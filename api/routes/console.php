<?php

use App\Domain\Platform\Demo\EnsureDemoAccounts;
use App\Domain\Platform\Demo\EnsureSchoolCore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('demo:bootstrap', function (): int {
    $this->info('Preparing PostgreSQL extensions…');
    foreach (['pg_trgm', 'pgcrypto'] as $extension) {
        try {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        } catch (Throwable $e) {
            $this->warn("Could not ensure extension {$extension}: ".$e->getMessage());
        }
    }

    $this->info('Running migrations…');
    $this->call('migrate', ['--force' => true]);

    try {
        $this->info('Seeding demo data…');
        $this->call('db:seed', ['--force' => true]);
    } catch (Throwable $e) {
        $this->warn('Seed incomplete: '.$e->getMessage());
    }

    $this->info('Ensuring demo login accounts…');
    app(EnsureDemoAccounts::class)->execute();

    $this->info('Ensuring school core (classes, barèmes)…');
    app(EnsureSchoolCore::class)->execute();

    return 0;
})->purpose('Extensions Postgres, migrations, et comptes de démo');
