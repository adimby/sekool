<?php

use App\Domain\Communication\Support\MessageCatalog;

it('never puts a score or risk level in a family-facing template', function () {
    foreach (MessageCatalog::defaults() as $template) {
        $haystack = mb_strtolower($template['subject']."\n".$template['body']);
        foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
            expect($haystack)->not->toContain(mb_strtolower($term));
        }
    }
});
