<?php

namespace App\Domain\Platform\Demo;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Production demo volumes often predate later migrations. A single failed
 * `migrate` must not block independent later files (EDT, substitutions, appel).
 */
final class RunDemoMigrations
{
    /**
     * @param  callable(string): void  $info
     * @param  callable(string): void  $error
     */
    public function execute(callable $info, callable $error): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            if ($output !== '') {
                $info($output);
            }
        } catch (Throwable $e) {
            $error('migrate: '.$e->getMessage());
        }

        if (! $this->hasPending()) {
            return;
        }

        $error('Certaines migrations restent en attente — reprise une par une.');
        $this->retryPending($info, $error);
    }

    /**
     * @param  callable(string): void  $info
     * @param  callable(string): void  $error
     */
    private function retryPending(callable $info, callable $error): void
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($this->alreadyRan($name)) {
                continue;
            }

            try {
                $code = Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => 'database/migrations/'.basename($file),
                ]);
                if ($code !== 0) {
                    $error($name.': '.trim(Artisan::output()));

                    continue;
                }
                $info($name.' OK');
            } catch (Throwable $e) {
                $error($name.': '.$e->getMessage());
            }
        }
    }

    private function hasPending(): bool
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        foreach ($files as $file) {
            if (! $this->alreadyRan(basename($file, '.php'))) {
                return true;
            }
        }

        return false;
    }

    private function alreadyRan(string $name): bool
    {
        try {
            return DB::table('migrations')->where('migration', $name)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
