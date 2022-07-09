<?php

namespace App\util;

class TwitterUtil
{
    static function getUsernameFromUrl($url)
    {
        preg_match("|https?://(www\.)?twitter\.com/(#!/)?@?([^/]*)|", $url, $matches);
        return $matches[3];
    }

    static function isTwitterUrl($url)
    {
        return preg_match("/(?:http:\/\/)?(?:www\.)?twitter\.com\/(?:(?:\w)*#!\/)?(?:pages\/)?(?:[\w\-]*\/)*([\w\-]*)/",
            $url);
    }
}