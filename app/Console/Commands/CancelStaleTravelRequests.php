<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Cancel travel requests left over from testing so their owners are not held
 * hostage by them.
 *
 * A request blocks its owner from submitting another while it is still live —
 * pending, or approved with no travel report filed. Demo requests submitted
 * before the system went into real use are exactly that: live, and never going
 * to be travelled or reported on. This retires them.
 *
 * Read-only unless --apply is passed, so the default run tells you what would
 * happen and changes nothing. Cancellation matches what the app itself does
 * when a requester cancels: status cancelled, and current_approver_id cleared
 * so the request also leaves its approver's queue.
 */
class CancelStaleTravelRequests extends Command
{
    use ConfirmableTrait;

    protected $signature = 'travel-requests:cancel-stale
        {--before= : Cancel requests created before this date (YYYY-MM-DD). Required.}
        {--user=* : Limit to these users, by email. Repeatable. Omit for everyone.}
        {--include-drafts : Also cancel drafts and returned requests, which block nobody}
        {--apply : Actually cancel. Without this the command only reports.}
        {--force : Skip the production confirmation prompt}';

    protected $description = 'Cancel leftover demo travel requests that are blocking their owners from submitting';

    public function handle(): int
    {
        $before = $this->option('before');

        if (! $before) {
            $this->error('--before is required, e.g. --before=2026-09-01');

            return self::FAILURE;
        }

        try {
            $cutoff = \Illuminate\Support\Carbon::parse($before)->startOfDay();
        } catch (\Throwable) {
            $this->error("Could not read --before=\"{$before}\". Use YYYY-MM-DD.");

            return self::FAILURE;
        }

        $emails = (array) $this->option('user');
        $userIds = null;

        if ($emails) {
            $users = User::whereIn('email', $emails)->get();
            $missing = array_diff($emails, $users->pluck('email')->all());

            if ($missing) {
                $this->error('No such user: '.implode(', ', $missing));

                return self::FAILURE;
            }

            $userIds = $users->pluck('id');
        }

        $query = $this->staleQuery($cutoff, $userIds);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to cancel.');

            return self::SUCCESS;
        }

        $this->report($query, $cutoff, $total);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Nothing was changed. Re-run with --apply to cancel these.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $cancelled = $this->cancel($query, $cutoff);

        $this->newLine();
        $this->info("Cancelled {$cancelled} request(s).");

        return self::SUCCESS;
    }

    /** Requests created before the cutoff that are still live. */
    private function staleQuery(\Illuminate\Support\Carbon $cutoff, $userIds)
    {
        $blocking = [TravelRequest::STATUS_PENDING, TravelRequest::STATUS_APPROVED];

        if ($this->option('include-drafts')) {
            $blocking[] = TravelRequest::STATUS_DRAFT;
            $blocking[] = TravelRequest::STATUS_RETURNED;
        }

        return TravelRequest::query()
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', $blocking)
            // An approved trip that has been reported on is finished; it blocks
            // nobody and there is no reason to disturb its record.
            ->where(function ($query) {
                $query->where('status', '!=', TravelRequest::STATUS_APPROVED)
                    ->orWhereNull('travel_report_submitted_at');
            })
            ->when($userIds, fn ($query) => $query->whereIn('requester_id', $userIds));
    }

    private function report($query, \Illuminate\Support\Carbon $cutoff, int $total): void
    {
        $this->newLine();
        $this->line("Requests created before {$cutoff->toDateString()} that are still live: <fg=yellow>{$total}</>");

        $byStatus = (clone $query)->selectRaw('status, count(*) as c')
            ->groupBy('status')->pluck('c', 'status');

        $this->table(['Status', 'Count'], $byStatus->map(
            fn ($count, $status) => [$status, $count]
        )->values()->all());

        $owners = (clone $query)->distinct()->count('requester_id');
        $this->line("Owners unblocked by this: <fg=yellow>{$owners}</>");

        $sample = (clone $query)->with('requester')->orderBy('created_at')->limit(10)->get();

        $this->newLine();
        $this->line('First few:');
        $this->table(
            ['Request', 'Status', 'Requester', 'Created'],
            $sample->map(fn ($r) => [
                $r->request_number,
                $r->status,
                $r->requester?->email ?? '—',
                $r->created_at?->toDateString(),
            ])->all()
        );

        if ($total > $sample->count()) {
            $this->line('  … and '.($total - $sample->count()).' more');
        }
    }

    private function cancel($query, \Illuminate\Support\Carbon $cutoff): int
    {
        $cancelled = 0;

        // Chunk by id and cancel row by row so every cancellation is logged
        // individually — a bulk UPDATE in production would leave no trace of
        // which requests were retired or what they were before.
        (clone $query)->chunkById(100, function ($requests) use (&$cancelled, $cutoff) {
            DB::transaction(function () use ($requests, &$cancelled, $cutoff) {
                foreach ($requests as $request) {
                    $before = $request->only(['status', 'current_approver_id']);

                    $request->update([
                        'status' => TravelRequest::STATUS_CANCELLED,
                        'current_approver_id' => null,
                    ]);

                    ActivityLog::record('cancelled', $request, [
                        'before' => $before,
                        'after' => ['status' => TravelRequest::STATUS_CANCELLED, 'current_approver_id' => null],
                        'reason' => "Stale request created before {$cutoff->toDateString()}, cancelled via travel-requests:cancel-stale",
                    ]);

                    $cancelled++;
                }
            });
        });

        return $cancelled;
    }
}
