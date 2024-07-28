<?php

namespace App\util;

use App\enum\ChainTypes;

class ValidatorUtil
{

    public static function validateLinkBooleanType($chainType, $value): bool
    {
        return $chainType === ChainTypes::BOOLEAN->value && ((int)$value === 1 || (int)$value === 0);
    }

    public static function validateLinkIntegerType($chainType, $value): bool
    {
        return $chainType === ChainTypes::INTEGER->value && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function validateLinkFloatType($chainType, $value): bool
    {
        return $chainType === ChainTypes::FLOAT->value && filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    public static function validateLinkValueByType($chainType, $value): bool
    {
        return self::validateLinkBooleanType($chainType, $value) ||
            self::validateLinkIntegerType($chainType, $value) ||
            self::validateLinkFloatType($chainType, $value);
    }
}