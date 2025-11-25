<?php

namespace App\Controller;

use App\Entity\EmailSettings;
use App\Service\EmailService;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailController extends AbstractController
{
    private EntityManagerInterface $em;
    private OpenSSLEncryptionSerivce $encryptionService;
    private EmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        OpenSSLEncryptionSerivce $encryptionService,
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->encryptionService = $encryptionService;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    #[Route('/email-settings', name: 'email', methods: ['POST'])]
    public function setEmailDetails(Request $request): Response
    {
        try {
            $imapServer = $request->request->get('imap_server');
            $imapPort = $request->request->get('imap_port');
            $imapUsername = $request->request->get('imap_username');
            $imapPassword = $request->request->get('imap_password');

            if (empty($imapServer) || empty($imapPort) || empty($imapUsername) || empty($imapPassword)) {
                $this->addFlash('error', 'All fields are required.');

                return $this->redirectToRoute('settings', ['tab' => 'email-settings']);
            }

            $emailSettings = $this->em->getRepository(EmailSettings::class)->find(1) ?? new EmailSettings();
            $emailSettings->setImapServer($this->encryptionService->encrypt($imapServer));
            $emailSettings->setImapPort($imapPort);
            $emailSettings->setImapUser($this->encryptionService->encrypt($imapUsername));
            $emailSettings->setImapPassword($this->encryptionService->encrypt($imapPassword));

            if (!$emailSettings->getId()) {
                $this->em->persist($emailSettings);
            }

            $this->em->flush();
            $this->addFlash('success', 'Email settings saved successfully.');
        } catch (\Exception $e) {
            $this->logger->error('Error saving email settings: '.$e->getMessage());
            $this->addFlash('error', 'Failed to save email settings.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'email-settings']);
    }

    #[Route('/email-settings/delete', name: 'delete-email', methods: ['POST'])]
    public function deleteEmailSettings(): Response
    {
        try {
            $emailSettings = $this->em->getRepository(EmailSettings::class)->findOneBy([], ['id' => 'DESC']);
            if ($emailSettings) {
                $this->em->remove($emailSettings);
                $this->em->flush();
                $this->addFlash('success', 'Email settings deleted successfully.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error deleting email settings: '.$e->getMessage());
            $this->addFlash('error', 'Failed to delete email settings.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'email-settings']);
    }

    public function getEmails(): array
    {
        return $this->emailService->getEmails();
    }
}
