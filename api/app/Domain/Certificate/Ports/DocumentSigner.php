<?php

namespace App\Domain\Certificate\Ports;

interface DocumentSigner
{
    public function keyId(): string;

    public function sign(string $artifactSha256): string;

    public function verify(string $artifactSha256, string $signature, string $keyId): bool;
}
