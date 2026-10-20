<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_setup_wizard_on_a_fresh_install(): void
    {
        config(['opensearch.host' => '']);
        \App\Support\Setup::refresh();

        $this->get('/')->assertRedirect(route('setup'));
    }
}
