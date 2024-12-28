<?php

namespace App\Controller;

use App\Entity\Countdown;
use App\Entity\EmailSettings;
use App\Entity\GoogleAccessToken;
use App\Entity\Location;
use App\Entity\SolarEdge;
use App\Entity\Spotify;
use App\Service\LayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AbstractController
{
    #[Route('/', name: 'settings')]
    public function settings(EntityManagerInterface $entityManager, LayoutService $componentService, LayoutConfigController $layoutConfigController): Response
    {
        $solarEdgeSettings = $entityManager->getRepository(SolarEdge::class)->findBy([], ['id' => 'DESC'], 1);
        $emailSettings = $entityManager->getRepository(EmailSettings::class)->findBy([], ['id' => 'DESC'], 1);
        $location = $entityManager->getRepository(Location::class)->find(1);
        $spotifySettings = $entityManager->getRepository(Spotify::class)->findBy([], ['id' => 'DESC'], 1);
        $googleSettings = $entityManager->getRepository(GoogleAccessToken::class)->findBy([], ['id' => 'DESC'], 1);
        $countDown = $entityManager->getRepository(Countdown::class)->findAll();

        // Pobieramy dostępne komponenty
        $availableComponents = $componentService->getAvailableComponents();

        $layoutResponse = $layoutConfigController->getLayout($entityManager);
        $layoutJson = json_decode($layoutResponse->getContent(), true);
        $layout = $layoutJson['layout'];
        $replacmentLayout = $layoutJson['replacment'];
        return $this->render('settings.html.twig', [
            'solarEdgeSettings' => $solarEdgeSettings,
            'emailSettings' => $emailSettings,
            'location' => $location,
            'spotifySettings' => $spotifySettings,
            'googleSettings' => $googleSettings,
            'availableComponents' => $availableComponents,
            'selectedComponents' => $layout,
            'replacementLayout' => $replacmentLayout,
            'countDown' => $countDown,
        ]);
    }
}
