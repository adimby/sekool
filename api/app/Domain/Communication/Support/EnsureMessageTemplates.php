<?php

namespace App\Domain\Communication\Support;

use App\Domain\Communication\Models\MessageTemplate;

final class EnsureMessageTemplates
{
    public static function forKeys(string $schoolId, string ...$keys): void
    {
        foreach (MessageCatalog::defaults() as $template) {
            if ($keys !== [] && ! in_array($template['key'], $keys, true)) {
                continue;
            }

            MessageTemplate::query()->firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'key' => $template['key'],
                    'channel' => $template['channel'],
                    'locale' => $template['locale'],
                ],
                [
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'version' => 1,
                ],
            );
        }
    }
}
