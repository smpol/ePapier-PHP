<?php

namespace App\Controller;

use App\Entity\Countdown;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class CountdownController extends AbstractController
{
    #[Route('/set-countdown', name: 'setCountdown')]
    public function setCountdown(EntityManagerInterface $entityManager)
    {
        $dateTime = $_POST['countdown_date'];
        $description = $_POST['countdown_title'];

        // Sprawdź, ile odliczań już istnieje
        $countdowns = $entityManager->getRepository(Countdown::class)->findAll();

        if (count($countdowns) < 2) {
            $countdown = new Countdown();
            $countdown->insertEvent(new \DateTime($dateTime), $description);
            $entityManager->persist($countdown);
            $entityManager->flush();
        }

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }

    #[Route('/delete-countdown', name: 'deleteCountdown')]
    public function deleteCountdown(EntityManagerInterface $entityManager)
    {
        $id = $_POST['countdown_id'];
        $countdown = $entityManager->getRepository(Countdown::class)->find($id);
        if ($countdown) {
            $entityManager->remove($countdown);
            $entityManager->flush();
        }
        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }
}