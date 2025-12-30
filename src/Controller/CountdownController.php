<?php

namespace App\Controller;

use App\Entity\Countdown;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CountdownController extends AbstractController
{
    #[Route('/set-countdown', name: 'setCountdown')]
    public function setCountdown(Request $request, EntityManagerInterface $entityManager)
    {
        $dateTime = $request->request->get('countdown_date');
        $description = $request->request->get('countdown_title');

        // No limit on number of countdowns - display is limited in ScreenController
        $countdown = new Countdown();
        $countdown->insertEvent(new \DateTime($dateTime), $description);
        $entityManager->persist($countdown);
        $entityManager->flush();

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }

    #[Route('/update-countdown', name: 'updateCountdown', methods: ['POST'])]
    public function updateCountdown(Request $request, EntityManagerInterface $entityManager)
    {
        $id = $request->request->get('countdown_id');
        $dateTime = $request->request->get('countdown_date');
        $description = $request->request->get('countdown_title');

        $countdown = $entityManager->getRepository(Countdown::class)->find($id);
        if ($countdown) {
            $countdown->setDate(new \DateTime($dateTime));
            $countdown->setDescription($description);
            $entityManager->flush();
        }

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }

    #[Route('/delete-countdown', name: 'deleteCountdown')]
    public function deleteCountdown(Request $request, EntityManagerInterface $entityManager)
    {
        $id = $request->request->get('countdown_id');
        $countdown = $entityManager->getRepository(Countdown::class)->find($id);
        if ($countdown) {
            $entityManager->remove($countdown);
            $entityManager->flush();
        }

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }
}
