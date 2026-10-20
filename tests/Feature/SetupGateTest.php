<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupGateTest extends TestCase
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

    public function test_a_fresh_install_redirects_every_page_to_setup(): void
    {
        config(['opensearch.host' => '']);
        Setup::refresh();

        $this->get('/')->assertRedirect(route('setup'));
        $this->get('/explorer')->assertRedirect(route('setup'));
        $this->get('/setup')->assertOk();
    }

    public function test_the_setup_wizard_closes_once_a_backend_and_an_admin_exist(): void
    {
        config(['opensearch.host' => 'https://indexer.local']);
        $this->admin();
        Setup::refresh();

        $this->get('/setup')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }

    public function test_setup_is_locked_for_anonymous_visitors_when_accounts_exist(): void
    {
        $this->admin();
        config(['opensearch.host' => '']);
        Setup::refresh();

        $this->get('/setup')->assertRedirect(route('login'));
    }

    public function test_login_stays_reachable_when_only_the_backend_is_missing(): void
    {
        $this->admin();
        config(['opensearch.host' => '']);
        Setup::refresh();

        $this->get('/login')->assertOk();
    }

    public function test_the_wizard_creates_the_first_administrator(): void
    {
        config(['opensearch.host' => '']);
        Setup::refresh();

        $this->post('/setup/account', [
            'name' => 'First Admin',
            'username' => 'firstadmin',
            'email' => '',
            'password' => 'Setup#2026!pass',
            'password_confirmation' => 'Setup#2026!pass',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'username' => 'firstadmin',
            'role' => 'admin',
            'email' => null,
        ]);
    }

    public function test_the_wizard_refuses_to_create_a_second_administrator(): void
    {
        $this->admin();
        config(['opensearch.host' => '']);
        Setup::refresh();

        $this->post('/setup/account', [
            'name' => 'Duplicate',
            'username' => 'second',
            'password' => 'Setup#2026!pass',
            'password_confirmation' => 'Setup#2026!pass',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['username' => 'second']);
    }
}
