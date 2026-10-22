<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SCA table renders every check code as a button that opens a read-only
 * detail drawer. Needs the live cluster for real rows, so it skips otherwise.
 */
class ScaCheckDrawerTest extends TestCase
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

    private function scaPage(): string
    {
        $agent = null;

        try {
            $agent = collect(app(\App\Services\OpenSearch\AgentRepository::class)->latestStatus())->first();
        } catch (\Throwable) {
            $this->markTestSkipped('OpenSearch is not reachable.');
        }

        if ($agent === null) {
            $this->markTestSkipped('No agents in the monitoring window.');
        }

        $response = $this->actingAs($this->admin())->get('/agents/' . $agent['id'] . '/sca');
        $response->assertOk();

        return $response->getContent();
    }

    public function test_the_check_drawer_is_rendered(): void
    {
        $html = $this->scaPage();

        foreach (['scaCheckDrawer', 'sccResult', 'sccId', 'sccTitle', 'sccFacts', 'sccDescription', 'sccClose'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, "The check drawer is missing #{$id}.");
        }
    }

    public function test_check_codes_are_buttons(): void
    {
        $html = $this->scaPage();

        if (! str_contains($html, 'data-check=')) {
            $this->markTestSkipped('No SCA checks for this agent.');
        }

        $this->assertStringContainsString('class="check-code"', $html);
        $this->assertStringContainsString('data-check=', $html);
    }

    public function test_the_check_payload_is_valid_json(): void
    {
        $html = $this->scaPage();

        $count = preg_match_all('/data-check="([^"]*)"/', $html, $matches);

        if ($count === 0) {
            $this->markTestSkipped('No SCA checks for this agent.');
        }

        $this->assertGreaterThan(0, $count);

        foreach ($matches[1] as $raw) {
            $payload = json_decode(html_entity_decode($raw, ENT_QUOTES), true);

            $this->assertIsArray($payload, 'An SCA payload is not valid JSON: ' . substr($raw, 0, 80));

            foreach (['check_id', 'title', 'result', 'description'] as $key) {
                $this->assertArrayHasKey($key, $payload, "SCA payload is missing \"{$key}\".");
            }

            $this->assertNotEmpty($payload['check_id'], 'An SCA payload has an empty check_id.');
        }
    }

    public function test_passed_checks_also_get_a_code_button(): void
    {
        $html = $this->scaPage();

        if (! str_contains($html, 'badge success')) {
            $this->markTestSkipped('No passed checks in this window.');
        }

        // Split into rows so the assertion is per-row: a passed row keeps its
        // Handle button suppressed, but its check code must stay clickable.
        $rows = preg_split('/<tr\b/i', $html);
        $passedRows = 0;
        $passedWithCode = 0;

        foreach ($rows as $row) {
            if (! str_contains($row, 'badge success')) {
                continue;
            }

            $passedRows++;

            if (str_contains($row, 'class="check-code"') && str_contains($row, 'data-check=')) {
                $passedWithCode++;
            }
        }

        $this->assertGreaterThan(0, $passedRows, 'Found "badge success" but no complete row.');

        $this->assertSame(
            $passedRows,
            $passedWithCode,
            "Only {$passedWithCode} of {$passedRows} passed rows have a clickable check code."
        );
    }
}
