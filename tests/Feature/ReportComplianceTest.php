<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Services\OpenSearch\AlertRepository;
use App\Support\Compliance;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportComplianceTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function report(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'reference' => Ticket::nextReference(),
            'type' => 'incident',
            'title' => 'Grouped incident',
            'status' => 'open',
            'priority' => 'high',
        ], $overrides));
    }

    public function test_the_compliance_section_is_hidden_when_the_report_has_no_group(): void
    {
        $report = $this->report();

        $this->assertFalse($report->isGrouped());

        $response = $this->actingAs($this->admin())->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('reportComplianceDetail', false);
        $response->assertDontSee('reportCompliance"', false);
    }

    public function test_a_grouped_report_renders_its_compliance_controls(): void
    {
        $report = $this->report([
            'group_rule_id' => '60205',
            'group_agent' => 'dc1',
            'group_agents' => ['dc1'],
            'group_window_from' => now()->subDay(),
        ]);

        try {
            $compliance = app(AlertRepository::class)->ruleCompliance(
                ['dc1'],
                '60205',
                now()->subDay()->toIso8601String()
            );
        } catch (\Throwable) {
            $this->markTestSkipped('OpenSearch is not reachable.');
        }

        if ($compliance === []) {
            $this->markTestSkipped('No compliance mapping on rule 60205 in this cluster.');
        }

        $response = $this->actingAs($this->admin())->get(route('reports.show', $report));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('reportComplianceDetail', $html);
        $this->assertStringContainsString('compliance-chip', $html);

        // Every framework the repository returned must reach the page, using the
        // catalogue key rather than the config label (TSC, not "TSC (SOC 2)").
        foreach ($compliance as $framework => $codes) {
            $this->assertStringContainsString(
                'data-framework="' . $framework . '"',
                $html,
                "Framework {$framework} is missing from the compliance card."
            );

            foreach ($codes as $code) {
                $this->assertStringContainsString(
                    'data-code="' . $code . '"',
                    $html,
                    "Control {$framework} {$code} is missing from the compliance card."
                );
            }
        }
    }

    public function test_the_compliance_catalogue_attribute_is_valid_json(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->admin())->get(route('reports.show', $report));
        $response->assertOk();

        $html = $response->getContent();

        // The catalogue is embedded by the drawer partial on every page, so this
        // attribute exists even for an ungrouped report.
        $count = preg_match('/data-compliance-catalogue="([^"]*)"/', $html, $matches);
        $this->assertSame(1, $count, 'No data-compliance-catalogue attribute found.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES);
        $catalogue = json_decode($decoded, true);

        $this->assertIsArray($catalogue, 'The catalogue attribute is not valid JSON.');
        $this->assertArrayHasKey('frameworks', $catalogue);
        $this->assertArrayHasKey('controls', $catalogue);
        $this->assertSame(Compliance::CATALOG, $catalogue['controls']);
    }

    public function test_rule_compliance_returns_an_empty_array_for_an_unknown_rule(): void
    {
        try {
            $result = app(AlertRepository::class)->ruleCompliance(['dc1'], '999999999', 'now-30d');
        } catch (\Throwable) {
            $this->markTestSkipped('OpenSearch is not reachable.');
        }

        $this->assertSame([], $result);
    }
}
