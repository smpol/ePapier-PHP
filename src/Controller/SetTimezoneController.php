<?php

namespace App\Controller;

use App\Entity\Location;
use App\Entity\Timezone;
use App\Service\UpdateScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SetTimezoneController extends AbstractController
{
    #[Route('/set-timezone', name: 'set-timezone')]
    public function setTimezone(Request $request, EntityManagerInterface $entityManager)
    {
        $timezone_selected = $request->request->get('timezone');
        $timezone = $entityManager->getRepository(Timezone::class)->find(1);
        if (!$timezone) {
            $newTimezone = new Timezone();
            $newTimezone->setTimezone($timezone_selected);
            $entityManager->persist($newTimezone);
        } else {
            $timezone->setTimezone($timezone_selected);
        }
        $entityManager->flush();

        return $this->redirectToRoute('settings', ['tab' => 'timezone-settings']);
    }

}