<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setup::refresh();
    }

    private function user(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($username),
            'username' => $username,
            'email' => null,
            'password' => bcrypt($username . '#2026!secure'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function report(User $author, string $status = 'open'): Ticket
    {
        return Ticket::create([
            'reference' => Ticket::nextReference(),
            'type' => 'incident',
            'title' => 'Brute force against dc1',
            'status' => $status,
            'priority' => 'high',
            'created_by' => $author->id,
        ]);
    }

    public function test_an_analyst_can_close_a_report(): void
    {
        $analyst = $this->user('analyst', 'analyst');
        $report = $this->report($analyst);

        $this->actingAs($analyst)
            ->post(route('reports.status', $report), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', ['id' => $report->id, 'status' => 'closed']);
        $this->assertNotNull($report->fresh()->closed_at);
    }

    public function test_a_status_change_is_written_to_the_timeline(): void
    {
        $analyst = $this->user('analyst', 'analyst');
        $report = $this->report($analyst);

        $this->actingAs($analyst)
            ->post(route('reports.status', $report), [
                'status' => 'closed',
                'note' => 'Handled with the endpoint owner.',
            ]);

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $report->id,
            'to_status' => 'closed',
            'note' => 'Handled with the endpoint owner.',
        ]);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $analyst = $this->user('analyst', 'analyst');
        $report = $this->report($analyst);

        $this->actingAs($analyst)
            ->post(route('reports.status', $report), ['status' => 'not_a_status'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('tickets', ['id' => $report->id, 'status' => 'open']);
    }

    public function test_a_viewer_cannot_change_a_report_status(): void
    {
        $analyst = $this->user('analyst', 'analyst');
        $viewer = $this->user('viewer', 'viewer');
        $report = $this->report($analyst);

        $this->actingAs($viewer)
            ->post(route('reports.status', $report), ['status' => 'closed'])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $report->id, 'status' => 'open']);
    }

    public function test_report_rows_are_clickable(): void
    {
        $admin = $this->user('admin', 'admin');
        $report = $this->report($admin);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('row-clickable', false)
            ->assertSee('data-href', false)
            ->assertSee(route('reports.show', $report), false)
            ->assertSee($report->reference);
    }
}
