<?php

namespace App\Domain\Collection\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class QuietHours
{
    public static function timezone(): string
    {
        return (string) config('fanabe.workflow.timezone', 'Indian/Antananarivo');
    }

    public static function isQuiet(?DateTimeInterface $at = null): bool
    {
        $now = CarbonImmutable::parse($at ?? 'now')->timezone(self::timezone());
        $hour = (int) $now->format('G');
        $start = (int) config('fanabe.workflow.quiet_hours_start', 20);
        $end = (int) config('fanabe.workflow.quiet_hours_end', 7);

        return $hour >= $start || $hour < $end;
    }

    public static function today(?DateTimeInterface $at = null): string
    {
        return CarbonImmutable::parse($at ?? 'now')->timezone(self::timezone())->toDateString();
    }
}
