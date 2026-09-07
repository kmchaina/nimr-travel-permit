<?php

namespace Tests\Feature;

use App\Models\ApprovalAction;
use App\Models\TravelRequest;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelStaleTravelRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_stale_requests_without_cancelling_them(): void
    {
        $request = TravelRequest::factory()->create([
            'unit_id' => Unit::factory(),
            'status' => TravelRequest::STATUS_PENDING,
            'b_departure_date' => '2026-08-30',
        ]);

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01')
            ->expectsTable(
                ['ID', 'Request #', 'Status', 'Departure', 'Requester', 'Current approver'],
                [[$request->id, $request->request_number, 'pending', '2026-08-30', '-', '-']]
            )
            ->expectsOutput('Dry run only. Re-run with --apply to cancel 1 request(s).')
            ->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_it_cancels_only_stale_cancellable_requests_when_applied(): void
    {
        $eligible = TravelRequest::factory()->create([
            'unit_id' => Unit::factory(),
            'status' => TravelRequest::STATUS_PENDING,
            'b_departure_date' => '2026-08-30',
            'current_approver_id' => null,
        ]);

        $future = TravelRequest::factory()->create([
            'unit_id' => Unit::factory(),
            'status' => TravelRequest::STATUS_PENDING,
            'b_departure_date' => '2026-09-01',
        ]);

        $alreadyApprovedStep = TravelRequest::factory()->create([
            'unit_id' => Unit::factory(),
            'status' => TravelRequest::STATUS_PENDING,
            'b_departure_date' => '2026-08-29',
        ]);

        ApprovalAction::create([
            'travel_request_id' => $alreadyApprovedStep->id,
            'stage' => 'supervisor',
            'decision' => 'approved',
            'acted_at' => now(),
        ]);

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply')
            ->expectsOutput('Cancelled 1 stale travel request(s).')
            ->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_CANCELLED, $eligible->fresh()->status);
        $this->assertSame(TravelRequest::STATUS_PENDING, $future->fresh()->status);
        $this->assertSame(TravelRequest::STATUS_PENDING, $alreadyApprovedStep->fresh()->status);
    }
}
