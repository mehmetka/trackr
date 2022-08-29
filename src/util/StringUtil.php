<?php

namespace App\util;

class StringUtil
{
    public static function getDecimalHashtags($text) : array
    {
        preg_match_all('/\B[#]\K\d+/', $text, $matches);
        return $matches[0];
    }
}