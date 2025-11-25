<?php

namespace App\Controller;

use App\Entity\Countdown;
use App\Entity\EmailSettings;
use App\Entity\GoogleAccessToken;
use App\Entity\Location;
use App\Entity\SolarEdge;
use App\Entity\Spotify;
use App\Entity\Timezone;
use App\Service\LayoutService;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AbstractController
{
    private EntityManagerInterface $em;
    private LayoutService $layoutService;
    private LayoutConfigController $layoutConfigController;
    private TimezoneService $timezoneService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        LayoutService $layoutService,
        LayoutConfigController $layoutConfigController,
        TimezoneService $timezoneService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->layoutService = $layoutService;
        $this->layoutConfigController = $layoutConfigController;
        $this->timezoneService = $timezoneService;
        $this->logger = $logger;
    }

    #[Route('/', name: 'settings')]
    public function settings(): Response
    {
        try {
            $solarEdgeSettings = $this->em->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);
            $emailSettings = $this->em->getRepository(EmailSettings::class)->findOneBy([], ['id' => 'DESC']);
            $location = $this->em->getRepository(Location::class)->find(1);
            $spotifySettings = $this->em->getRepository(Spotify::class)->findOneBy([], ['id' => 'DESC']);
            $googleSettings = $this->em->getRepository(GoogleAccessToken::class)->findOneBy([], ['id' => 'DESC']);
            $countDown = $this->em->getRepository(Countdown::class)->findAll();
            $selectedTimezoneEntity = $this->em->getRepository(Timezone::class)->find(1);
            $selectedTimezone = $selectedTimezoneEntity ? $selectedTimezoneEntity->getTimezone() : null;

            $availableComponents = $this->layoutService->getAvailableComponents();
            $timeZones = $this->timezoneService->getTimezones();

            $layoutResponse = $this->layoutConfigController->getLayout($this->em);
            $layoutJson = json_decode($layoutResponse->getContent(), true);

            return $this->render('settings.html.twig', [
                'solarEdgeSettings' => $solarEdgeSettings,
                'emailSettings' => $emailSettings,
                'location' => $location,
                'spotifySettings' => $spotifySettings,
                'googleSettings' => $googleSettings,
                'availableComponents' => $availableComponents,
                'selectedComponents' => $layoutJson['layout'],
                'replacementLayout' => $layoutJson['replacment'],
                'countDown' => $countDown,
                'timeZones' => $timeZones,
                'selectedTimezone' => $selectedTimezone,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error loading settings page: '.$e->getMessage());
            $this->addFlash('error', 'Failed to load settings page.');

            return $this->render('settings.html.twig');
        }
    }
}
