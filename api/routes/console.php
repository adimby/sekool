<?php

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    $demoEmail = 'direction.antsahabe@fanabe.test';

    $hasDemoUser = TenantContext::runWithRlsBypass(
        fn (): bool => UserAccount::query()->where('email', $demoEmail)->exists(),
    );

    if (! $hasDemoUser) {
        $this->info('Seeding demo schools and personas…');
        $this->call('db:seed', ['--force' => true]);
    } else {
        $this->info('Demo data already present — ensuring known passwords.');
    }

    TenantContext::runWithRlsBypass(function (): void {
        $emails = [
            'direction.antsahabe@fanabe.test',
            'direction.ambohipo@fanabe.test',
            'direction.itaosy@fanabe.test',
            'parent.andry@fanabe.test',
            'parent.d@fanabe.test',
        ];

        foreach ($emails as $email) {
            $account = UserAccount::query()->where('email', $email)->first();
            if ($account === null) {
                continue;
            }
            if (! Hash::check('password', $account->password)) {
                $account->forceFill(['password' => 'password'])->save();
            }
        }
    });

    return 0;
})->purpose('Extensions Postgres, migrations, et seed de démo si la base est vide');
