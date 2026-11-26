<?php

declare(strict_types=1);

namespace App\Modules\Saas\Database\Seeders;

use App\Modules\Saas\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Three starting plans (SaaS mode, central database). Edit them under SaaS > Plans;
 * this seeder never overwrites a plan that already exists.
 *
 * Prices in paise: 99900 = ₹999/month. Yearly = 10 × monthly (2 months free).
 * Core modules (Dashboard, Users, Roles, Settings, Menus, Activity, Billing) are always included.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $website = ['pages', 'media', 'inbox', 'faqs', 'services', 'sliders', 'testimonials'];

        $plans = [
            [
                'slug' => 'starter', 'name' => 'Starter', 'description' => 'A professional website you manage yourself.',
                'price_monthly' => 99900, 'price_yearly' => 999000, 'trial_days' => 14, 'sort_order' => 1,
                'modules' => $website,
                'limits' => ['users' => 3, 'customers' => null, 'storage_mb' => 1024],
                'features' => ['Website with page builder', 'FAQs, services, sliders, testimonials', 'Contact inbox', '3 staff users', '1 GB storage', 'Email support'],
            ],
            [
                'slug' => 'business', 'name' => 'Business', 'description' => 'Website, blog and customer accounts with a mobile app API.',
                'price_monthly' => 249900, 'price_yearly' => 2499000, 'trial_days' => 14, 'sort_order' => 2, 'is_featured' => true,
                'modules' => [...$website, 'blog', 'customers', 'chat'],
                'limits' => ['users' => 10, 'customers' => 5000, 'storage_mb' => 5120],
                'features' => ['Everything in Starter', 'Blog', 'Customer accounts + app API', 'Team chat', '10 staff users', '5,000 customers', '5 GB storage', 'Priority support'],
            ],
            [
                'slug' => 'enterprise', 'name' => 'Enterprise', 'description' => 'Everything, without limits.',
                'price_monthly' => 699900, 'price_yearly' => 6999000, 'trial_days' => 30, 'sort_order' => 3,
                'modules' => [...$website, 'blog', 'customers', 'chat', 'ai'],
                'limits' => ['users' => null, 'customers' => null, 'storage_mb' => null, 'ai_tokens' => 1000000],
                'features' => ['Everything in Business', 'AI assistant + connect Claude/ChatGPT (MCP)', 'Unlimited users and customers', 'Unlimited storage', 'Own domain', 'Dedicated support'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->firstOrCreate(['slug' => $plan['slug']], $plan + ['currency' => config('starter.tenancy.currency', 'INR')]);
        }
    }
}
