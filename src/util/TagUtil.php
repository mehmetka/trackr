<?php

namespace App\util;

class TagUtil
{
    static function hashtagsToLabel($str)
    {
        $matches = array();
        if (preg_match_all('/#([^\s]+)/', $str, $matches)) {
            foreach ($matches[1] as $match) {
                $str = str_replace('#' . $match, '<span class="badge badge-info">' . $match . '</span>', $str);
            }
        }
        return $str;
    }
}