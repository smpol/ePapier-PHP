<?php

namespace App\Controller;

use App\Entity\Timezone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SetTimezoneController extends AbstractController
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }

    #[Route('/set-timezone', name: 'set-timezone', methods: ['POST'])]
    public function setTimezone(Request $request): Response
    {
        $timezoneSelected = $request->request->get('timezone');

        if (empty($timezoneSelected)) {
            $this->addFlash('error', 'Timezone is required.');

            return $this->redirectToRoute('settings', ['tab' => 'timezone-settings']);
        }

        try {
            $timezone = $this->em->getRepository(Timezone::class)->find(1) ?? new Timezone();
            $timezone->setTimezone($timezoneSelected);

            if (!$timezone->getId()) {
                $this->em->persist($timezone);
            }

            $this->em->flush();
            $this->addFlash('success', 'Timezone saved successfully.');
        } catch (\Exception $e) {
            $this->logger->error('Error saving timezone: '.$e->getMessage());
            $this->addFlash('error', 'Failed to save timezone.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'timezone-settings']);
    }
}
