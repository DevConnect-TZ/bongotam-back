<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectionAccessPageTest extends TestCase
{
    public function test_the_connection_dashboard_redirects_to_the_admin_login_helper_when_not_authenticated(): void
    {
        $this->get('/connection')
            ->assertRedirect('/connection/login');
    }

    public function test_the_connection_login_helper_page_renders(): void
    {
        $this->get('/connection/login')
            ->assertOk()
            ->assertSee('Sign in to analytics.')
            ->assertSee('Enter your admin email and we will send a login code.')
            ->assertSee('Send Code');
    }
}
