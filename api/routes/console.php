<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Platform\Demo\EnsureCollection;
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

    $this->info('Ensuring collection intelligence (risque, file, cockpit)…');
    app(EnsureCollection::class)->execute();

    return 0;
})->purpose('Extensions Postgres, migrations, et comptes de démo');

Artisan::command('collection:recompute {--school=} {--live}', function (): int {
    $school = $this->option('school');
    $live = (bool) $this->option('live');
    app(RecomputeCollection::class)->execute(is_string($school) && $school !== '' ? $school : null, $live);
    $this->info('Recouvrement recalculé.');

    return 0;
})->purpose('Recalcule risque, prévision, workflows et fiabilité familiale');
