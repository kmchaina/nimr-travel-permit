<?php

namespace App\Console\Commands;

use App\Models\TravelRequest;
use App\Notifications\TravelRequestReminderNotification;
use App\Notifications\TravelRequestStillPendingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chase a request that is sitting with an approver.
 *
 * Runs daily but does not email daily: a request is chased three days after it
 * lands with its current approver, then every three days, until its departure
 * date arrives. After that it goes quiet — the trip date has passed and a
 * reminder to approve it is no longer the useful message.
 *
 * The cadence is measured from when the request landed with *this* approver,
 * not from submission. A request that took nine days to reach the Director
 * General should not arrive on their desk already overdue; their three days
 * start when it becomes theirs. That landing time is the most recent approval
 * action, or the submission itself for the first approver in the chain.
 *
 * Comparing the last-reminded stamp against that landing time is also what
 * resets the cadence as a request moves up: a stamp left by the previous
 * approver is older than the new landing, so the clock starts again.
 *
 * The requester is copied on every reminder. Between submitting and a decision
 * they used to hear nothing, so a request nobody had touched looked exactly
 * like one being actively chased.
 */
class NotifyPendingApprovers extends Command
{
    protected $signature = 'approvals:remind
        {--days=3 : Days a request must sit with its current approver before each reminder}
        {--dry-run : Report what would be sent without sending or recording anything}';

    protected $description = 'Remind the current approver every few days about a request still waiting, and copy the requester';

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();

        $pending = TravelRequest::with(['currentApprover', 'requester'])
            ->where('status', TravelRequest::STATUS_PENDING)
            ->whereNotNull('current_approver_id')
            ->whereNotNull('submitted_at')
            // Silence once the travel date has arrived.
            ->whereNotNull('b_departure_date')
            ->whereDate('b_departure_date', '>', $now->toDateString())
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($pending as $travelRequest) {
            $approver = $travelRequest->currentApprover;

            if (! $approver || ! $approver->is_active) {
                $skipped++;
                continue;
            }

            $landedAt = $this->landedAt($travelRequest);

            if (! $landedAt) {
                $skipped++;
                continue;
            }

            // A reminder left by a previous approver does not count for this one.
            $lastChased = $travelRequest->approval_last_reminded_at;
            $since = ($lastChased && $lastChased->greaterThan($landedAt)) ? $lastChased : $landedAt;

            if ($since->copy()->addDays($interval)->greaterThan($now)) {
                $skipped++;
                continue;
            }

            $daysWaiting = max(1, (int) $landedAt->diffInDays($now));

            if ($dryRun) {
                $this->line("  Would remind {$approver->email} about {$travelRequest->request_number} ({$daysWaiting}d with them)");
                $sent++;
                continue;
            }

            try {
                $approver->notify(new TravelRequestReminderNotification($travelRequest, $daysWaiting));

                // The requester's copy must never stop the approver being chased.
                $requester = $travelRequest->requester;
                if ($requester && $requester->is_active) {
                    $requester->notify(new TravelRequestStillPendingNotification($travelRequest, $daysWaiting, $approver));
                }

                $travelRequest->forceFill(['approval_last_reminded_at' => $now])->save();
                $sent++;

                $this->line("  Reminded {$approver->name} about {$travelRequest->request_number} ({$daysWaiting}d with them)");
            } catch (\Throwable $e) {
                $this->warn("  Failed to remind {$approver->name} for {$travelRequest->request_number}: {$e->getMessage()}");
            }
        }

        $this->info($dryRun
            ? "Dry run: {$sent} reminder(s) due, {$skipped} not yet due."
            : "Done. Sent {$sent} reminder(s); {$skipped} not yet due.");

        return self::SUCCESS;
    }

    /**
     * When the request became this approver's to act on: the most recent
     * decision recorded against it, or its submission for the first approver.
     */
    private function landedAt(TravelRequest $travelRequest): ?Carbon
    {
        $lastAction = $travelRequest->approvalActions()
            ->reorder()
            ->max('acted_at');

        return $lastAction ? Carbon::parse($lastAction) : $travelRequest->submitted_at;
    }
}
