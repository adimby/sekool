<?php

namespace App\Domain\Communication\Adapters;

use App\Domain\Communication\Ports\SmsGateway;

final class NullSmsGateway implements SmsGateway
{
    public function send(string $toE164, string $body): array
    {
        return [
            'sent' => false,
            'provider_reference' => null,
            'error' => 'sms_disabled',
        ];
    }
}
