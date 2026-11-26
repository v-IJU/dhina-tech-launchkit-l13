<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

/**
 * Laravel 13 PreventRequestForgery is switched off in normal tests (runningUnitTests). Here we turn it
 * back on to prove the hardening: other sites (and sibling tenant subdomains) can't post, we can.
 */
class RequestForgeryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_cross_site_post_without_token_is_rejected(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post(route('login'), ['email' => 'a@b.c', 'password' => 'x'])
            ->assertStatus(419);
    }

    public function test_sibling_subdomain_post_is_rejected(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'same-site')
            ->post(route('login'), ['email' => 'a@b.c', 'password' => 'x'])
            ->assertStatus(419);
    }

    public function test_same_origin_post_passes_without_a_token(): void
    {
        $user = $this->superAdmin();

        $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post(route('login'), ['email' => $user->email, 'password' => 'Secret#123'])
            ->assertRedirect($this->admin());
    }

    public function test_old_browsers_still_work_with_the_token(): void
    {
        $user = $this->superAdmin();
        $this->get(route('login'));

        $this->post(route('login'), ['_token' => session()->token(), 'email' => $user->email, 'password' => 'Secret#123'])
            ->assertRedirect($this->admin());
    }

    public function test_payment_webhooks_are_excluded(): void
    {
        $this->assertContains('billing/webhooks/*', app(PreventRequestForgery::class)->getExcludedPaths());
    }
}
