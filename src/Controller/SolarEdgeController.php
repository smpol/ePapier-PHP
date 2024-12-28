<?php

namespace App\Controller;

use App\Entity\SolarEdge;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SolarEdgeController extends AbstractController
{
    #[Route('/solar-edge', name: 'solar-edge')]
    public function setSolarEdge(Request $request, EntityManagerInterface $entityManager, OpenSSLEncryptionSerivce $encryptionSerivce): Response
    {
        $apiKey = $request->request->get('api_key');
        $siteId = $request->request->get('site_id');

        if (!$apiKey || !$siteId) {
            $this->addFlash('error', 'API Key and Site ID are required.');
            return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
        }

        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']) ?? new SolarEdge();
        $solarEdge->setApiKey($encryptionSerivce->encrypt($apiKey));
        $solarEdge->setSiteId($siteId);

        if (!$solarEdge->getId()) {
            $entityManager->persist($solarEdge);
        }

        try {
            $entityManager->flush();
            $this->addFlash('success', 'SolarEdge configuration saved successfully.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to save SolarEdge configuration. Please try again.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }

    #[Route('/solar-edge/delete', name: 'delete-solar-edge')]
    public function deleteSolarEdge(EntityManagerInterface $entityManager): Response
    {
        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);
        if ($solarEdge) {
            try {
                $entityManager->remove($solarEdge);
                $entityManager->flush();
                $this->addFlash('success', 'SolarEdge configuration deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to delete SolarEdge configuration.');
            }
        } else {
            $this->addFlash('info', 'No SolarEdge configuration to delete.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }
}
