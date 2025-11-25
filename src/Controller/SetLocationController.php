<?php

namespace App\Controller;

use App\Entity\Location;
use App\Service\UpdateScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SetLocationController extends AbstractController
{
    private EntityManagerInterface $em;
    private UpdateScreenService $updateScreenService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        UpdateScreenService $updateScreenService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->updateScreenService = $updateScreenService;
        $this->logger = $logger;
    }

    #[Route('/set-location', name: 'set-location', methods: ['POST'])]
    public function setLocation(Request $request): Response
    {
        $lat = $request->request->get('latitude');
        $lon = $request->request->get('longitude');

        if (empty($lat) || empty($lon)) {
            $this->addFlash('error', 'Latitude and longitude are required.');

            return $this->redirectToRoute('settings', ['tab' => 'location-settings']);
        }

        try {
            $location = $this->em->getRepository(Location::class)->find(1) ?? new Location();
            $location->setLat($lat);
            $location->setLon($lon);

            if (!$location->getId()) {
                $this->em->persist($location);
            }

            $this->em->flush();
            $this->updateScreenService->updateScreen();
            $this->addFlash('success', 'Location saved successfully.');
        } catch (\Exception $e) {
            $this->logger->error('Error saving location: '.$e->getMessage());
            $this->addFlash('error', 'Failed to save location.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'location-settings']);
    }
}
