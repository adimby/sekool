<?php

namespace App\Domain\Communication\Support;

/**
 * Family-facing copy. Must never mention a score, risk level, or judgment.
 */
final class MessageCatalog
{
    /**
     * @return list<array{key: string, channel: string, locale: string, subject: string, body: string}>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'payment_overdue',
                'channel' => 'in_app',
                'locale' => 'fr',
                'subject' => 'Échéance d’écolage à régulariser',
                'body' => 'Bonjour, une échéance d’écolage pour {student_first_name} reste à régler. Merci de vous présenter à la caisse de l’école. FANABE n’encaisse rien en ligne.',
            ],
            [
                'key' => 'payment_overdue',
                'channel' => 'print',
                'locale' => 'fr',
                'subject' => 'Avis d’échéance — {school_name}',
                'body' => "À l’attention de la famille de {student_first_name} {student_last_name}.\n\nUne échéance d’écolage reste à régler. Merci de vous présenter à la caisse.\nMontant restant indiqué par l’école : {remaining_amount} Ar.\n\nCe document est à remettre en main propre. FANABE n’envoie pas de SMS.",
            ],
            [
                'key' => 'repeated_absence',
                'channel' => 'in_app',
                'locale' => 'fr',
                'subject' => 'Absences à l’école',
                'body' => 'Bonjour, {student_first_name} a été noté absent plusieurs fois récemment. Merci de prendre contact avec l’école si besoin.',
            ],
            [
                'key' => 'repeated_absence',
                'channel' => 'print',
                'locale' => 'fr',
                'subject' => 'Note à la famille — {school_name}',
                'body' => "À l’attention de la famille de {student_first_name} {student_last_name}.\n\nPlusieurs absences ont été enregistrées ces derniers jours. Merci de prendre contact avec l’école.\n\nDocument à remettre en main propre.",
            ],
            [
                'key' => 'missing_document',
                'channel' => 'in_app',
                'locale' => 'fr',
                'subject' => 'Document à remettre à l’école',
                'body' => 'Bonjour, un document manque encore au dossier de {student_first_name}. Merci de le déposer à l’accueil de l’école.',
            ],
            [
                'key' => 'missing_document',
                'channel' => 'print',
                'locale' => 'fr',
                'subject' => 'Document à remettre — {school_name}',
                'body' => "À l’attention de la famille de {student_first_name} {student_last_name}.\n\nUn document manque encore au dossier. Merci de le déposer à l’accueil.\n\nDocument à remettre en main propre.",
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function forbiddenFamilyTerms(): array
    {
        return [
            'risque',
            'critique',
            'score',
            'fiabilité',
            'reliability',
            'palier',
            'mauvais payeur',
            'élève en difficulté',
        ];
    }
}
