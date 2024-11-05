<?php

namespace App\Controller;

use App\Entity\GoogleAccessToken;
use App\Entity\SelectedCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Google_Service_Calendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleSyncController extends AbstractController
{
    #[Route('/google-login', name: 'google-login')]
    public function googleLogin()
    {
        $client = $this->initializeGoogleClient();
        $authUrl = $client->createAuthUrl();

        return $this->redirect($authUrl);
    }

    #[Route('/google-callback', name: 'google-callback')]
    public function googleCallback(Request $request, EntityManagerInterface $em)
    {
        $client = $this->initializeGoogleClient();
        $authCode = $request->query->get('code');

        if (!$authCode) {
            return $this->json(['error' => 'Authorization code not found'], 400);
        }

        // Exchange authorization code for access token
        $tokenResponse = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($tokenResponse['error'])) {
            return $this->json([
                'error' => $tokenResponse['error'],
                'error_description' => $tokenResponse['error_description']
            ], 400);
        }

        $accessToken = $client->getAccessToken();
        $this->storeAccessToken($em, $accessToken);

        $service = new Google_Service_Calendar($client);
        try {
            $calendarList = $service->calendarList->listCalendarList();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch calendar list',
                'error_description' => $e->getMessage()
            ], 500);
        }

        return $this->render('select_calendars.html.twig', [
            'calendars' => $calendarList->getItems(),
        ]);
    }

    #[Route('/google-save-calendar', name: 'google-save-calendar', methods: ['POST'])]
    public function saveSelectedCalendars(Request $request, EntityManagerInterface $em)
    {
        $allParameters = $request->request->all();
        $selectedCalendars = $allParameters['calendars'] ?? [];

        if (empty($selectedCalendars)) {
            return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
        }

        $em->createQuery('DELETE FROM App\Entity\SelectedCalendar')->execute();
        foreach ($selectedCalendars as $calendarId) {
            if (!is_scalar($calendarId)) {
                continue;
            }

            $selectedCalendar = new SelectedCalendar();
            $selectedCalendar->setCalendarId((string)$calendarId);
            $selectedCalendar->setCalendarName($calendarId);

            $em->persist($selectedCalendar);
        }
        $em->flush();

        return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
    }

    #[Route('/google-remove-calendar', name: 'google-remove-calendar')]
    public function removeGoogle(EntityManagerInterface $entityManager)
    {
        $googleAccessTokens = $entityManager->getRepository(GoogleAccessToken::class)->findAll();
        $selectedCalendars = $entityManager->getRepository(SelectedCalendar::class)->findAll();

        foreach ($googleAccessTokens as $token) {
            $this->revokeToken($token->getAccessToken());
            $entityManager->remove($token);
        }

        foreach ($selectedCalendars as $calendar) {
            $entityManager->remove($calendar);
        }

        $entityManager->flush();

        return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
    }

    public function getEvents(EntityManagerInterface $em): ?array
    {
        // Retrieve the Google access token from the database
        $googleAccessToken = $em->getRepository(GoogleAccessToken::class)->findOneBy([], ['id' => 'DESC']);

        if (!$googleAccessToken || !$googleAccessToken->getAccessToken()) {
            error_log('Google access token not found');
            return null;
        }

        $client = $this->initializeGoogleClient();
        $client->setAccessToken($googleAccessToken->getAccessToken());

        // Check if the access token is expired
        if ($client->isAccessTokenExpired()) {
            // Sprawdzamy, czy mamy token odświeżania
            $refreshToken = $googleAccessToken->getRefreshToken();

            if ($refreshToken) {
                try {
                    // Attempt to refresh the access token
                    $newAccessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

                    // check if the new access token was successfully retrieved
                    if (isset($newAccessToken['access_token'])) {
                        $this->storeAccessToken($em, $newAccessToken);
                    } else {
                        error_log("Failed to refresh access token");
                        return null;
                    }
                } catch (\Exception $e) {
                    error_log("Error refreshing access token: " . $e->getMessage());
                    return null;
                }
            } else {
                error_log("No refresh token available to refresh access token");
                return null;
            }
        }

        $service = new Google_Service_Calendar($client);
        $selectedCalendars = $em->getRepository(SelectedCalendar::class)->findAll();

        $events = [];
        $now = new \DateTime();
        $sixtyDaysLater = (new \DateTime())->add(new \DateInterval('P60D'));
        $nowFormatted = $now->format(\DateTimeInterface::RFC3339);
        $sixtyDaysLaterFormatted = $sixtyDaysLater->format(\DateTimeInterface::RFC3339);

        foreach ($selectedCalendars as $calendar) {
            try {
                $eventList = $service->events->listEvents($calendar->getCalendarId(), [
                    'timeMin' => $nowFormatted,
                    'timeMax' => $sixtyDaysLaterFormatted,
                    'singleEvents' => true,
                    'orderBy' => 'startTime',
                    'maxResults' => 2,
                ]);
            } catch (\Exception $e) {
                error_log("Failed to fetch events for calendar {$calendar->getCalendarId()}: " . $e->getMessage());
                return null;
            }

            foreach ($eventList->getItems() as $event) {
                $events[] = [
                    'summary' => $event->getSummary(),
                    'start' => $event->start->dateTime ?? $event->start->date,
                    'location' => $event->getLocation() ?? '',
                ];
            }
        }

        usort($events, function ($a, $b) {
            return new \DateTime($a['start']) <=> new \DateTime($b['start']);
        });

        return array_slice($events, 0, 2);
    }


    private function initializeGoogleClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
            $client->setRedirectUri('https://127.0.0.1:8000/google-callback');
        } else {
            $client->setRedirectUri('https://' . $_ENV['REDIRECT_URL'] . '/google-callback');
        }
        $client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    private function storeAccessToken(EntityManagerInterface $em, array $accessToken)
    {
        $expiresIn = $accessToken['expires_in'] ?? 0;
        $expiresAt = (new \DateTime())->add(new \DateInterval('PT' . $expiresIn . 'S'));
        $googleAccessToken = new GoogleAccessToken();
        $googleAccessToken->setAccessToken($accessToken['access_token']);
        $googleAccessToken->setExpiresAt($expiresAt);

        if (isset($accessToken['refresh_token'])) {
            $googleAccessToken->setRefreshToken($accessToken['refresh_token']);
        }

        $em->persist($googleAccessToken);
        $em->flush();
    }

    private function revokeToken(?string $token)
    {
        if ($token) {
            $client = new Google_Client();
            return $client->revokeToken($token);
        }
        return false;
    }
}
