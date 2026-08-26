<?php

namespace App\Domain\Communication\Support;

use App\Domain\Platform\Exceptions\DomainException;

final class MessageRenderer
{
    /**
     * @param  array<string, scalar|null>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($template, $replace);
    }

    public static function assertFamilySafe(string ...$parts): void
    {
        $haystack = mb_strtolower(implode("\n", $parts));

        foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
            if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                throw new DomainException('Le message destiné à une famille contient un terme interdit.');
            }
        }
    }
}
