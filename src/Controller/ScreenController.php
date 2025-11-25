<?php

namespace App\Controller;

use App\Entity\Countdown;
use App\Entity\Location;
use App\Entity\Timezone;
use App\Service\OpenSSLEncryptionSerivce;
use App\Service\SolarEdgeService;
use App\Service\EmailService;
use App\Service\GoogleCalendarService;
use App\Service\SpotifyService;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ScreenController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private WeatherService $weatherService;
    private SolarEdgeService $solarEdgeService;
    private CacheInterface $cache;
    private SpotifyService $spotifyService;
    private OpenSSLEncryptionSerivce $encryptionSerivce;
    private LayoutConfigController $layoutConfigController;
    private EmailService $emailService;
    private GoogleCalendarService $googleCalendarService;

    public function __construct(
        EntityManagerInterface $entityManager,
        WeatherService $weatherService,
        SolarEdgeService $solarEdgeService,
        CacheInterface $cache,
        SpotifyService $spotifyService,
        OpenSSLEncryptionSerivce $encryptionSerivce,
        LayoutConfigController $layoutConfigController,
        EmailService $emailService,
        GoogleCalendarService $googleCalendarService
    ) {
        $this->entityManager = $entityManager;
        $this->weatherService = $weatherService;
        $this->solarEdgeService = $solarEdgeService;
        $this->cache = $cache;
        $this->spotifyService = $spotifyService;
        $this->encryptionSerivce = $encryptionSerivce;
        $this->layoutConfigController = $layoutConfigController;
        $this->emailService = $emailService;
        $this->googleCalendarService = $googleCalendarService;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/screen', name: 'screen')]
    public function screen(Request $request): Response
    {
        $layoutResponse = $this->layoutConfigController->getLayout($this->entityManager);
        $layoutJson = json_decode($layoutResponse->getContent(), true);
        $layout = $layoutJson['layout'];
        $replacmentLayout = $layoutJson['replacment'];
        if ($request->query->get('second')) {
            foreach ($layout as $key => $value) {
                if (null != $replacmentLayout[$key]) {
                    $layout[$key] = $replacmentLayout[$key];
                }
            }
        }
        $countdown = $this->entityManager->getRepository(Countdown::class)->findBy([], ['date' => 'ASC']);
        $location = $this->entityManager->getRepository(Location::class)->find(1);

        $weatherData = $this->cache->get('weather_data', function (ItemInterface $item) use ($location) {
            if ($location) {
                $item->expiresAfter(60);

                return $this->weatherService->getWeatherData($location->getLat(), $location->getLon());
            } else {
                $this->cache->delete('weather_data');
                $item->expiresAfter(0);
            }

            return null;
        });

        $airQuality = $this->cache->get('air_quality', function (ItemInterface $item) use ($location) {
            if ($location) {
                $item->expiresAfter(60);

                return $this->weatherService->getAirQuality($location->getLat(), $location->getLon());
            } else {
                $this->cache->delete('air_quality');
                $item->expiresAfter(0);
            }

            return null;
        });

        $solarEdgeData = $this->cache->get('solar_edge_data', function (ItemInterface $item) {
            $data = $this->solarEdgeService->getSolarEdgeData($this->entityManager);
            if (!$data) {
                $this->cache->delete('solar_edge_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(60);
            }

            return $data;
        });

        $getMails = $this->cache->get('emails_data', function (ItemInterface $item) {
            $data = $this->emailService->getEmails();
            if (!$data) {
                $this->cache->delete('emails_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(20);
            }

            return $data;
        });

        $getEvents = $this->cache->get('events_data', function (ItemInterface $item) {
            $data = $this->googleCalendarService->getEvents();
            if (!$data) {
                $this->cache->delete('events_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(20);
            }

            return $data;
        });

        $spotify = $this->cache->get('spotify_now_playing', function (ItemInterface $item) {
            $data = $this->spotifyService->getPlayingNow();
            $item->expiresAfter(10);

            return $data;
        });

        $weeklyProductionData = $this->cache->get('solar_edge_weekly_data', function (ItemInterface $item) {
            $data = $this->solarEdgeService->getSolarEdgeDataWeekly($this->entityManager);
            if (!$data) {
                $this->cache->delete('solar_edge_weekly_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(3600);
            }

            return $data;
        });

        $latestMail = $getMails['latestMail'] ?? null;
        $emailConfigured = $getMails['emailConfigured'] ?? false;
        $unreadCount = $getMails['unreadCount'] ?? 0;

        $timeZone = $this->entityManager->getRepository(Timezone::class)->find(1);
        if (!$timeZone) {
            $timeZone = new Timezone();
            $timeZone->setTimezone(\date_default_timezone_get());
            $this->entityManager->persist($timeZone);
            $this->entityManager->flush();
        }

        if (!$location && !$solarEdgeData && !$latestMail && !$spotify && !$getEvents) {
            $url = 'https://'.($request->query->get('ip') ?? $_SERVER['SERVER_NAME']).'/';

            return $this->render('notConfigured.html.twig', ['settings_url' => $url]);
        } else {
            return $this->render('index.html.twig', [
                'weather' => $weatherData,
                'solarData' => $solarEdgeData,
                'weeklyProductionData' => $weeklyProductionData,
                'unreadCount' => $unreadCount,
                'latestMail' => $latestMail ? [
                    'subject' => $latestMail->subject,
                    'from' => $latestMail->from,
                    'date' => $latestMail->date,
                ] : null,
                'emailConfigured' => $emailConfigured,
                'spotifyNowPlaying' => $spotify,
                'events' => $getEvents,
                'layout' => $layout,
                'airQuality' => $airQuality,
                'countdown' => $countdown,
                'timeZone' => $timeZone->getTimezone(),
            ]);
        }
    }
}
