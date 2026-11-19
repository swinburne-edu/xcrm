<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entry_point_is_the_login_page()
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_a_signed_in_user_is_sent_on_to_the_dashboard()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect(config('fortify.home'));
    }

    public function test_logging_out_returns_to_the_login_page()
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');
    }
}
