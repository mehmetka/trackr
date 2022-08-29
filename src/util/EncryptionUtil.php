<?php

namespace App\util;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

class EncryptionUtil
{
    public static function encrypt($str)
    {
        return Crypto::encrypt($str, $_SESSION['userInfos']['encryption_key']);
    }

    public static function decrypt($str)
    {
        try {
            return Crypto::decrypt($str, $_SESSION['userInfos']['encryption_key']);
        } catch (\Exception $exception) {
            return null;
        }
    }

    public static function createEncryptionKey()
    {
        return Key::createNewRandomKey();
    }
}