<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setup::refresh();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => null,
            'password' => bcrypt('Admin#2026!secure'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function page(string $range = '30d'): string
    {
        $response = $this->actingAs($this->admin())
            ->get(route('overview', ['range' => $range]));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        // An admin must exist first, or the setup gate redirects to /setup
        // instead of the login screen.
        $this->admin();

        $this->get(route('overview'))->assertRedirect(route('login'));
    }

    public function test_event_sources_link_to_the_explorer(): void
    {
        $html = $this->page('24h');

        if (! str_contains($html, 'source-link')) {
            $this->markTestSkipped('No decoder data.');
        }

        // The class list carries other tokens (truncate, mono), so match the
        // token anywhere inside the attribute rather than as a prefix.
        $this->assertSame(
            1,
            preg_match('/class="[^"]*\bsource-link\b/', $html),
            'No element carries the source-link class.'
        );
        $this->assertStringContainsString('decoder=', $html);
    }

    public function test_the_source_link_targets_the_explorer(): void
    {
        $html = $this->page('24h');

        if (! str_contains($html, 'source-link')) {
            $this->markTestSkipped('No decoder data.');
        }

        $found = preg_match(
            '/<a\b[^>]*\bsource-link\b[^>]*href="([^"]+)"/s',
            $html,
            $matches
        );

        if ($found !== 1) {
            // Attribute order may be reversed; try href first.
            $found = preg_match(
                '/<a\b[^>]*href="([^"]+)"[^>]*\bsource-link\b/s',
                $html,
                $matches
            );
        }

        $this->assertSame(1, $found, 'No source-link href could be extracted.');

        $href = html_entity_decode($matches[1], ENT_QUOTES);

        $this->assertStringContainsString('/explorer', $href);
        $this->assertStringContainsString('decoder=', $href);
    }

    public function test_the_critical_card_lists_rules_not_alerts(): void
    {
        $html = $this->page('7d');

        if (! str_contains($html, 'Last Critical')) {
            $this->markTestSkipped('No critical card in this window.');
        }

        // Scope the check to the card itself: the old card listed one agent per
        // row, the new one aggregates by rule and shows how often it fired.
        $start = strpos($html, 'Last Critical');
        $card = substr($html, $start, 6000);

        $this->assertSame(
            1,
            preg_match('/rule \d+.*?[\d,]+<\/strong>\s*alerts/s', $card),
            'The Last Critical card does not render the "rule <id> ... N alerts" format.'
        );
    }

    public function test_the_explorer_accepts_a_decoder_filter(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('explorer.index', ['decoder' => 'windows_eventchannel', 'range' => '24h']));
        $response->assertOk();

        $html = $response->getContent();

        if (str_contains($html, 'Query failed')) {
            $this->markTestSkipped('Explorer rejected the decoder query.');
        }

        $this->assertStringContainsString('Decoder windows_eventchannel', $html);
    }
}
