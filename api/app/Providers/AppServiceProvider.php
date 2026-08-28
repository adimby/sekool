<?php

namespace App\Providers;

use App\Domain\Certificate\Adapters\PlatformAttestationSigner;
use App\Domain\Certificate\Ports\DocumentSigner;
use App\Domain\Communication\Adapters\NullSmsGateway;
use App\Domain\Communication\Ports\SmsGateway;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, NullSmsGateway::class);
        $this->app->singleton(DocumentSigner::class, PlatformAttestationSigner::class);
    }

    public function boot(): void
    {
        RateLimiter::for('certificate-verify', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(MigrationsStarted::class, function (): void {
            TenantContext::activate(TenantContext::migrationBypass());
        });

        Event::listen(MigrationsEnded::class, function (): void {
            TenantContext::clear();
        });
    }
}
