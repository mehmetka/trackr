<?php

namespace App\enum;

enum ChainTypes: int
{
    case BOOLEAN = 1;
    case FLOAT = 2;
    case INTEGER = 3;

    public static function toArray(): array
    {
        return [
            self::BOOLEAN->value,
            self::FLOAT->value,
            self::INTEGER->value,
        ];
    }

}