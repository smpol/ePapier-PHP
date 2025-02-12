<?php

namespace App\Controller;

use App\Entity\EmailSettings;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Webklex\PHPIMAP\ClientManager;

class EmailController extends AbstractController
{
    #[Route('/email-settings', name: 'email')]
    public function setEmailDetails(Request $request, EntityManagerInterface $entityManager, OpenSSLEncryptionSerivce $encryptionSerivce)
    {
        $imapServer = $request->request->get('imap_server');
        $imapPort = $request->request->get('imap_port');
        $imapUsername = $request->request->get('imap_username');
        $imapPassword = $request->request->get('imap_password');

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
        if ($emailSettings) {
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

            $clientManager = new ClientManager();
            $client = $clientManager->make([
                'host' => $encryptionSerivce->decrypt($emailSettings->getImapServer()),
                'port' => $emailSettings->getImapPort(),
                'encryption' => 'ssl',
                'username' => $encryptionSerivce->decrypt($emailSettings->getImapUser()),
                'password' => $encryptionSerivce->decrypt($emailSettings->getImapPassword()),
                'validate_cert' => true,
                'protocol' => 'imap',
            ]);

            try {
                $client->connect();

                $folder = $client->getFolder('INBOX');
                $unreadMessages = $folder->query()->unseen()->get();
                $unreadCount = $unreadMessages->count();

                $latestMessages = $folder->query()
                    ->all()
                    ->setFetchOrder('desc')
                    ->limit(1)
                    ->get();

                if ($latestMessages->count() > 0) {
                    $latestMail = $latestMessages->first();
                    $latestMailDate = $latestMail->getDate()?->toDate();
                    $latestMail->date = $latestMailDate ? $latestMailDate->format('d-m-Y H:i') : null;
                }
            } catch (\Exception $ex) {
                $this->addFlash('error', 'Wystąpił błąd podczas pobierania e-maili: '.$ex->getMessage());
            }
        }

        return [
            'latestMail' => $latestMail,
            'emailConfigured' => $emailConfigured,
            'unreadCount' => $unreadCount,
        ];
    }
}
