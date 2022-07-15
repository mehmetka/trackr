<?php

namespace App\util;

class EncodingUtil
{
    static function isLatin1($str)
    {
        return (preg_match("/^[\\x00-\\xFF]*$/u", $str) === 1);
    }

}