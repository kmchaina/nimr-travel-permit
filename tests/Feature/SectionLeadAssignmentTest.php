<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Services\ApprovalChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A section lead, a Director, a unit Manager and a Centre Manager are single
 * posts, not ranks. Nothing enforced that, so a section could be given three
 * Heads at once — and demoting one left their staff routing approvals to an
 * ordinary colleague with no authority to grant them.
 */
class SectionLeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Unit $directorate;
    private Unit $section;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['role' => 'director_general', 'unit_id' => null]);
        $this->admin = User::factory()->create(['role' => 'system_admin', 'unit_id' => null]);

        $this->directorate = Unit::factory()->create(['type' => 'hq_directorate']);
        User::factory()->create(['unit_id' => $this->directorate->id, 'role' => 'director']);

        $this->section = Unit::factory()->create(['type' => 'hq_section', 'parent_id' => $this->directorate->id]);
        $this->head = User::factory()->create(['unit_id' => $this->section->id, 'role' => 'head']);
    }

    private function createUser(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/users', array_merge([
            'name' => 'Someone New',
            'email' => 'someone.new@nimr.or.tz',
            'role' => 'head',
            'unit_id' => $this->section->id,
            'is_active' => 1,
        ], $overrides));
    }

    public function test_a_section_cannot_be_given_a_second_head(): void
    {
        $this->createUser()->assertSessionHasErrors('role');

        $this->assertSame(1, User::where('unit_id', $this->section->id)->where('role', 'head')->count());
    }

    public function test_a_staff_member_cannot_be_promoted_alongside_the_existing_head(): void
    {
        $staff = User::factory()->create(['unit_id' => $this->section->id, 'role' => 'staff', 'supervisor_id' => $this->head->id]);

        $this->actingAs($this->admin)->patch("/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'head',
            'unit_id' => $this->section->id,
            'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->assertSame('staff', $staff->fresh()->role);
    }

    public function test_the_post_can_be_filled_once_the_incumbent_steps_down(): void
    {
        $this->actingAs($this->admin)->patch("/users/{$this->head->id}", [
            'name' => $this->head->name,
            'email' => $this->head->email,
            'role' => 'staff',
            'unit_id' => $this->section->id,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->createUser()->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('unit_id', $this->section->id)->where('role', 'head')->where('is_active', true)->count());
    }

    public function test_demoting_a_head_releases_the_people_reporting_to_them(): void
    {
        $staff = User::factory()->create(['unit_id' => $this->section->id, 'role' => 'staff', 'supervisor_id' => $this->head->id]);

        $this->actingAs($this->admin)->patch("/users/{$this->head->id}", [
            'name' => $this->head->name,
            'email' => $this->head->email,
            'role' => 'staff',
            'unit_id' => $this->section->id,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull($staff->fresh()->supervisor_id,
            'the staff member still reports to someone who can no longer approve for them');
    }

    public function test_a_stale_supervisor_cannot_be_used_to_build_a_chain(): void
    {
        $staff = User::factory()->create(['unit_id' => $this->section->id, 'role' => 'staff', 'supervisor_id' => $this->head->id]);

        // Demote directly, bypassing the controller, as older data already is.
        $this->head->forceFill(['role' => 'staff'])->save();

        $this->expectException(\RuntimeException::class);
        app(ApprovalChainService::class)->buildChain($staff->fresh());
    }

    public function test_the_rule_covers_directors_and_centre_managers_too(): void
    {
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Second Director', 'email' => 'second.director@nimr.or.tz',
            'role' => 'director', 'unit_id' => $this->directorate->id, 'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $centre = Unit::factory()->create(['type' => 'research_centre']);
        User::factory()->create(['unit_id' => $centre->id, 'role' => 'centre_manager']);

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Second CM', 'email' => 'second.cm@nimr.or.tz',
            'role' => 'centre_manager', 'unit_id' => $centre->id, 'is_active' => 1,
        ])->assertSessionHasErrors('role');
    }

    public function test_an_inactive_incumbent_does_not_block_a_replacement(): void
    {
        $this->head->forceFill(['is_active' => false])->save();

        $this->createUser()->assertSessionHasNoErrors();
    }
}
