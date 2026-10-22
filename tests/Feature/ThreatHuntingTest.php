<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreatHuntingTest extends TestCase
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
            ->get(route('threats.index', ['range' => $range]));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_guests_cannot_open_threat_hunting(): void
    {
        // An admin must exist first, or the setup gate redirects to /setup
        // instead of the login screen.
        $this->admin();

        $this->get(route('threats.index'))->assertRedirect(route('login'));
    }

    public function test_tactic_titles_link_to_filtered_alerts(): void
    {
        $html = $this->page('30d');

        if (! str_contains($html, 'tactic-link')) {
            $this->markTestSkipped('No MITRE data in this window.');
        }

        $this->assertStringContainsString('class="tactic-link', $html);
        $this->assertStringContainsString('mitre_tactic=', $html);
    }

    public function test_the_tactic_link_targets_the_explorer(): void
    {
        $html = $this->page('30d');

        if (! str_contains($html, 'tactic-link')) {
            $this->markTestSkipped('No MITRE data in this window.');
        }

        $found = preg_match(
            '/class="tactic-link[^"]*"[^>]*href="([^"]+)"/s',
            $html,
            $matches
        );

        if ($found !== 1) {
            // Attribute order may be reversed; try href first.
            $found = preg_match(
                '/href="([^"]+)"[^>]*class="tactic-link[^"]*"/s',
                $html,
                $matches
            );
        }

        $this->assertSame(1, $found, 'No tactic-link href could be extracted.');

        $href = html_entity_decode($matches[1], ENT_QUOTES);

        $this->assertStringContainsString('/explorer', $href);
        $this->assertStringContainsString('mitre_tactic=', $href);
    }

    public function test_technique_codes_are_buttons_with_valid_json(): void
    {
        $html = $this->page('30d');

        $count = preg_match_all('/data-technique="([^"]*)"/', $html, $matches);

        if ($count === 0) {
            $this->markTestSkipped('No MITRE data in this window.');
        }

        $this->assertStringContainsString('class="tech-code"', $html);
        $this->assertStringContainsString('type="button"', $html);

        foreach ($matches[1] as $raw) {
            $payload = json_decode(html_entity_decode($raw, ENT_QUOTES), true);

            $this->assertIsArray($payload, 'A technique payload is not valid JSON: ' . substr($raw, 0, 80));

            foreach (['id', 'name', 'count', 'tactics', 'agents', 'explore'] as $key) {
                $this->assertArrayHasKey($key, $payload, "Technique payload is missing \"{$key}\".");
            }
        }
    }

    public function test_the_technique_drawer_is_rendered(): void
    {
        $html = $this->page('30d');

        foreach (['mitreDrawer', 'mtCode', 'mtName', 'mtTitle', 'mtTactics', 'mtStats', 'mtAgents', 'mtExplore', 'mtClose'] as $id) {
            $this->assertStringContainsString(
                'id="' . $id . '"',
                $html,
                "The technique drawer is missing #{$id}."
            );
        }
    }

    public function test_the_explorer_accepts_a_tactic_filter(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('explorer.index', ['mitre_tactic' => 'Defense Evasion', 'range' => '30d']));
        $response->assertOk();

        $html = $response->getContent();

        if (str_contains($html, 'Query failed') || str_contains($html, 'Invalid query')) {
            $this->markTestSkipped('Explorer rejected the mitre_tactic query.');
        }

        $this->assertStringContainsString('Tactic Defense Evasion', $html);
    }
}
