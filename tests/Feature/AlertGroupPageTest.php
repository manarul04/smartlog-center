<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AlertStatus;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertGroupPageTest extends TestCase
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

    public function test_the_status_vocabulary_calls_an_unhandled_alert_untriaged(): void
    {
        $this->assertSame('Untriaged', AlertStatus::label(AlertStatus::NOT_REVIEWED));
        $this->assertSame('Untriaged', AlertStatus::label(null));
    }

    public function test_guests_cannot_open_the_alert_groups_page(): void
    {
        $this->admin();

        $this->get('/alert-groups')->assertRedirect(route('login'));
    }

    public function test_an_analyst_can_open_the_alert_groups_page(): void
    {
        $user = User::create([
            'name' => 'Analyst',
            'username' => 'analyst',
            'email' => null,
            'password' => bcrypt('Analyst#2026!secure'),
            'role' => 'analyst',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/alert-groups')->assertOk();
    }

    public function test_the_alert_groups_page_explains_levels_and_statuses(): void
    {
        $this->actingAs($this->admin())
            ->get('/alert-groups')
            ->assertOk()
            ->assertSee('data-help-open="alertHelp"', false)
            ->assertSee('Reading levels and statuses')
            ->assertSee('False Positive')
            ->assertSee('Counts as closed, leaves the queue');
    }

    public function test_a_viewer_cannot_create_a_report_from_a_group(): void
    {
        $viewer = User::create([
            'name' => 'Viewer',
            'username' => 'viewer',
            'email' => null,
            'password' => bcrypt('Viewer#2026!secure'),
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->actingAs($viewer)->post('/reports/from-group', [
            'agent' => 'dc1',
            'rule_id' => '60137',
            'range' => '24h',
            'priority' => 'high',
        ])->assertForbidden();
    }
}
