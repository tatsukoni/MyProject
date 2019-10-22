<?php

namespace App;

use phpseclib\Crypt\Blowfish;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;

class BlowfishEncrypter implements EncrypterContract
{
    protected $encrypter;

    public function __construct(string $key)
    {
        $this->encrypter = new Blowfish();
        $this->encrypter->setkey($key);
    }

    public function encrypt($value, $serialize = true)
    {
        $this->encrypter->encrypt($value);
    }

    public function decrypt($payload, $unserialize = true)
    {
        $this->encrypter->decrypt($payload);
    }
}
