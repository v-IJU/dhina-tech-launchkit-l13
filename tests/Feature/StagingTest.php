<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Laravel\Horizon\ProvisioningPlan;
use Tests\TestCase;

/**
 * Staging = same code and compose file as production, but private, not indexed and never emailing customers.
 */
class StagingTest extends TestCase
{
    public function test_horizon_runs_both_supervisors_on_staging(): void
    {
        $this->assertArrayHasKey('staging', config('horizon.environments'));

        $plan = (new ProvisioningPlan('test', config('horizon.environments'), config('horizon.defaults')))->toSupervisorOptions();
        $this->assertSame(['supervisor-1', 'supervisor-imports'], $plan['staging']->keys()->all());
        $this->assertSame(['supervisor-1', 'supervisor-imports'], $plan['production']->keys()->all());
    }

    public function test_password_lock(): void
    {
        config(['starter.staging_basic_auth' => 'client:Secret-123']);

        $this->get('/')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->get('/', ['PHP_AUTH_USER' => 'client', 'PHP_AUTH_PW' => 'wrong'])->assertUnauthorized();
        $this->withServerVariables(['PHP_AUTH_USER' => 'client', 'PHP_AUTH_PW' => 'Secret-123'])->get('/')->assertOk();

        // health check for the load balancer stays open
        $this->withServerVariables([])->get('/up')->assertOk();
    }

    public function test_no_lock_when_empty(): void
    {
        config(['starter.staging_basic_auth' => '']);
        $this->get('/')->assertOk();
    }

    public function test_not_indexed_outside_production(): void
    {
        $this->app['env'] = 'staging';
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertSee('Disallow: /')->assertDontSee('Sitemap');

        $this->app['env'] = 'production';
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/robots.txt')->assertSee('Sitemap');
    }

    public function test_all_mail_goes_to_the_test_inbox(): void
    {
        config(['mail.default' => 'array', 'mail.always_to' => 'qa@agency.test']);

        Mail::raw('Your order', function ($m) {
            $m->to('real.customer@gmail.com')->cc('boss@client.com')->bcc('audit@client.com')->subject('Order #12');
        });

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame(['qa@agency.test'], array_map(fn ($a) => $a->getAddress(), $sent->getTo()));
        $this->assertSame([], $sent->getCc());
        $this->assertSame([], $sent->getBcc());
        $this->assertSame('[STAGING] Order #12', $sent->getSubject());
        $this->assertStringContainsString('real.customer@gmail.com', $sent->getHeaders()->get('X-Original-To')->getBodyAsString());
    }

    public function test_mail_untouched_when_not_set(): void
    {
        config(['mail.default' => 'array', 'mail.always_to' => null]);
        Mail::raw('Hi', fn ($m) => $m->to('real.customer@gmail.com')->subject('Hello'));

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('real.customer@gmail.com', $sent->getTo()[0]->getAddress());
        $this->assertSame('Hello', $sent->getSubject());
    }
}
