<?php

use App\Core\Tenancy\Tenancy;
use Illuminate\Support\Facades\Broadcast;

// Live notifications for a signed-in staff user.
// SaaS mode: "tenant.{tenant}.App.Models.User.{id}", so user 1 of tenant A and user 1 of tenant B
// never share a channel. The user must belong to the tenant the request is for.
Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => ! Tenancy::inTenant() && (int) $user->id === (int) $id);

Broadcast::channel('tenant.{tenant}.App.Models.User.{id}', fn ($user, $tenant, $id) => Tenancy::tenant()?->getTenantKey() === $tenant && (int) $user->id === (int) $id);
