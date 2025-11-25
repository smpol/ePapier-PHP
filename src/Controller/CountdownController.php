<?php

namespace App\Controller;

use App\Entity\Countdown;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CountdownController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/set-countdown', name: 'setCountdown', methods: ['POST'])]
    public function setCountdown(Request $request): Response
    {
        $dateTime = $request->request->get('countdown_date');
        $description = $request->request->get('countdown_title');

        if (empty($dateTime) || empty($description)) {
            $this->addFlash('error', 'Date and description are required.');

            return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
        }

        $countdowns = $this->em->getRepository(Countdown::class)->findAll();

        if (count($countdowns) < 2) {
            $countdown = new Countdown();
            $countdown->setDate(new \DateTime($dateTime));
            $countdown->setDescription($description);

            $this->em->persist($countdown);
            $this->em->flush();
            $this->addFlash('success', 'Countdown created successfully.');
        } else {
            $this->addFlash('error', 'You can only have a maximum of two countdowns.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }

    #[Route('/update-countdown/{id}', name: 'updateCountdown', methods: ['POST'])]
    public function updateCountdown(Request $request, int $id): Response
    {
        $countdown = $this->em->getRepository(Countdown::class)->find($id);

        if (!$countdown) {
            $this->addFlash('error', 'Countdown not found.');

            return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
        }

        $dateTime = $request->request->get('countdown_date');
        $description = $request->request->get('countdown_title');

        if (!empty($dateTime)) {
            $countdown->setDate(new \DateTime($dateTime));
        }

        if (!empty($description)) {
            $countdown->setDescription($description);
        }

        $this->em->flush();
        $this->addFlash('success', 'Countdown updated successfully.');

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }

    #[Route('/delete-countdown/{id}', name: 'deleteCountdown', methods: ['POST'])]
    public function deleteCountdown(int $id): Response
    {
        $countdown = $this->em->getRepository(Countdown::class)->find($id);

        if ($countdown) {
            $this->em->remove($countdown);
            $this->em->flush();
            $this->addFlash('success', 'Countdown deleted successfully.');
        } else {
            $this->addFlash('error', 'Countdown not found.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'countdown-settings']);
    }
}
