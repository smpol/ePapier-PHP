<?php

namespace App\Service;

class OpenSSLEncryptionSerivce
{
    public function encrypt(string $data): string
    {
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $data): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($data);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    private function getEncryptionKey(): string
    {
        if (!empty($_ENV['ENCRYPTION_KEY'])) {
            return $_ENV['ENCRYPTION_KEY'];
        }

        $keyPath = __DIR__.'/../../var/encryption.key';

        if (is_readable($keyPath)) {
            $key = trim((string) file_get_contents($keyPath));
            if ($key !== '') {
                $this->exportKeyToEnv($key);

                return $key;
            }
        }

        if (!is_dir(dirname($keyPath))) {
            mkdir(dirname($keyPath), 0775, true);
        }

        $key = base64_encode(random_bytes(32));
        file_put_contents($keyPath, $key);
        chmod($keyPath, 0660);
        $this->exportKeyToEnv($key);

        return $key;
    }

    private function exportKeyToEnv(string $key): void
    {
        $_ENV['ENCRYPTION_KEY'] = $key;
        $_SERVER['ENCRYPTION_KEY'] = $key;
        putenv('ENCRYPTION_KEY='.$key);
    }
}
