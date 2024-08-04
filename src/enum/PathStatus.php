<?php

namespace App\enum;

enum PathStatus: int
{
    case ACTIVE = 0;
    case DONE = 1;

    public static function toArray(): array
    {
        return [
            self::ACTIVE->value,
            self::DONE->value,
        ];
    }
}