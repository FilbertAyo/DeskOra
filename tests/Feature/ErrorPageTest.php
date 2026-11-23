<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_render_the_react_error_page(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page
            ->component('Errors/Show')
            ->where('status', 404)
            ->where('title', 'Page not found'));
        $response->assertDontSee('ti ti-home', false);
    }

    public function test_explicit_error_route_renders_the_react_page(): void
    {
        $this->get('/error/500')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 500)
                ->where('title', 'Something went wrong'));
    }
}
