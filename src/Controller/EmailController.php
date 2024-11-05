<?php

namespace App\Controller;

use App\Entity\EmailSettings;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Webklex\PHPIMAP\ClientManager;

class EmailController extends AbstractController
{
    #[Route('/email-settings', name: 'email')]
    public function setEmailDetails(EntityManagerInterface $entityManager, OpenSSLEncryptionSerivce $encryptionSerivce)
    {
        $imapServer =$_POST['imap_server'];
        $imapPort = $_POST['imap_port'];
        $imapUsername = $_POST['imap_username'];
        $imapPassword = $_POST['imap_password'];
        $emailSettings = $entityManager->getRepository(EmailSettings::class)->find(1) ?? new EmailSettings();
        $emailSettings->setImapServer($encryptionSerivce->encrypt($imapServer));
        $emailSettings->setImapPort($imapPort);
        $emailSettings->setImapUser($encryptionSerivce->encrypt($imapUsername));
        $emailSettings->setImapPassword($encryptionSerivce->encrypt($imapPassword));

        if (!$emailSettings->getId()) {
            $entityManager->persist($emailSettings);
        }

        $entityManager->flush();
        return $this->redirectToRoute('settings', ['tab' => 'email-settings']);

    }

    #[Route('/email-settings/delete', name: 'delete-email')]
    public function deleteEmailSettings(EntityManagerInterface $entityManager)
    {
        $emailSettings = $entityManager->getRepository(EmailSettings::class)->findOneBy([], ['id' => 'DESC']);
        if($emailSettings)
        {
            $entityManager->remove($emailSettings);
            $entityManager->flush();
        }
        return $this->redirectToRoute('settings', ['tab' => 'email-settings']);
    }
    
    public function getEmails(EntityManagerInterface $entityManager, OpenSSLEncryptionSerivce $encryptionSerivce)
    {
        $latestMail = null;
        $emailConfigured = false;
        $unreadCount = null;

        $emailSettings = $entityManager->getRepository(EmailSettings::class)->findOneBy([], ['id' => 'DESC']);
        if ($emailSettings) {
            $emailConfigured = true;

            // Inicjalizacja klienta IMAP
            $clientManager = new ClientManager();
            $client = $clientManager->make([
                'host'          => $encryptionSerivce->decrypt($emailSettings->getImapServer()),
                'port'          => $emailSettings->getImapPort(),
                'encryption'    => 'ssl',
                'username'      => $encryptionSerivce->decrypt($emailSettings->getImapUser()),
                'password'      => $encryptionSerivce->decrypt($emailSettings->getImapPassword()),
                'validate_cert' => true,
                'protocol'      => 'imap'
            ]);

            try {
                $client->connect();

                // Pobranie nieprzeczytanych wiadomości
                $folder = $client->getFolder('INBOX');
                $unreadMessages = $folder->query()->unseen()->get();
                $unreadCount = $unreadMessages->count();

                // Pobranie najnowszej wiadomości
                $latestMessages = $folder->query()
                    ->all()
                    ->setFetchOrder('desc') // Ustawienie kolejności pobierania na malejącą
                    ->limit(1)
                    ->get();

                if ($latestMessages->count() > 0) {
                    $latestMail = $latestMessages->first();
                    $latestMailDate = $latestMail->getDate()->toDate(); // Konwersja na obiekt Carbon
                    $latestMail->date = $latestMailDate->format('d-m-Y H:i');
                }
            } catch (\Exception $ex) {
                $this->addFlash('error', 'Wystąpił błąd podczas pobierania e-maili: ' . $ex->getMessage());
            }
        }

        return [
            'latestMail' => $latestMail,
            'emailConfigured' => $emailConfigured,
            'unreadCount' => $unreadCount
        ];
    }


}