<?php

namespace App\util;

class MarkdownUtil
{
    public static function convertToHTML($str)
    {
        $str = str_replace("\n", "   \n", $str);
        $parseDown = new \Parsedown();
        $parseDown->setSafeMode(true);
        return $parseDown->text($str);
    }
}