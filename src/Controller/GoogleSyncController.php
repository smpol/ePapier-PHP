<?php

namespace App\Controller;

use App\Entity\SelectedCalendar;
use App\Service\GoogleCalendarService;
use App\Service\GoogleClientService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleSyncController extends AbstractController
{
    private GoogleClientService $googleClientService;
    private GoogleCalendarService $googleCalendarService;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        GoogleClientService $googleClientService,
        GoogleCalendarService $googleCalendarService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->googleClientService = $googleClientService;
        $this->googleCalendarService = $googleCalendarService;
        $this->em = $em;
        $this->logger = $logger;
    }

    #[Route('/google-login', name: 'google-login')]
    public function googleLogin(): Response
    {
        $authUrl = $this->googleClientService->createAuthUrl();

        return $this->redirect($authUrl);
    }

    #[Route('/google-callback', name: 'google-callback')]
    public function googleCallback(Request $request): Response
    {
        $authCode = $request->query->get('code');

        if (!$authCode) {
            $this->logger->error('Authorization code not found');
            $this->addFlash('error', 'Authorization code not found.');

            return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
        }

        try {
            $tokenResponse = $this->googleClientService->fetchAccessTokenWithAuthCode($authCode);

            if (isset($tokenResponse['error'])) {
                $this->logger->error('Google API error: '.$tokenResponse['error_description']);
                $this->addFlash('error', 'Google API error: '.$tokenResponse['error_description']);

                return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
            }

            $this->googleClientService->storeAccessToken($tokenResponse);

            $calendars = $this->googleCalendarService->getCalendarList();

            return $this->render('select_calendars.html.twig', [
                'calendars' => $calendars,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process Google callback: '.$e->getMessage());
            $this->addFlash('error', 'Failed to process Google callback.');

            return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
        }
    }

    #[Route('/google-save-calendar', name: 'google-save-calendar', methods: ['POST'])]
    public function saveSelectedCalendars(Request $request): Response
    {
        $selectedCalendars = $request->request->all('calendars');

        if (!is_array($selectedCalendars) || empty($selectedCalendars)) {
            $this->addFlash('error', 'Invalid or empty calendar selection.');

            return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
        }

        $this->em->createQuery('DELETE FROM App\Entity\SelectedCalendar')->execute();

        foreach ($selectedCalendars as $calendarId) {
            if (!is_scalar($calendarId)) {
                continue;
            }

            $selectedCalendar = new SelectedCalendar();
            $selectedCalendar->setCalendarId((string) $calendarId);
            $selectedCalendar->setCalendarName((string) $calendarId);

            $this->em->persist($selectedCalendar);
        }

        $this->em->flush();
        $this->addFlash('success', 'Calendar selection saved.');

        return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
    }

    #[Route('/google-remove-calendar', name: 'google-remove-calendar')]
    public function removeGoogle(): Response
    {
        try {
            $this->googleClientService->revokeToken();

            $selectedCalendars = $this->em->getRepository(SelectedCalendar::class)->findAll();
            foreach ($selectedCalendars as $calendar) {
                $this->em->remove($calendar);
            }

            $this->em->flush();
            $this->addFlash('success', 'Google account disconnected.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove Google integration: '.$e->getMessage());
            $this->addFlash('error', 'Failed to remove Google integration.');
        }

        return $this->redirectToRoute('settings', ['tab' => 'google-settings']);
    }

    public function getEvents(): ?array
    {
        try {
            return $this->googleCalendarService->getEvents();
        } catch (\Exception $e) {
            $this->logger->error('Error in getEvents: '.$e->getMessage());

            return null;
        }
    }
}
