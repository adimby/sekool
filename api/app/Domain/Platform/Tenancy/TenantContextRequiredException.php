<?php

namespace App\Domain\Platform\Tenancy;

use RuntimeException;

final class TenantContextRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant context is required. Refusing to proceed without a school scope.');
    }
}
