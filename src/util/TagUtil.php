<?php

namespace App\util;

class TagUtil
{
    public static function hashtagsToLabel($str)
    {
        $matches = array();
        if (preg_match_all('/#([^\s]+)/', $str, $matches)) {
            foreach ($matches[1] as $match) {
                $str = str_replace('#' . $match, '<span class="badge badge-info">' . $match . '</span>', $str);
            }
        }
        return $str;
    }

    public static function prepareTagsAsArray($tags)
    {
        $result = [];

        if (strpos($tags, ',') !== false) {
            $tags = explode(',', $tags);

            foreach ($tags as $tag) {
                $tag = str_replace(' ', '', trim($tag));
                $result[] = $tag;
            }

        } else {
            $result[] = str_replace(' ', '', trim($tags));
        }

        return $result;
    }
}