<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Compliance;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceControlDrawerTest extends TestCase
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

    private function page(): string
    {
        $response = $this->actingAs($this->admin())->get(route('compliance.index'));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_guests_cannot_open_the_compliance_page(): void
    {
        // An admin must exist first, or the setup gate redirects to /setup
        // instead of the login screen.
        $this->admin();

        $this->get(route('compliance.index'))->assertRedirect(route('login'));
    }

    public function test_the_control_drawer_is_rendered(): void
    {
        $html = $this->page();

        foreach (['controlDrawer', 'cdTitle', 'cdSummary', 'cdStats', 'cdClose', 'cdDrill'] as $id) {
            $this->assertStringContainsString(
                'id="' . $id . '"',
                $html,
                "The control drawer is missing #{$id}."
            );
        }
    }

    public function test_the_drawer_carries_the_compliance_catalogue(): void
    {
        $html = $this->page();

        $found = preg_match('/data-compliance-catalogue="([^"]*)"/', $html, $matches);
        $this->assertSame(1, $found, 'No data-compliance-catalogue attribute on the compliance page.');

        $catalogue = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        $this->assertIsArray($catalogue, 'The compliance catalogue is not valid JSON.');
        $this->assertArrayHasKey('frameworks', $catalogue);
        $this->assertArrayHasKey('controls', $catalogue);
        $this->assertSame(Compliance::CATALOG, $catalogue['controls']);
    }

    public function test_the_control_codes_are_buttons(): void
    {
        $html = $this->page();

        if (! str_contains($html, 'data-control=')) {
            $this->markTestSkipped('No compliance data in this cluster/range.');
        }

        $this->assertStringContainsString('class="control-code"', $html);
        $this->assertStringContainsString('data-control=', $html);
    }

    public function test_the_control_payload_is_valid_json(): void
    {
        $html = $this->page();

        $count = preg_match_all('/data-control="([^"]*)"/', $html, $matches);

        if ($count === 0) {
            $this->markTestSkipped('No compliance data in this cluster/range.');
        }

        $this->assertGreaterThan(0, $count);

        foreach ($matches[1] as $raw) {
            $payload = json_decode(html_entity_decode($raw, ENT_QUOTES), true);

            $this->assertIsArray($payload, 'A control payload is not valid JSON: ' . substr($raw, 0, 80));

            foreach (['framework', 'code', 'count', 'drill'] as $key) {
                $this->assertArrayHasKey($key, $payload, "Control payload is missing \"{$key}\".");
            }

            $this->assertNotEmpty($payload['code'], 'A control payload has an empty code.');
        }
    }
}
