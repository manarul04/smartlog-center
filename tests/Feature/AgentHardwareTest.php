<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpenSearch\AgentRepository;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hardware panel reads syscollector inventory. These assertions need the
 * live cluster, so they skip when it is unreachable instead of failing the
 * suite on a laptop that is offline.
 */
class AgentHardwareTest extends TestCase
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

    private function firstAgent(): ?array
    {
        try {
            return collect(app(AgentRepository::class)->latestStatus())->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function test_hardware_mapping_is_empty_for_an_unknown_agent(): void
    {
        $hardware = app(AgentRepository::class)->hardware('agent-that-does-not-exist');

        $this->assertFalse($hardware['available']);
        $this->assertNull($hardware['cpu_name']);
        $this->assertNull($hardware['mem_total']);
    }

    public function test_the_agent_page_renders_the_hardware_panel(): void
    {
        $agent = $this->firstAgent();

        if ($agent === null) {
            $this->markTestSkipped('OpenSearch is not reachable.');
        }

        $hardware = app(AgentRepository::class)->hardware($agent['name']);

        if (! $hardware['available']) {
            $this->markTestSkipped('No hardware inventory for '.$agent['name'].'.');
        }

        $this->actingAs($this->admin())
            ->get('/agents/'.$agent['id'])
            ->assertOk()
            ->assertSee('Hardware')
            ->assertSee('Host Name')
            ->assertSee('Operating System')
            ->assertSee($hardware['cpu_name'])
            ->assertSee((string) $hardware['cpu_cores']);
    }

    public function test_the_system_profile_reports_hostname_and_platform(): void
    {
        $agent = $this->firstAgent();

        if ($agent === null) {
            $this->markTestSkipped('OpenSearch is not reachable.');
        }

        $profile = app(AgentRepository::class)->systemProfile($agent['name']);

        if ($profile['hostname'] === null) {
            $this->markTestSkipped('No system inventory for '.$agent['name'].'.');
        }

        $this->assertSame($profile['hostname'], $agent['name']);
        $this->assertNotNull($profile['os_platform']);
    }
}
