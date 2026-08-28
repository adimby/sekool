<?php

namespace App\Domain\Certificate\Adapters;

use App\Domain\Certificate\Ports\DocumentSigner;
use RuntimeException;

final class PlatformAttestationSigner implements DocumentSigner
{
    public const KEY_ID = 'ed25519-v1';

    public function keyId(): string
    {
        return self::KEY_ID;
    }

    public function sign(string $artifactSha256): string
    {
        return sodium_bin2base64(
            sodium_crypto_sign_detached($artifactSha256, $this->secretKey()),
            SODIUM_BASE64_VARIANT_ORIGINAL,
        );
    }

    public function verify(string $artifactSha256, string $signature, string $keyId): bool
    {
        if ($keyId !== self::KEY_ID) {
            return false;
        }

        try {
            $raw = sodium_base642bin($signature, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\Throwable) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($raw, $artifactSha256, $this->publicKey());
    }

    private function secretKey(): string
    {
        return sodium_crypto_sign_secretkey($this->keypair());
    }

    private function publicKey(): string
    {
        return sodium_crypto_sign_publickey($this->keypair());
    }

    private function keypair(): string
    {
        $seed = substr(hash('sha256', (string) config('app.key').'|fanabe-cert-v1', true), 0, SODIUM_CRYPTO_SIGN_SEEDBYTES);
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new RuntimeException('Impossible de dériver la clé d’attestation FANABE.');
        }

        return sodium_crypto_sign_seed_keypair($seed);
    }
}
