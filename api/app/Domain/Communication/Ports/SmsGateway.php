<?php

namespace App\Domain\Communication\Ports;

interface SmsGateway
{
    /**
     * @return array{sent: bool, provider_reference: ?string, error: ?string}
     */
    public function send(string $toE164, string $body): array;
}
