<?php

namespace App\util;

class StringUtil
{
    public static function getDecimalHashtags($text) : array
    {
        preg_match_all('/\B#\K([0-9]+)\b/', $text, $matches);
        $uniqueMatches = array_unique($matches[0]);
        rsort($uniqueMatches);
        return $uniqueMatches;
    }

    public static function getAlphaNumericHashtags($text) : array
    {
        preg_match_all('/\B[#]\K[a-zA-Z0-9]+/', $text, $matches);
        $uniqueMatches = array_unique($matches[0]);
        rsort($uniqueMatches);
        return $uniqueMatches;
    }
}