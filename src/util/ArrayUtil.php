<?php

namespace App\util;

class ArrayUtil
{

    public static function trimArrayElements($array)
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return trimArrayElements($item);
            }
            return is_string($item) ? trim($item) : $item;
        }, $array);
    }
}