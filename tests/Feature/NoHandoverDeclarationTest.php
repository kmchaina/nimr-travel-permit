<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A traveller with genuinely nobody to cover their duties may say so instead of
 * inventing an officer. The wizard makes that deliberate — a checkbox, a written
 * declaration, and a confirmation dialog — but all three are JavaScript, so what
 * matters here is that the server decides the requirement again for itself.
 */
class NoHandoverDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private User $traveller;
    private User $colleague;
    private Unit $centre;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['role' => 'director_general', 'unit_id' => null]);
        $this->centre = Unit::factory()->create(['type' => 'research_centre']);
        $cm = User::factory()->create(['unit_id' => $this->centre->id, 'role' => 'centre_manager']);
        $this->traveller = User::factory()->create(['unit_id' => $this->centre->id, 'role' => 'staff', 'supervisor_id' => $cm->id]);
        $this->colleague = User::factory()->create(['unit_id' => $this->centre->id, 'role' => 'staff', 'supervisor_id' => $cm->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'action' => 'submit',
            'b_phone' => '+255 700 000 001',
            'b_destination' => 'Arusha',
            'b_departure_date' => now()->addMonth()->toDateString(),
            'b_return_date' => now()->addMonth()->addDays(4)->toDateString(),
            'd_benefit_to_institution' => 'Shares findings.',
            'd_benefit_to_nation' => 'Informs policy.',
            'd_consequences_if_rejected' => 'Slot lost.',
            'e_govt_cost_i' => '900,000',
            'f_previous_travel_impact' => 'Improved throughput.',
        ], $overrides);
    }

    private function declaration(): string
    {
        return 'I am the only officer at this station and the laboratory closes while I am away.';
    }

    public function test_an_officer_and_a_note_are_still_required_by_default(): void
    {
        $this->actingAs($this->traveller)
            ->post('/travel-requests', $this->payload())
            ->assertSessionHasErrors(['g_handover_officer_name', 'g_handover_document']);

        $this->assertSame(0, TravelRequest::count());
    }

    public function test_declaring_no_handover_replaces_the_officer_and_the_note(): void
    {
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'g_no_handover_officer' => '1',
            'g_no_handover_declaration' => $this->declaration(),
        ]))->assertSessionHasNoErrors();

        $request = TravelRequest::latest('id')->firstOrFail();

        $this->assertTrue($request->g_no_handover_officer);
        $this->assertSame($this->declaration(), $request->g_no_handover_declaration);
        $this->assertNull($request->g_handover_document);
        $this->assertNull($request->g_handover_officer_name);
        $this->assertSame(TravelRequest::STATUS_PENDING, $request->status);
    }

    public function test_the_declaration_cannot_be_left_out(): void
    {
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'g_no_handover_officer' => '1',
        ]))->assertSessionHasErrors('g_no_handover_declaration');

        $this->assertSame(0, TravelRequest::count());
    }

    public function test_a_token_declaration_is_refused(): void
    {
        foreach (['n/a', 'none', '-', 'no one'] as $token) {
            $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
                'g_no_handover_officer' => '1',
                'g_no_handover_declaration' => $token,
            ]))->assertSessionHasErrors('g_no_handover_declaration');
        }

        $this->assertSame(0, TravelRequest::count());
    }

    public function test_declaring_no_handover_clears_any_officer_that_was_also_sent(): void
    {
        // A direct POST carrying both accounts of the duties: the declaration
        // wins and the officer is dropped, so the permit cannot show both.
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'g_no_handover_officer' => '1',
            'g_no_handover_declaration' => $this->declaration(),
            'g_handover_officer_id' => $this->colleague->id,
            'g_handover_officer_name' => $this->colleague->name,
        ]))->assertSessionHasNoErrors();

        $request = TravelRequest::latest('id')->firstOrFail();

        $this->assertNull($request->g_handover_officer_id);
        $this->assertNull($request->g_handover_officer_name);
    }

    public function test_naming_an_officer_still_works_unchanged(): void
    {
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'g_handover_officer_id' => $this->colleague->id,
            'g_handover_officer_name' => $this->colleague->name,
            'g_handover_document' => UploadedFile::fake()->create('handover.pdf', 40, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $request = TravelRequest::latest('id')->firstOrFail();

        $this->assertFalse($request->g_no_handover_officer);
        $this->assertSame($this->colleague->id, $request->g_handover_officer_id);
        $this->assertNotNull($request->g_handover_document);
    }

    public function test_a_draft_may_be_parked_without_the_declaration(): void
    {
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'action' => 'draft',
            'g_no_handover_officer' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(TravelRequest::STATUS_DRAFT, TravelRequest::latest('id')->firstOrFail()->status);
    }

    public function test_the_approver_sees_the_declaration_on_the_request(): void
    {
        $this->actingAs($this->traveller)->post('/travel-requests', $this->payload([
            'g_no_handover_officer' => '1',
            'g_no_handover_declaration' => $this->declaration(),
        ]));

        $request = TravelRequest::latest('id')->firstOrFail();
        $approver = User::findOrFail($request->current_approver_id);

        $this->actingAs($approver)
            ->get("/travel-requests/{$request->id}")
            ->assertOk()
            ->assertSee($this->declaration());
    }
}
