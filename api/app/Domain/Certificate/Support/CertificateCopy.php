<?php

namespace App\Domain\Certificate\Support;

final class CertificateCopy
{
    public const DISCLAIMER = 'Attestation de plateforme FANABE. Ne constitue pas une signature électronique qualifiée au sens de la loi n° 2014-025.';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function renderHtml(array $snapshot): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return implode("\n", [
            '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Certificat FANABE</title></head><body>',
            '<h1>FANABE</h1>',
            '<p>'.$esc((string) ($snapshot['type_label'] ?? 'Certificat de scolarité')).'</p>',
            '<p>'.$esc((string) ($snapshot['school_name'] ?? '')).'</p>',
            '<p>'.$esc((string) ($snapshot['year_label'] ?? '')).' · '.$esc((string) ($snapshot['classroom_name'] ?? '')).'</p>',
            '<p>'.$esc((string) ($snapshot['full_name'] ?? '')).'</p>',
            isset($snapshot['ended_on']) && $snapshot['ended_on'] !== '' && $snapshot['ended_on'] !== null
                ? '<p>Fin d’inscription le '.$esc((string) $snapshot['ended_on']).'</p>'
                : '',
            isset($snapshot['exit_reason']) && $snapshot['exit_reason'] !== '' && $snapshot['exit_reason'] !== null
                ? '<p>Motif : '.$esc((string) $snapshot['exit_reason']).'</p>'
                : '',
            '<p>Référence '.$esc((string) ($snapshot['public_reference'] ?? '')).'</p>',
            '<p>Émis le '.$esc((string) ($snapshot['issued_on'] ?? '')).'</p>',
            '<p>'.$esc(self::DISCLAIMER).'</p>',
            '</body></html>',
        ]);
    }

    public static function maskedName(string $first, string $last): string
    {
        $initial = mb_substr(trim($last), 0, 1);

        return trim($first).($initial === '' ? '' : ' '.$initial.'.');
    }
}
