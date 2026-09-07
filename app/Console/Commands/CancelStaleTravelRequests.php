<?php

namespace App\Console\Commands;

use App\Models\TravelRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelStaleTravelRequests extends Command
{
    protected $signature = 'travel-requests:cancel-stale
        {--before= : Cancel cancellable requests with departure dates before this YYYY-MM-DD date}
        {--apply : Persist the cancellation instead of only reporting matches}';

    protected $description = 'Cancel stale draft, pending, or returned travel requests whose departure date has passed';

    public function handle(): int
    {
        $before = $this->option('before');
        if (! $before) {
            $this->error('The --before=YYYY-MM-DD option is required.');

            return self::FAILURE;
        }

        try {
            $cutoff = CarbonImmutable::createFromFormat('Y-m-d', $before)->startOfDay();
        } catch (\Throwable) {
            $this->error('The --before option must use YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $query = TravelRequest::query()
            ->with(['requester:id,name,email', 'currentApprover:id,name,email'])
            ->whereIn('status', [
                TravelRequest::STATUS_DRAFT,
                TravelRequest::STATUS_PENDING,
                TravelRequest::STATUS_RETURNED,
            ])
            ->whereDate('b_departure_date', '<', $cutoff->toDateString())
            ->whereDoesntHave('approvalActions', function ($query) {
                $query->where('decision', 'approved');
            })
            ->orderBy('b_departure_date')
            ->orderBy('id');

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info("No stale cancellable travel requests found before {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Request #', 'Status', 'Departure', 'Requester', 'Current approver'],
            $matches->map(fn (TravelRequest $request) => [
                $request->id,
                $request->request_number,
                $request->status,
                optional($request->b_departure_date)->toDateString(),
                $request->requester?->email ?? $request->requester?->name ?? '-',
                $request->currentApprover?->email ?? $request->currentApprover?->name ?? '-',
            ])->all()
        );

        if (! $apply) {
            $this->warn("Dry run only. Re-run with --apply to cancel {$matches->count()} request(s).");

            return self::SUCCESS;
        }

        $cancelled = 0;
        DB::transaction(function () use ($matches, &$cancelled) {
            TravelRequest::whereKey($matches->modelKeys())
                ->lockForUpdate()
                ->get()
                ->each(function (TravelRequest $request) use (&$cancelled) {
                    if (! $request->isCancellable()) {
                        return;
                    }

                    $request->forceFill([
                        'status' => TravelRequest::STATUS_CANCELLED,
                        'current_approver_id' => null,
                    ])->save();

                    $cancelled++;
                });
        });

        $this->info("Cancelled {$cancelled} stale travel request(s).");

        return self::SUCCESS;
    }
}
