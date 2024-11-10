<?php

namespace App\Controller;

use App\Entity\SolarEdge;
use App\Service\OpenSSLEncryptionSerivce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SolarEdgeController extends AbstractController
{
    #[Route('/solar-edge', name: 'solar-edge')]
    public function setSolarEdge(EntityManagerInterface $entityManager, OpenSSLEncryptionSerivce $encryptionSerivce): Response
    {
        $apiKey = $_POST['api_key'];
        $siteId = $_POST['site_id'];
        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']) ?? new SolarEdge();
        $solarEdge->setApiKey($encryptionSerivce->encrypt($apiKey));
        $solarEdge->setSiteId($siteId);

        if (!$solarEdge->getId()) {
            $entityManager->persist($solarEdge);
        }

        $entityManager->flush();
        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }

    #[Route('/solar-edge/delete', name: 'delete-solar-edge')]
    public function deleteSolarEdge(EntityManagerInterface $entityManager): Response
    {
        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);
        if ($solarEdge) {
            $entityManager->remove($solarEdge);
            $entityManager->flush();
        }
        return $this->redirectToRoute('settings', ['tab' => 'solar-settings']);
    }

}