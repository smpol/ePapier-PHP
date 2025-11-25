<?php

namespace App\Service;

use App\Entity\SelectedCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar as GoogleCalendar;
use Psr\Log\LoggerInterface;

class GoogleCalendarService
{
    private GoogleClientService $googleClientService;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        GoogleClientService $googleClientService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->googleClientService = $googleClientService;
        $this->em = $em;
        $this->logger = $logger;
    }

    public function getEvents(): ?array
    {
        try {
            $client = $this->googleClientService->getClient();
            $service = new GoogleCalendar($client);

            $selectedCalendars = $this->em->getRepository(SelectedCalendar::class)->findAll();

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
                    $this->logger->error("Failed to fetch events for calendar {$calendar->getCalendarId()}: ".$e->getMessage());
                    continue;
                }
            }

            usort($events, function ($a, $b) {
                return new \DateTime($a['start']) <=> new \DateTime($b['start']);
            });

            return array_slice($events, 0, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Google Calendar events: '.$e->getMessage());

            return null;
        }
    }

    public function getCalendarList(): array
    {
        $client = $this->googleClientService->getClient();
        $service = new GoogleCalendar($client);

        return $service->calendarList->listCalendarList()->getItems();
    }
}
