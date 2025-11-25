<?php

namespace App\Service;

use App\Entity\EmailSettings;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapClientFactory
{
    private OpenSSLEncryptionSerivce $encryptionSerivce;

    public function __construct(OpenSSLEncryptionSerivce $encryptionSerivce)
    {
        $this->encryptionSerivce = $encryptionSerivce;
    }

    public function createClient(EmailSettings $emailSettings): Client
    {
        $clientManager = new ClientManager();

        return $clientManager->make([
            'host' => $this->encryptionSerivce->decrypt($emailSettings->getImapServer()),
            'port' => $emailSettings->getImapPort(),
            'encryption' => 'ssl',
            'username' => $this->encryptionSerivce->decrypt($emailSettings->getImapUser()),
            'password' => $this->encryptionSerivce->decrypt($emailSettings->getImapPassword()),
            'validate_cert' => true,
            'protocol' => 'imap',
        ]);
    }
}
