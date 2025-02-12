<?php

namespace App\Service;

class OpenSSLEncryptionSerivce
{
    public function encrypt(string $data): string
    {
        $key = $_ENV['ENCRYPTION_KEY'];
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $data): string
    {
        $key = $_ENV['ENCRYPTION_KEY'];
        $data = base64_decode($data);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}
