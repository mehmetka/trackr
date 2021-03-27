<?php

namespace App\util;

class Util
{
    static function dateDiff($d1, $d2)
    {
        return round(abs(strtotime($d1) - strtotime($d2)) / 86400);
    }

    static function getDayDifference($from, $to)
    {
        $interval = date_diff(date_create($from), date_create($to));
        return intval($interval->format('%a')) + 1;
    }

    static function epochDateDiff($d1, $d2)
    {
        return round(abs($d1 - $d2) / 86400);
    }

    static function calculateAge($d1, $d2)
    {
        return round(round(abs(strtotime($d1) - strtotime($d2)) / 86400) / 365, 3);
    }

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