<?php

namespace App\Controller;

use App\Entity\Countdown;
use App\Entity\Location;
use App\Entity\Timezone;
use App\Service\OpenSSLEncryptionSerivce;
use App\Service\SolarEdgeService;
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
    /**
     * @throws InvalidArgumentException
     */
    #[Route('/screen', name: 'screen')]
    public function screen(
        EntityManagerInterface   $entityManager,
        WeatherService           $weatherService,
        SolarEdgeService         $solarEdgeService,
        CacheInterface           $cache,
        SpotifyController        $spotifyController,
        OpenSSLEncryptionSerivce $encryptionSerivce,
        LayoutConfigController   $layoutConfigController,
        EmailController          $emailController,
        GoogleSyncController     $googleSyncController,
        SetTimezoneController    $setTimezoneController,
        Request                  $request,
    ): Response {
        $layoutResponse = $layoutConfigController->getLayout($entityManager);
        $layoutJson = json_decode($layoutResponse->getContent(), true);
        $layout = $layoutJson['layout'];
        $replacmentLayout = $layoutJson['replacment'];
        if ($request->query->get('second')) {
            foreach ($layout as $key => $value) {
                if ($replacmentLayout[$key] != null) {
                    $layout[$key] = $replacmentLayout[$key];
                }
            }
        }
        $countdown = $entityManager->getRepository(Countdown::class)->findBy([], ['date' => 'ASC']);
        $location = $entityManager->getRepository(Location::class)->find(1);

        $weatherData = $cache->get('weather_data', function (ItemInterface $item) use ($weatherService, $location, $cache) {
            if ($location) {
                $item->expiresAfter(60);
                return $weatherService->getWeatherData($location->getLat(), $location->getLeng());
            } else {
                if (isset($cache)) $cache->delete('weather_data');
                $item->expiresAfter(0);
            }
            return null;
        });

        $airQuality = $cache->get('air_quality', function (ItemInterface $item) use ($weatherService, $location, $cache) {
            if ($location) {
                $item->expiresAfter(60);
                return $weatherService->getAirQuality($location->getLat(), $location->getLeng());
            } else {
                if (isset($cache)) $cache->delete('air_quality');
                $item->expiresAfter(0);
            }
            return null;
        });

        $solarEdgeData = $cache->get('solar_edge_data', function (ItemInterface $item) use ($solarEdgeService, $entityManager, $cache) {
            $data = $solarEdgeService->getSolarEdgeData($entityManager);
            if (!$data) {
                if (isset($cache)) $cache->delete('solar_edge_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(60);
            }
            return $data;
        });

        $getMails = $cache->get('emails_data', function (ItemInterface $item) use ($emailController, $entityManager, $encryptionSerivce, $cache) {
            $data = $emailController->getEmails($entityManager, $encryptionSerivce);
            if (!$data) {
                if (isset($cache)) $cache->delete('emails_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(20);
            }
            return $data;
        });

        $getEvents = $cache->get('events_data', function (ItemInterface $item) use ($googleSyncController, $entityManager, $cache) {
            $data = $googleSyncController->getEvents($entityManager);
            if (!$data) {
                if (isset($cache)) $cache->delete('events_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(20);
            }
            return $data;
        });

        $spotify = $cache->get('spotify_now_playing', function (ItemInterface $item) use ($spotifyController, $entityManager) {
            $data = $spotifyController->getPlayingNow($entityManager);
            $item->expiresAfter(10);
            return $data;
        });

        $weeklyProductionData = $cache->get('solar_edge_weekly_data', function (ItemInterface $item) use ($solarEdgeService, $entityManager, $cache) {
            $data = $solarEdgeService->getSolarEdgeDataWeekly($entityManager);
            if (!$data) {
                if (isset($cache)) $cache->delete('solar_edge_weekly_data');
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(3600);
            }
            return $data;
        });

        $latestMail = $getMails['latestMail'] ?? null;
        $emailConfigured = $getMails['emailConfigured'] ?? false;
        $unreadCount = $getMails['unreadCount'] ?? 0;

        $timeZone = $entityManager->getRepository(Timezone::class)->find(1);
        if (!$timeZone) {
            $timeZone = new Timezone();
            $timeZone->setTimezone(\date_default_timezone_get());
            $entityManager->persist($timeZone);
            $entityManager->flush();
        }

        if (!$location && !$solarEdgeData && !$latestMail && !$spotify && !$getEvents) {
            $url = "https://" . ($request->query->get('ip') ?? $_SERVER['SERVER_NAME']) . "/";
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
                    'date' => $latestMail->date
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
