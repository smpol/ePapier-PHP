<?php

namespace App\Service;

use App\Entity\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EmailService
{
    private EntityManagerInterface $em;
    private ImapClientFactory $imapClientFactory;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        ImapClientFactory $imapClientFactory,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->imapClientFactory = $imapClientFactory;
        $this->logger = $logger;
    }

    public function getEmails(): array
    {
        $emailSettings = $this->em->getRepository(EmailSettings::class)->findOneBy([], ['id' => 'DESC']);

        if (!$emailSettings) {
            return [
                'latestMail' => null,
                'emailConfigured' => false,
                'unreadCount' => null,
            ];
        }

        try {
            $client = $this->imapClientFactory->createClient($emailSettings);
            $client->connect();

            $inbox = $client->getFolder('INBOX');
            $unreadCount = $inbox->query()->unseen()->get()->count();
            $latestMessage = $inbox->query()->all()->setFetchOrder('desc')->limit(1)->get()->first();

            $latestMail = null;
            if ($latestMessage) {
                $latestMailDate = $latestMessage->getDate()?->toDate();
                $latestMessage->date = $latestMailDate ? $latestMailDate->format('d-m-Y H:i') : null;
                $latestMail = $latestMessage;
            }

            return [
                'latestMail' => $latestMail,
                'emailConfigured' => true,
                'unreadCount' => $unreadCount,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching emails: '.$e->getMessage());

            return [
                'latestMail' => null,
                'emailConfigured' => true,
                'unreadCount' => null,
                'error' => 'Failed to fetch emails.',
            ];
        }
    }
}
