<?php

namespace App\Controller;

use App\Entity\SolarEdge;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SolarEdgeController extends AbstractController
{
    private EntityManagerInterface $em;
    private OpenSSLEncryptionSerivce $encryptionService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        OpenSSLEncryptionSerivce $encryptionService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->encryptionService = $encryptionService;
        $this->logger = $logger;
    }

    #[Route('/solar-edge', name: 'solar-edge', methods: ['POST'])]
    public function setSolarEdge(Request $request): Response
    {
        $apiKey = $request->request->get('api_key');
        $siteId = $request->request->get('site_id');

        if (empty($apiKey) || empty($siteId)) {
            $this->addFlash('error', 'API Key and Site ID are required.');

            return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
        }

        try {
            $solarEdge = $this->em->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']) ?? new SolarEdge();
            $solarEdge->setApiKey($this->encryptionService->encrypt($apiKey));
            $solarEdge->setSiteId($siteId);

            if (!$solarEdge->getId()) {
                $this->em->persist($solarEdge);
            }

            $this->em->flush();
            $this->addFlash('success', 'SolarEdge configuration saved successfully.');
        } catch (\Exception $e) {
            $this->logger->error('Error saving SolarEdge configuration: '.$e->getMessage());
            $this->addFlash('error', 'Failed to save SolarEdge configuration. Please try again.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }

    #[Route('/solar-edge/delete', name: 'delete-solar-edge', methods: ['POST'])]
    public function deleteSolarEdge(): Response
    {
        $solarEdge = $this->em->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);

        if ($solarEdge) {
            try {
                $this->em->remove($solarEdge);
                $this->em->flush();
                $this->addFlash('success', 'SolarEdge configuration deleted successfully.');
            } catch (\Exception $e) {
                $this->logger->error('Error deleting SolarEdge configuration: '.$e->getMessage());
                $this->addFlash('error', 'Failed to delete SolarEdge configuration.');
            }
        } else {
            $this->addFlash('info', 'No SolarEdge configuration to delete.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }
}
