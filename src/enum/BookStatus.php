<?php

namespace App\enum;

enum BookStatus: int
{
    case NEW = 0;
    case STARTED = 1;
    case DONE = 2;
    case ABANDONED = 3;
    case PRIORITIZED = 4;

    public static function toArray(): array
    {
        return [
            self::NEW->value,
            self::STARTED->value,
            self::DONE->value,
            self::ABANDONED->value,
            self::PRIORITIZED->value,
        ];
    }
}