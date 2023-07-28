<?php

namespace App\util;

class HighlightUtil
{

    public static function prepareBlogPath(array $tags): string
    {
        $blogPath = '';
        $i = 1;

        foreach ($tags as $tag) {
            if ($i > 1) {
                $blogPath .= '/' . $tag;
            } else {
                $blogPath .= $tag;
            }

            $i++;
        }

        return $blogPath;
    }
}