<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getAirQuality(float $lat, float $lng): ?array
    {
        $lat = $this->normalizeCoordinate($lat);
        $lng = $this->normalizeCoordinate($lng);

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://air-quality-api.open-meteo.com/v1/air-quality',
                [
                    'headers' => [
                        'User-Agent' => 'ePapier/1.0',
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'current' => 'european_aqi,pm10,pm2_5,carbon_monoxide',
                    ],
                ]
            );

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $response->toArray();
        } catch (TransportExceptionInterface|
            ServerExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface $e) {
            return null;
        }
    }

    public function getWeatherData($lat, $lng): ?array
    {
        $lat = $this->normalizeCoordinate($lat);
        $lng = $this->normalizeCoordinate($lng);

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.open-meteo.com/v1/forecast',
                [
                    'headers' => [
                        'User-Agent' => 'ePapier/1.0',
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'current_weather' => 'true',
                        // CSV string is more compatible across Symfony HttpClient versions.
                        'daily' => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_probability_mean',
                        'timezone' => 'Europe/Warsaw',
                        'forecast_days' => 3,
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                error_log('WeatherService::getWeatherData status='.$statusCode.' body='.$response->getContent(false));
                return null;
            }

            $weatherData = $response->toArray();
            if (!isset($weatherData['current_weather']) || !is_array($weatherData['current_weather'])) {
                error_log('WeatherService::getWeatherData missing current_weather. Keys='.implode(',', array_keys($weatherData)));
                return null;
            }

            $current = &$weatherData['current_weather'];
            $weatherCode = $current['weathercode'] ?? $current['weather_code'] ?? null;
            if (null === $weatherCode) {
                error_log('WeatherService::getWeatherData missing weather code in current_weather.');
                return null;
            }

            $current['weathercode'] = (int) $weatherCode;
            $current['is_day'] = (int) ($current['is_day'] ?? 1);

            if (!isset($current['wind_speed']) && isset($current['windspeed'])) {
                $current['wind_speed'] = $current['windspeed'];
            }

            $current['icon'] = $this->getWeatherIcon($current['weathercode'], $current['is_day']);
            $weatherData['forecast_data'] = isset($weatherData['daily']) ? $this->processForecast($weatherData['daily']) : [];
            if (empty($weatherData['forecast_data'])) {
                error_log('WeatherService::getWeatherData forecast_data empty. daily_keys='.(isset($weatherData['daily']) && is_array($weatherData['daily']) ? implode(',', array_keys($weatherData['daily'])) : 'none'));
            }

            return $weatherData;
        } catch (TransportExceptionInterface|
            ServerExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface $e) {
            error_log('WeatherService::getWeatherData exception '.get_class($e).': '.$e->getMessage());
            return null;
        }
    }

    private function getWeatherIcon(int $weatherCode, int $isDay = 1): string
    {
        $iconMapDay = [
            0 => 'wi-day-sunny',
            1 => 'wi-cloud',
            2 => 'wi-cloudy',
            3 => 'wi-cloudy',
            45 => 'wi-fog',
            48 => 'wi-fog',
            51 => 'wi-sprinkle',
            53 => 'wi-sprinkle',
            55 => 'wi-sprinkle',
            56 => 'wi-sleet',
            57 => 'wi-sleet',
            61 => 'wi-rain',
            63 => 'wi-rain',
            65 => 'wi-rain',
            66 => 'wi-sleet',
            67 => 'wi-sleet',
            71 => 'wi-snow',
            73 => 'wi-snow',
            75 => 'wi-snow',
            77 => 'wi-snow-wind',
            80 => 'wi-showers',
            81 => 'wi-showers',
            82 => 'wi-showers',
            85 => 'wi-snow',
            86 => 'wi-snow',
            95 => 'wi-thunderstorm',
            96 => 'wi-thunderstorm',
            99 => 'wi-thunderstorm',
        ];

        $iconMapNight = [
            0 => 'wi-night-clear',
            1 => 'wi-night-alt-cloudy',
            2 => 'wi-night-alt-cloudy',
            3 => 'wi-night-alt-cloudy',
            45 => 'wi-night-fog',
            48 => 'wi-night-fog',
            51 => 'wi-night-alt-sprinkle',
            53 => 'wi-night-alt-sprinkle',
            55 => 'wi-night-alt-sprinkle',
            56 => 'wi-night-alt-sleet',
            57 => 'wi-night-alt-sleet',
            61 => 'wi-night-alt-rain',
            63 => 'wi-night-alt-rain',
            65 => 'wi-night-alt-rain',
            66 => 'wi-night-alt-sleet',
            67 => 'wi-night-alt-sleet',
            71 => 'wi-night-alt-snow',
            73 => 'wi-night-alt-snow',
            75 => 'wi-night-alt-snow',
            77 => 'wi-night-alt-snow-wind',
            80 => 'wi-night-alt-showers',
            81 => 'wi-night-alt-showers',
            82 => 'wi-night-alt-showers',
            85 => 'wi-night-alt-snow',
            86 => 'wi-night-alt-snow',
            95 => 'wi-night-alt-thunderstorm',
            96 => 'wi-night-alt-thunderstorm',
            99 => 'wi-night-alt-thunderstorm',
        ];

        $iconMap = $isDay ? $iconMapDay : $iconMapNight;

        return $iconMap[$weatherCode] ?? 'wi-na';
    }

    private function processForecast(array $dailyData): array
    {
        if (
            !isset($dailyData['time'], $dailyData['temperature_2m_max'], $dailyData['temperature_2m_min'], $dailyData['weather_code'])
            || !is_array($dailyData['time'])
        ) {
            return [];
        }

        $forecast = [];
        for ($i = 1; $i < count($dailyData['time']); ++$i) {
            $forecast[] = [
                'label' => date('d M', strtotime($dailyData['time'][$i])),
                'temperature_max' => $dailyData['temperature_2m_max'][$i] ?? null,
                'temperature_min' => $dailyData['temperature_2m_min'][$i] ?? null,
                'weather_code' => $this->getWeatherIcon((int) ($dailyData['weather_code'][$i] ?? 0)),
                'precipitation_probability' => $dailyData['precipitation_probability_mean'][$i] ?? 0,
            ];
        }

        return $forecast;
    }

    private function normalizeCoordinate($value): float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return (float) $value;
    }
}
