<?php

namespace App\util;

use ForceUTF8\Encoding;

class EncodingUtil
{
    static function isLatin1($str)
    {
        return (preg_match("/^[\\x00-\\xFF]*$/u", $str) === 1);
    }

    static function fixEncoding($str)
    {
        if (!static::isLatin1($str)) {
            $final = Encoding::toUTF8($str);
        } else {
            $fixUtf8 = Encoding::fixUTF8($str);
            $final = $str === $fixUtf8 ? $fixUtf8 : Encoding::toLatin1($str);
        }

        return $final;
    }
}