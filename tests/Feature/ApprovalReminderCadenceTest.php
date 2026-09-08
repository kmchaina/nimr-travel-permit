<?php

namespace Tests\Feature;

use App\Models\ApprovalAction;
use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TravelRequestReminderNotification;
use App\Notifications\TravelRequestStillPendingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalReminderCadenceTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;
    private User $approver;
    private User $nextApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = Unit::factory()->create(['type' => 'research_centre']);
        $this->approver = User::factory()->create(['unit_id' => $unit->id, 'role' => 'supervisor']);
        $this->nextApprover = User::factory()->create(['unit_id' => $unit->id, 'role' => 'centre_manager']);
        $this->requester = User::factory()->create(['unit_id' => $unit->id, 'role' => 'staff']);
    }

    private function pending(array $overrides = []): TravelRequest
    {
        return TravelRequest::factory()->create(array_merge([
            'requester_id' => $this->requester->id,
            'unit_id' => $this->requester->unit_id,
            'status' => TravelRequest::STATUS_PENDING,
            'current_approver_id' => $this->approver->id,
            'submitted_at' => now()->subDays(4),
            'b_departure_date' => now()->addDays(30),
            'b_return_date' => now()->addDays(35),
            'approval_last_reminded_at' => null,
        ], $overrides));
    }

    public function test_nothing_is_sent_before_three_days(): void
    {
        Notification::fake();
        $this->pending(['submitted_at' => now()->subDays(2)]);

        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_approver_is_reminded_and_the_requester_copied(): void
    {
        Notification::fake();
        $this->pending();

        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertSentTo($this->approver, TravelRequestReminderNotification::class);
        Notification::assertSentTo($this->requester, TravelRequestStillPendingNotification::class);
    }

    public function test_it_does_not_repeat_the_next_day(): void
    {
        Notification::fake();
        $request = $this->pending();

        $this->artisan('approvals:remind')->assertSuccessful();
        $this->assertNotNull($request->fresh()->approval_last_reminded_at);

        Carbon::setTestNow(now()->addDay());
        Notification::fake();
        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_it_repeats_after_another_three_days(): void
    {
        $request = $this->pending();
        $this->artisan('approvals:remind')->assertSuccessful();

        Carbon::setTestNow(now()->addDays(3));
        Notification::fake();
        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertSentTo($this->approver, TravelRequestReminderNotification::class);
        Notification::assertSentTo($this->requester, TravelRequestStillPendingNotification::class);
        Carbon::setTestNow();
    }

    public function test_the_clock_restarts_when_the_request_moves_to_the_next_approver(): void
    {
        // Chased under the first approver, then approved and passed on today.
        $request = $this->pending(['approval_last_reminded_at' => now()->subDay()]);

        ApprovalAction::create([
            'travel_request_id' => $request->id,
            'actor_id' => $this->approver->id,
            'stage' => 'supervisor',
            'decision' => 'approved',
            'acted_at' => now(),
        ]);
        $request->forceFill(['current_approver_id' => $this->nextApprover->id])->save();

        Notification::fake();
        $this->artisan('approvals:remind')->assertSuccessful();

        // It only just landed with them — they do not inherit the old clock.
        Notification::assertNothingSent();

        Carbon::setTestNow(now()->addDays(3));
        Notification::fake();
        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertSentTo($this->nextApprover, TravelRequestReminderNotification::class);
        Carbon::setTestNow();
    }

    public function test_reminders_stop_once_the_departure_date_arrives(): void
    {
        Notification::fake();
        $this->pending(['b_departure_date' => now(), 'b_return_date' => now()->addDays(3)]);

        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_decided_request_is_never_chased(): void
    {
        Notification::fake();
        foreach ([TravelRequest::STATUS_APPROVED, TravelRequest::STATUS_REJECTED, TravelRequest::STATUS_CANCELLED] as $status) {
            $this->pending(['status' => $status]);
        }

        $this->artisan('approvals:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_dry_run_sends_nothing_and_records_nothing(): void
    {
        Notification::fake();
        $request = $this->pending();

        $this->artisan('approvals:remind --dry-run')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($request->fresh()->approval_last_reminded_at);
    }
}
