<?php

use App\Domain\School\Models\School;
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

    if (School::query()->exists()) {
        $this->info('Demo data already present — skipping seed.');

        return 0;
    }

    $this->info('Seeding demo schools and personas…');
    $this->call('db:seed', ['--force' => true]);

    return 0;
})->purpose('Extensions Postgres, migrations, et seed de démo si la base est vide');
