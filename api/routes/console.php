<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Platform\Demo\EnsureCollection;
use App\Domain\Platform\Demo\EnsureDemoAccounts;
use App\Domain\Platform\Demo\EnsurePhases567;
use App\Domain\Platform\Demo\EnsureSchoolCore;
use App\Domain\Platform\Demo\RunDemoMigrations;
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
    app(RunDemoMigrations::class)->execute(
        fn (string $line) => $this->info($line),
        fn (string $line) => $this->error($line),
    );

    try {
        $this->info('Seeding demo data…');
        $this->call('db:seed', ['--force' => true]);
    } catch (Throwable $e) {
        $this->warn('Seed incomplete: '.$e->getMessage());
    }

    $steps = [
        [EnsureDemoAccounts::class, 'Ensuring demo login accounts…'],
        [EnsureSchoolCore::class, 'Ensuring school core (classes, barèmes)…'],
        [EnsureCollection::class, 'Ensuring collection intelligence (risque, file, cockpit)…'],
        [EnsurePhases567::class, 'Ensuring documents, kits et notes (phases 5–7)…'],
    ];

    foreach ($steps as [$class, $label]) {
        $this->info($label);
        try {
            app($class)->execute();
        } catch (Throwable $e) {
            $this->error($label.' failed: '.$e->getMessage());
        }
    }

    return 0;
})->purpose('Extensions Postgres, migrations, et comptes de démo');

Artisan::command('collection:recompute {--school=} {--live}', function (): int {
    $school = $this->option('school');
    $live = (bool) $this->option('live');
    app(RecomputeCollection::class)->execute(is_string($school) && $school !== '' ? $school : null, $live);
    $this->info('Recouvrement recalculé.');

    return 0;
})->purpose('Recalcule risque, prévision, workflows et fiabilité familiale');
