<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelStaleTravelRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = Unit::factory()->create(['type' => 'research_centre']);
        $this->approver = User::factory()->create(['unit_id' => $unit->id, 'role' => 'centre_manager']);
        $this->owner = User::factory()->create(['unit_id' => $unit->id, 'role' => 'staff']);
        $this->other = User::factory()->create(['unit_id' => $unit->id, 'role' => 'staff']);
    }

    private function request(User $owner, string $status, string $createdAt, array $extra = []): TravelRequest
    {
        $tr = TravelRequest::factory()->create(array_merge([
            'requester_id' => $owner->id,
            'unit_id' => $owner->unit_id,
            'status' => $status,
            'current_approver_id' => $status === TravelRequest::STATUS_PENDING ? $this->approver->id : null,
            'travel_report_submitted_at' => null,
        ], $extra));

        // created_at is set by the framework, so push it back afterwards.
        $tr->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $tr->refresh();
    }

    public function test_it_requires_a_cutoff_date(): void
    {
        $this->artisan('travel-requests:cancel-stale')
            ->expectsOutputToContain('--before is required')
            ->assertFailed();
    }

    public function test_it_rejects_an_unreadable_date(): void
    {
        $this->artisan('travel-requests:cancel-stale --before=not-a-date')
            ->assertFailed();
    }

    public function test_it_changes_nothing_without_apply(): void
    {
        $stale = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01')->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_PENDING, $stale->fresh()->status,
            'a dry run modified data');
    }

    public function test_it_cancels_stale_requests_and_clears_the_approver(): void
    {
        $stale = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply --force')
            ->assertSuccessful();

        $stale->refresh();
        $this->assertSame(TravelRequest::STATUS_CANCELLED, $stale->status);
        $this->assertNull($stale->current_approver_id, 'the request would have stayed in an approver queue');
    }

    public function test_it_leaves_requests_created_on_or_after_the_cutoff_alone(): void
    {
        $recent = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-09-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply --force')->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_PENDING, $recent->fresh()->status);
    }

    public function test_it_never_disturbs_a_finished_trip(): void
    {
        $reported = $this->request($this->owner, TravelRequest::STATUS_APPROVED, '2026-08-01', [
            'travel_report_submitted_at' => '2026-08-20',
        ]);
        $rejected = $this->request($this->owner, TravelRequest::STATUS_REJECTED, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply --force')->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_APPROVED, $reported->fresh()->status,
            'an approved trip that had already been reported on was cancelled');
        $this->assertSame(TravelRequest::STATUS_REJECTED, $rejected->fresh()->status);
    }

    public function test_drafts_are_left_alone_unless_asked_for(): void
    {
        $draft = $this->request($this->owner, TravelRequest::STATUS_DRAFT, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply --force')->assertSuccessful();
        $this->assertSame(TravelRequest::STATUS_DRAFT, $draft->fresh()->status);

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --include-drafts --apply --force')->assertSuccessful();
        $this->assertSame(TravelRequest::STATUS_CANCELLED, $draft->fresh()->status);
    }

    public function test_it_can_be_limited_to_one_person(): void
    {
        $mine = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-08-01');
        $theirs = $this->request($this->other, TravelRequest::STATUS_PENDING, '2026-08-01');

        $this->artisan("travel-requests:cancel-stale --before=2026-09-01 --user={$this->owner->email} --apply --force")
            ->assertSuccessful();

        $this->assertSame(TravelRequest::STATUS_CANCELLED, $mine->fresh()->status);
        $this->assertSame(TravelRequest::STATUS_PENDING, $theirs->fresh()->status,
            'scoping to one user cancelled somebody else');
    }

    public function test_an_unknown_email_fails_rather_than_cancelling_everything(): void
    {
        $stale = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --user=nobody@nimr.or.tz --apply --force')
            ->assertFailed();

        $this->assertSame(TravelRequest::STATUS_PENDING, $stale->fresh()->status);
    }

    public function test_every_cancellation_is_logged(): void
    {
        $stale = $this->request($this->owner, TravelRequest::STATUS_PENDING, '2026-08-01');

        $this->artisan('travel-requests:cancel-stale --before=2026-09-01 --apply --force')->assertSuccessful();

        $log = ActivityLog::where('action', 'cancelled')
            ->where('subject_id', $stale->id)
            ->firstOrFail();

        $this->assertSame(TravelRequest::STATUS_PENDING, $log->changes['before']['status']);
        $this->assertStringContainsString('cancel-stale', $log->changes['reason']);
    }
}
