<?php

namespace App\Providers;

use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(MigrationsStarted::class, function (): void {
            TenantContext::activate(TenantContext::migrationBypass());
        });

        Event::listen(MigrationsEnded::class, function (): void {
            TenantContext::clear();
        });
    }
}
