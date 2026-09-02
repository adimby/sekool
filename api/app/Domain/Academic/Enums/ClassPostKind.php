<?php

namespace App\Domain\Academic\Enums;

enum ClassPostKind: string
{
    case Homework = 'homework';
    case Journal = 'journal';

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'Devoir',
            self::Journal => 'Cahier journal',
        };
    }

    /**
     * @return list<string>
     */
    public function familyChannels(): array
    {
        return match ($this) {
            self::Homework => ['in_app', 'print'],
            self::Journal => ['in_app'],
        };
    }

    public function familyTemplate(): string
    {
        return match ($this) {
            self::Homework => 'homework_published',
            self::Journal => 'journal_published',
        };
    }
}
