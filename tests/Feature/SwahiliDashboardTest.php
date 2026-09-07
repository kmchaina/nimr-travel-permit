<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chunks of the dashboard were written as literal English rather than
 * translation keys, so a Swahili user got a page in two languages — a Swahili
 * sidebar above an English heading. Render it in Swahili and fail if any of
 * those phrases come back.
 */
class SwahiliDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ENGLISH_LEAKS = [
        'Departing today',
        'Trip in progress or past',
        'Permit issued',
        'Action required',
        'Action needed',
        'Your Requests',
        'Recent Requests',
        'No requests yet.',
        'Set supervisor first',
        'New request',
        'Not submitted',
    ];

    private function staff(): User
    {
        User::factory()->create(['role' => 'director_general', 'unit_id' => null]);
        $centre = Unit::factory()->create(['type' => 'research_centre']);
        $cm = User::factory()->create(['unit_id' => $centre->id, 'role' => 'centre_manager']);

        return User::factory()->create([
            'unit_id' => $centre->id, 'role' => 'staff', 'supervisor_id' => $cm->id,
        ]);
    }

    public function test_the_dashboard_is_wholly_swahili_for_a_swahili_user(): void
    {
        $staff = $this->staff();

        // SetLocale reads the session (defaulting to Swahili), so drive it the
        // way a real request does rather than by setting the locale directly.
        $html = $this->actingAs($staff)->withSession(['locale' => 'sw'])
            ->get('/dashboard')->assertOk()->getContent();

        foreach (self::ENGLISH_LEAKS as $phrase) {
            $this->assertStringNotContainsString($phrase, $html,
                "the dashboard showed English \"{$phrase}\" to a Swahili user");
        }
    }

    public function test_it_still_reads_correctly_in_english(): void
    {
        $staff = $this->staff();

        $html = $this->actingAs($staff)->withSession(['locale' => 'en'])
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Your Requests', $html);
        $this->assertStringNotContainsString('Maombi', $html, 'Swahili leaked into the English dashboard');
    }

    public function test_requests_are_not_labelled_with_a_word_that_also_means_prayers(): void
    {
        $staff = $this->staff();
        TravelRequest::factory()->create([
            'requester_id' => $staff->id,
            'unit_id' => $staff->unit_id,
            'status' => TravelRequest::STATUS_DRAFT,
        ]);
        foreach (['/dashboard', '/travel-requests'] as $url) {
            $html = $this->actingAs($staff)->withSession(['locale' => 'sw'])
                ->get($url)->assertOk()->getContent();

            // "Maombi Yangu" on its own reads as "my prayers"; it must always
            // carry the context that makes it a travel application.
            $this->assertStringNotContainsString('Maombi Yangu<', $html,
                "{$url} labelled requests ambiguously");
            $this->assertStringContainsString('Maombi Yangu ya Safari', $html,
                "{$url} lost the disambiguated label");
        }
    }
}
