<?php

namespace App\util;

class RequestUtil
{
    const HTTP_GET = 'GET';
    const HTTP_PUT = 'PUT';
    const HTTP_POST = 'POST';
    const HTTP_DELETE = 'DELETE';

    /**
     * @param $url
     * @return mixed|string|null
     * @deprecated
     */
    static function getTitle($url)
    {
        try {
            $data = @file_get_contents($url);
            $code = RequestUtil::getHttpCode($http_response_header);

            if ($code === 404) {
                return null;
            }
        } catch (\Exception $exception) {
            return null;
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches)) {
            return mb_check_encoding($matches[1], 'UTF-8') ? $matches[1] : utf8_encode($matches[1]);
        }

        return null;
    }

    /**
     * @param $http_response_header
     * @return int
     * @deprecated
     */
    private static function getHttpCode($http_response_header)
    {
        if (is_array($http_response_header)) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) //HTTP/1.0 <code> <text>
            {
                return intval($parts[1]);
            } //Get code
        }
        return 0;
    }

    static function getUrlMetadata($url, $specificTags = 0)
    {
        $html = RequestUtil::getUrlContent($url);

        if ($html) {
            $doc = new \DOMDocument();
            @$doc->loadHTML($html);
            $res['title'] = $doc->getElementsByTagName('title')->item(0)->nodeValue;

            foreach ($doc->getElementsByTagName('meta') as $m) {
                $tag = $m->getAttribute('name') ?: $m->getAttribute('property');
                if (in_array($tag, ['description', 'keywords']) || strpos($tag, 'og:') === 0) {
                    $res[str_replace('og:', '', $tag)] = $m->getAttribute('content');
                }
            }
            return $specificTags ? array_intersect_key($res, array_flip($specificTags)) : $res;
        }

        return null;
    }

    static function getUrlContent($url)
    {
        $headers = [
            'User-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
            'Accept: */*'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $html = curl_exec($ch);
        curl_close($ch);

        return $html;
    }

    static function makeHttpRequest($url, $httpMethod, $body, $headers)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
}