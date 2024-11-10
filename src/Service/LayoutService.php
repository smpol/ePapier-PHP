<?php

namespace App\Service;

class LayoutService
{
    public function getAvailableComponents(): array
    {
        return [
            'CurrentWeather' => 'Current Weather',
            'Forecast' => 'Weather Forecast',
            'Spotify' => 'Spotify',
            'GoogleCalendar' => 'Google Calendar',
            'Emails' => 'Emails',
            'SolarEdge' => 'SolarEdge',
            'SolarEdgeChart' => 'SolarEdge Chart',
            'AirQuality' => 'Air Quality',
            'Countdown' => 'Countdown',
        ];
    }
}