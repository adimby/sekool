<?php

namespace App\Domain\Platform\Tenancy;

use Illuminate\Support\Facades\Schema;

trait HasReadyTable
{
    public static function tableReady(): bool
    {
        return Schema::hasTable((new static)->getTable());
    }
}
