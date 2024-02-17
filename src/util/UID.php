<?php

namespace App\util;

class UID
{
    static function generate()
    {
        $rand1 = substr(md5(uniqid('', true)), rand(0, 26), 3);
        $rand2 = substr(md5(uniqid('', true)), rand(0, 26), 4);
        $rand3 = substr(md5(uniqid('', true)), rand(0, 26), 3);

        return "$rand1-$rand2-$rand3";
    }
}