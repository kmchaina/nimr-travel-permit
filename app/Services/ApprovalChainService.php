<?php

namespace App\Services;

use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class ApprovalChainService
{
    public function __construct(private SupervisorService $supervisors) {}

    /**
     * Build the ordered approval chain for a given traveller.
     *
     * Returns an array of steps, each being:
     *   ['stage' => string, 'approver_id' => int]
     *
     * Stages: supervisor | director | final
     *
     * HR receives notification copies only and does not approve requests.
     */
    public function buildChain(User $traveller): array
    {
        $unit = $traveller->unit;

        if (!$unit) {
            throw new RuntimeException("You are not assigned to an organisational unit. Please contact your system administrator before submitting a travel request.");
        }

        return match (true) {
            $unit->type === 'research_centre' => $this->chainForCentre($traveller),
            $unit->type === 'hq_section'      => $this->chainForHqSection($traveller),
            $unit->type === 'hq_standalone'   => $this->chainForHqStandalone($traveller),
            $unit->type === 'hq_directorate'  => $this->chainForHqDirectorate($traveller),
            default => throw new RuntimeException("Unknown unit type [{$unit->type}]."),
        };
    }

    /**
     * After an approval action is recorded, advance the request to the
     * next step or mark it fully approved/rejected/returned.
     */
    public function advance(TravelRequest $request, string $decision): void
    {
        if ($decision === 'rejected') {
            $request->update([
                'status'              => TravelRequest::STATUS_REJECTED,
                'current_approver_id' => null,
            ]);
            return;
        }

        if ($decision === 'returned') {
            $chain        = $request->approval_chain;
            $currentIndex = $this->currentStepIndex($request);

            if (! $this->returnGoesToRequester($request)) {
                // Non-first approver returns → route to the previous approver in the chain.
                // The requester is NOT involved; the previous approver re-reviews and decides
                // whether to re-approve (send it back up) or return it to the requester.
                $request->update([
                    'status'              => TravelRequest::STATUS_PENDING,
                    'current_approver_id' => $chain[$currentIndex - 1]['approver_id'],
                ]);
            } else {
                // First approver (or chain not found) returns → goes back to the requester.
                $request->update([
                    'status'              => TravelRequest::STATUS_RETURNED,
                    'current_approver_id' => null,
                    'approval_chain'      => null,
                    'submitted_at'        => null,
                ]);
            }
            return;
        }

        $chain     = $request->approval_chain;
        $currentId = $request->current_approver_id;

        $currentIndex = collect($chain)->search(fn($step) => (int)$step['approver_id'] === (int)$currentId);

        if ($currentIndex === false) {
            throw new \RuntimeException(
                "Approver [{$currentId}] not found in approval chain for request [{$request->id}]. Possible data corruption."
            );
        }

        $nextStep = $chain[$currentIndex + 1] ?? null;

        if ($nextStep) {
            $request->update([
                'current_approver_id' => $nextStep['approver_id'],
            ]);
        } else {
            $request->update([
                'status'              => TravelRequest::STATUS_APPROVED,
                'current_approver_id' => null,
            ]);
        }
    }

    /**
     * Position of the current approver within the request's chain, or false
     * when the chain is absent or does not contain them.
     */
    public function currentStepIndex(TravelRequest $request): int|false
    {
        $index = collect($request->approval_chain ?? [])->search(
            fn ($step) => (int) $step['approver_id'] === (int) $request->current_approver_id
        );

        return $index === false ? false : (int) $index;
    }

    /**
     * Where a "Return for Revision" from the current approver lands.
     *
     * Returning steps back exactly one place in the chain: the first approver
     * returns to the requester, anyone further up returns to the approver
     * below them, who re-reviews and decides whether to send it up again.
     */
    public function returnGoesToRequester(TravelRequest $request): bool
    {
        $index = $this->currentStepIndex($request);

        return $index === false || $index === 0;
    }

    public function hrCopyRecipients(TravelRequest $request): Collection
    {
        $request->loadMissing(['requester', 'unit']);

        if (
            $request->unit?->type === 'research_centre'
            && $request->requester?->role !== 'centre_manager'
        ) {
            return User::where('unit_id', $request->unit_id)
                ->where('role', 'hr')
                ->where('is_active', true)
                ->get();
        }

        $hqHrUnitId = Unit::where('code', 'HRMAS')->value('id');

        if (!$hqHrUnitId) {
            return collect();
        }

        return User::where('unit_id', $hqHrUnitId)
            ->where('role', 'hr')
            ->where('is_active', true)
            ->get();
    }

    // ------------------------------------------------------------------
    // Chain builders per unit type
    // ------------------------------------------------------------------

    /**
     * A stored supervisor_id is only as good as the day it was chosen. If that
     * person has since been demoted, moved unit or deactivated, the chain would
     * still route step one to them — sending a permit for approval to somebody
     * with no authority to approve it. Refuse, and say what to do about it.
     */
    private function assertSupervisorStillValid(User $traveller): int
    {
        $supervisorId = (int) $traveller->supervisor_id;

        if (! $this->supervisors->isValidCandidate(
            $supervisorId,
            $traveller->unit,
            $traveller->role,
            $traveller->getKey(),
        )) {
            throw new RuntimeException(
                'Your selected supervisor can no longer approve travel for you — their role or unit has changed. Please choose your supervisor again on the Dashboard before submitting.'
            );
        }

        return $supervisorId;
    }

    private function chainForCentre(User $traveller): array
    {
        $unit          = $traveller->unit;
        $centreManager = $this->findInUnit($unit->id, 'centre_manager', "{$unit->name} does not have an active Centre Manager assigned. Contact your system administrator.");
        $dg            = $this->findDirectorGeneral();

        return match ($traveller->role) {

            // Centre Manager travels → DG
            'centre_manager' => [
                ['stage' => 'final', 'approver_id' => $dg->id],
            ],

            // Supervisor is the top of the centre's staff hierarchy, below the
            // Centre Manager — no personal supervisor of their own needed.
            'supervisor' => [
                ['stage' => 'final', 'approver_id' => $centreManager->id],
            ],

            // Staff must have a supervisor set; go supervisor → centre_manager.
            //
            // Not everyone in a centre sits under a Supervisor — some report
            // straight to the Centre Manager. When that is who they report to,
            // the chain is a single step: routing supervisor → centre_manager
            // would otherwise put the same person in two consecutive stages and
            // ask them to approve the same request twice.
            'staff', 'hr', 'system_admin' => match (true) {
                ! $traveller->supervisor_id => throw new RuntimeException(
                    "You have not selected a supervisor. Please set your supervisor on the Dashboard before submitting a travel request."
                ),
                (int) $traveller->supervisor_id === (int) $centreManager->id => [
                    ['stage' => 'final', 'approver_id' => $centreManager->id],
                ],
                default => [
                    ['stage' => 'supervisor', 'approver_id' => $this->assertSupervisorStillValid($traveller)],
                    ['stage' => 'final',      'approver_id' => $centreManager->id],
                ],
            },

            default => throw new RuntimeException("Unhandled role [{$traveller->role}] for research centre."),
        };
    }

    private function chainForHqSection(User $traveller): array
    {
        $unit   = $traveller->unit;
        $parent = $unit->parent;
        $dg     = $this->findDirectorGeneral();

        if (!$parent) {
            throw new RuntimeException("The section \"{$unit->name}\" has no parent Directorate configured. Contact your system administrator.");
        }

        $directorId = $this->findInUnit($parent->id, 'director', "The Directorate \"{$parent->name}\" has no active Director assigned. Contact your system administrator.")->id;

        return match ($traveller->role) {

            // Section lead travels → straight to the Directorate's Director.
            // Scientific sections (under RCPD/RIRAD) are led by a "head";
            // Corporate Services sections (under CSD) are led by a "manager".
            // Either way, the lead is the top of their section and needs no supervisor.
            'head', 'manager' => [
                ['stage' => 'director', 'approver_id' => $directorId],
                ['stage' => 'final',    'approver_id' => $dg->id],
            ],

            'staff', 'hr', 'system_admin' => $traveller->supervisor_id
                ? [
                    ['stage' => 'supervisor', 'approver_id' => $this->assertSupervisorStillValid($traveller)],
                    ['stage' => 'director',   'approver_id' => $directorId],
                    ['stage' => 'final',      'approver_id' => $dg->id],
                ]
                : throw new RuntimeException(
                    "You have not selected a supervisor. Please set your supervisor on the Dashboard before submitting a travel request."
                ),

            default => throw new RuntimeException("Your role cannot submit travel requests through this unit. Contact your system administrator."),
        };
    }

    private function chainForHqStandalone(User $traveller): array
    {
        $unit = $traveller->unit;
        $dg   = $this->findDirectorGeneral();

        return match ($traveller->role) {

            'manager' => [
                ['stage' => 'final', 'approver_id' => $dg->id],
            ],

            'staff', 'hr', 'system_admin' => $traveller->supervisor_id
                ? [
                    ['stage' => 'supervisor', 'approver_id' => $this->assertSupervisorStillValid($traveller)],
                    ['stage' => 'final',      'approver_id' => $dg->id],
                ]
                : throw new RuntimeException(
                    "You have not selected a supervisor. Please set your supervisor on the Dashboard before submitting a travel request."
                ),

            default => throw new RuntimeException("Your role cannot submit travel requests through this unit. Contact your system administrator."),
        };
    }

    private function chainForHqDirectorate(User $traveller): array
    {
        $dg = $this->findDirectorGeneral();

        return match ($traveller->role) {

            // Only the Director sits directly in a Directorate unit per the NIMR
            // organogram — everyone else belongs under one of its sections and must
            // go through chainForHqSection instead. Without this check, any other
            // role accidentally placed here (e.g. self-registration picking the
            // Directorate instead of a section) would skip straight to the DG,
            // bypassing supervisor/section-head/director review entirely.
            'director' => [
                ['stage' => 'final', 'approver_id' => $dg->id],
            ],

            default => throw new RuntimeException("Your role cannot submit travel requests through this unit. Contact your system administrator."),
        };
    }

    // ------------------------------------------------------------------
    // Finders
    // ------------------------------------------------------------------

    private function findInUnit(int $unitId, string $role, string $message = ''): User
    {
        $user = User::where('unit_id', $unitId)
                    ->where('role', $role)
                    ->where('is_active', true)
                    ->first();

        if (!$user) {
            throw new RuntimeException($message ?: "No active {$role} found in the required unit. Contact your system administrator.");
        }

        return $user;
    }

    private function findDirectorGeneral(): User
    {
        $dg = User::where('role', 'director_general')->where('is_active', true)->first();

        if (!$dg) {
            throw new RuntimeException("No active Director General is configured in the system. Contact your system administrator.");
        }

        return $dg;
    }

}
