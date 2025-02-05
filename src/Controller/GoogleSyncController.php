<?php

namespace App\Controller;

use App\Entity\GoogleAccessToken;
use App\Entity\SelectedCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleSyncController extends AbstractController
{
    #[Route('/google-login', name: 'google-login')]
    public function googleLogin(): Response
    {
        $client = $this->initializeGoogleClient();
        $authUrl = $client->createAuthUrl();

        return $this->redirect($authUrl);
    }

    #[Route('/google-callback', name: 'google-callback')]
    public function googleCallback(Request $request, EntityManagerInterface $em): Response
    {
        $client = $this->initializeGoogleClient();
        $authCode = $request->query->get('code');

        if (!$authCode) {
            return $this->json(['error' => 'Authorization code not found'], 400);
        }

        try {
            $tokenResponse = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($tokenResponse['error'])) {
                return $this->json([
                    'error' => $tokenResponse['error'],
                    'error_description' => $tokenResponse['error_description']
                ], 400);
            }

            $this->storeAccessToken($em, $client->getAccessToken());

            $service = new GoogleCalendar($client);
            $calendarList = $service->calendarList->listCalendarList();

            return $this->render('select_calendars.html.twig', [
                'calendars' => $calendarList->getItems(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to process Google callback',
                'error_description' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/google-save-calendar', name: 'google-save-calendar', methods: ['POST'])]
    public function saveSelectedCalendars(Request $request, EntityManagerInterface $em): Response
    {
        $selectedCalendars = $request->request->all('calendars');

        if (!is_array($selectedCalendars) || empty($selectedCalendars)) {
            $this->addFlash('error', 'Invalid or empty calendar selection.');
            return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
        }

        $em->createQuery('DELETE FROM App\Entity\SelectedCalendar')->execute();

        foreach ($selectedCalendars as $calendarId) {
            if (!is_scalar($calendarId)) {
                continue;
            }

            $selectedCalendar = new SelectedCalendar();
            $selectedCalendar->setCalendarId((string)$calendarId);
            $selectedCalendar->setCalendarName((string)$calendarId);

            $em->persist($selectedCalendar);
        }

        $em->flush();

        return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
    }

    #[Route('/google-remove-calendar', name: 'google-remove-calendar')]
    public function removeGoogle(EntityManagerInterface $entityManager): Response
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
        $googleAccessToken = $em->getRepository(GoogleAccessToken::class)
            ->findOneBy([], ['id' => 'DESC']);

        if (!$googleAccessToken) {
            error_log('No Google access token found');
            return null;
        }

        $client = $this->initializeGoogleClient();
        $client->setAccessToken($googleAccessToken->getAccessToken());

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $googleAccessToken->getRefreshToken();

            if (!$refreshToken) {
                error_log('No refresh token available');
                return null;
            }

            try {
                $newAccessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($newAccessToken['access_token'])) {
                    $googleAccessToken->setAccessToken($newAccessToken['access_token']);
                    $googleAccessToken->setExpiresAt(
                        (new \DateTime())->add(
                            new \DateInterval('PT' . ($newAccessToken['expires_in'] ?? 0) . 'S')
                        )
                    );
                    if (isset($newAccessToken['refresh_token'])) {
                        $googleAccessToken->setRefreshToken($newAccessToken['refresh_token']);
                    }
                    $em->persist($googleAccessToken);
                    $em->flush();
                } else {
                    error_log("Failed to refresh access token");
                    return null;
                }
            } catch (\Exception $e) {
                error_log("Error refreshing access token: " . $e->getMessage());
                return null;
            }
        }

        $service = new GoogleCalendar($client);
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
                ]);

                foreach ($eventList->getItems() as $event) {
                    $events[] = [
                        'summary' => $event->getSummary(),
                        'start' => $event->start->dateTime ?? $event->start->date,
                        'location' => $event->getLocation() ?? '',
                    ];
                }
            } catch (\Exception $e) {
                error_log("Failed to fetch events for calendar {$calendar->getCalendarId()}: " . $e->getMessage());
                continue;
            }
        }

        usort($events, function ($a, $b) {
            return new \DateTime($a['start']) <=> new \DateTime($b['start']);
        });

        return array_slice($events, 0, 2);
    }

    private function initializeGoogleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $client->setRedirectUri(
            (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']))
                ? 'https://127.0.0.1:8000/google-callback'
                : 'https://' . $_ENV['REDIRECT_URL'] . '/google-callback'
        );
        $client->addScope(GoogleCalendar::CALENDAR_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setHttpClient(new \GuzzleHttp\Client(['timeout' => 10]));

        return $client;
    }

    private function storeAccessToken(EntityManagerInterface $em, array $accessToken)
    {
        $googleAccessToken = new GoogleAccessToken();
        $googleAccessToken->setAccessToken($accessToken['access_token']);
        $googleAccessToken->setExpiresAt(
            (new \DateTime())->add(
                new \DateInterval('PT' . ($accessToken['expires_in'] ?? 0) . 'S')
            )
        );

        if (isset($accessToken['refresh_token'])) {
            $googleAccessToken->setRefreshToken($accessToken['refresh_token']);
        }

        $em->persist($googleAccessToken);
        $em->flush();
    }

    private function revokeToken(?string $token)
    {
        if ($token) {
            $client = new GoogleClient();
            $client->revokeToken($token);
        }
    }
}
