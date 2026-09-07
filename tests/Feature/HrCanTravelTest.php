<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * An HR officer oversees everyone's travel and also travels themselves. The UI
 * grouped them with the Director General as a pure oversight role, which took
 * away every route into the request form — the form itself always accepted
 * them, so they were locked out by the absence of a button rather than by any
 * rule. The DG stays excluded: they have no approval chain to submit through.
 */
class HrCanTravelTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $centreManager;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['role' => 'director_general', 'unit_id' => null]);
        $centre = Unit::factory()->create(['type' => 'research_centre']);
        $this->centreManager = User::factory()->create(['unit_id' => $centre->id, 'role' => 'centre_manager']);
        $this->hr = User::factory()->create([
            'unit_id' => $centre->id,
            'role' => 'hr',
            'supervisor_id' => $this->centreManager->id,
        ]);
    }

    public function test_hr_is_offered_a_way_into_the_request_form(): void
    {
        $createUrl = route('travel-requests.create');

        $this->assertStringContainsString($createUrl,
            $this->actingAs($this->hr)->get('/dashboard')->assertOk()->getContent(),
            'an HR officer had no way to start a request from the dashboard');

        $this->assertStringContainsString($createUrl,
            $this->actingAs($this->hr)->get('/travel-requests')->assertOk()->getContent(),
            'an HR officer had no way to start a request from the request list');
    }

    public function test_hr_can_submit_a_travel_request(): void
    {
        $before = TravelRequest::count();

        $this->actingAs($this->hr)->get('/travel-requests/create')->assertOk();

        $this->actingAs($this->hr)->post('/travel-requests', [
            'action' => 'submit',
            'b_phone' => '+255 700 000 001',
            'b_destination' => 'Arusha',
            'b_departure_date' => now()->addMonth()->toDateString(),
            'b_return_date' => now()->addMonth()->addDays(4)->toDateString(),
            'd_benefit_to_institution' => 'Payroll systems training.',
            'd_benefit_to_nation' => 'Better public service administration.',
            'd_consequences_if_rejected' => 'Training slot lost.',
            'e_govt_cost_i' => '900,000',
            'f_previous_travel_impact' => 'Improved records handling.',
            'g_handover_officer_name' => 'Someone',
            'g_handover_document' => UploadedFile::fake()->create('handover.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame($before + 1, TravelRequest::count());

        $request = TravelRequest::latest('id')->firstOrFail();
        $this->assertSame($this->hr->id, $request->requester_id);
        $this->assertSame(TravelRequest::STATUS_PENDING, $request->status);
        $this->assertSame($this->centreManager->id, $request->current_approver_id,
            'the HR officer\'s request did not reach their supervisor');
    }

    public function test_the_director_general_is_still_not_offered_the_form(): void
    {
        $dg = User::where('role', 'director_general')->firstOrFail();

        $this->assertStringNotContainsString(route('travel-requests.create'),
            $this->actingAs($dg)->get('/travel-requests')->assertOk()->getContent(),
            'the DG was offered a form they cannot submit through');
    }
}
