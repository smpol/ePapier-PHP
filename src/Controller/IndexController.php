<?php

namespace App\Controller;

use App\Entity\Location;
use App\Service\OpenSSLEncryptionSerivce;
use App\Service\SolarEdgeService;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IndexController extends AbstractController
{
    /**
     * @throws InvalidArgumentException
     */
    #[Route('/', name: 'index')]
    public function index(
        EntityManagerInterface   $entityManager,
        WeatherService           $weatherService,
        SolarEdgeService         $solarEdgeService,
        CacheInterface           $cache,
        SpotifyController        $spotifyController,
        OpenSSLEncryptionSerivce $encryptionSerivce,
        LayoutConfigController   $layoutConfigController,
        EmailController          $emailController,
        GoogleSyncController     $googleSyncController
    ): Response
    {
        $layoutResponse = $layoutConfigController->getLayout();
        $layout = json_decode($layoutResponse->getContent(), true);
        $location = $entityManager->getRepository(Location::class)->find(1);

        $weatherData = $cache->get('weather_data', function (ItemInterface $item) use ($weatherService, $location) {
            if ($location) {
                $item->expiresAfter(60);
                return $weatherService->getWeatherData($location->getLat(), $location->getLeng());
            } else
                $item->expiresAfter(0);
            return null;
        });

        $airQuality = $cache->get('air_quality', function (ItemInterface $item) use ($weatherService, $location) {
            if ($location) {
                $item->expiresAfter(60);
                return $weatherService->getAirQuality($location->getLat(), $location->getLeng());
            } else
                $item->expiresAfter(0);
            return null;
        });

        $solarEdgeData = $cache->get('solar_edge_data', function (ItemInterface $item) use ($solarEdgeService, $entityManager) {
            $data = $solarEdgeService->getSolarEdgeData($entityManager);
            if (!$data) {
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(60);
            }
            return $data;
        });


        $getMails = $cache->get('emails_data', function (ItemInterface $item) use ($emailController, $entityManager, $encryptionSerivce) {
            $data = $emailController->getEmails($entityManager, $encryptionSerivce);
            if (!$data) {
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(20);
            }
            return $data;
        });

        $getEvents = $cache->get('events_data', function (ItemInterface $item) use ($googleSyncController, $entityManager) {
            $data = $googleSyncController->getEvents($entityManager);
            if (!$data) {
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

        $weeklyProductionData = $cache->get('solar_edge_weekly_data', function (ItemInterface $item) use ($solarEdgeService, $entityManager) {
            $data = $solarEdgeService->getSolarEdgeDataWeekly($entityManager);
            if (!$data) {
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter(3600);
            }
            return $data;
        });
        $latestMail = $getMails['latestMail'] ?? null;
        $emailConfigured = $getMails['emailConfigured'] ?? false;
        $unreadCount = $getMails['unreadCount'] ?? 0;

        date_default_timezone_set('Europe/Warsaw');
        $time = date('H:i');

        if (!$location && !$solarEdgeData && !$latestMail && !$spotify && !$getEvents) {
            $url = "https://" . ($_GET['ip'] ?? $_SERVER['SERVER_NAME']) . "/settings";
            return $this->render('notConfigured.html.twig', ['settings_url' => $url]);
        } else {
            return $this->render('index.html.twig', [
                'time' => $time,
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
                'airQuality' => $airQuality
            ]);
        }
    }
}