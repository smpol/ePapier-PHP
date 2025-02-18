<?php

namespace App\Controller;

use App\Entity\Location;
use App\Service\UpdateScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SetLocationController extends AbstractController
{
    #[Route('/set-location', name: 'set-location')]
    public function setLocation(Request $request, EntityManagerInterface $entityManager)
    {
        $lat = $request->request->get('latitude');
        $lng = $request->request->get('longitude');

        $location = $entityManager->getRepository(Location::class)->find(1);
        if (!$location) {
            $newLocation = new Location();
            $newLocation->setLat($lat);
            $newLocation->setLong($lng);
            $entityManager->persist($newLocation);
        } else {
            $location->setLat($lat);
            $location->setLong($lng);
        }
        $entityManager->flush();

        $screen = new UpdateScreenService();
        $screen->updateScreen();

        return $this->redirectToRoute('settings', ['tab' => 'location-settings']);
    }
}
