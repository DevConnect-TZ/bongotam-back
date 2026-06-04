<?php

namespace Tests\Feature;

use Tests\TestCase;

class StatusPageTest extends TestCase
{
    public function test_the_main_backend_page_shows_the_api_uptime_message(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Hoora! The API is up.')
            ->assertSee('API Uptime')
            ->assertSee('/api/uptime');
    }

    public function test_the_api_uptime_endpoint_returns_status_payload(): void
    {
        $response = $this->getJson('/api/uptime');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'up',
                'status_label' => 'UP',
                'message' => 'Hoora! The API is up.',
            ])
            ->assertJsonStructure([
                'status',
                'status_label',
                'message',
                'uptime_seconds',
                'uptime_human',
                'started_at',
                'started_at_human',
                'checked_at',
                'checked_at_human',
            ]);
    }
}
