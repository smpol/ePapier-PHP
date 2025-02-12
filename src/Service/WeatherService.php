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

    /**
     * Pobiera dane dotyczące jakości powietrza.
     *
     * @param float $lat szerokość geograficzna
     * @param float $lng długość geograficzna
     *
     * @return array|null dane dotyczące jakości powietrza lub null w przypadku błędu
     */
    public function getAirQuality(float $lat, float $lng): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://air-quality-api.open-meteo.com/v1/air-quality',
                [
                    'query' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'current' => 'european_aqi,pm10,pm2_5,carbon_monoxide',
                    ],
                ]
            );

            // Check if the response status is 200, otherwise return null
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $airQuality = $response->toArray();

            return $airQuality;
        } catch (TransportExceptionInterface|
            ServerExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface $e) {
                // If expected error occurs return null
                return null;
            }
    }

    public function getWeatherData($lat, $lng): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.open-meteo.com/v1/forecast',
                [
                    'query' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'current_weather' => true,
                        'daily' => [
                            'temperature_2m_max',
                            'temperature_2m_min',
                            'weather_code',
                            'precipitation_probability_mean',
                        ],
                        'timezone' => 'Europe/Warsaw',
                        'forecast_days' => 3,
                    ],
                ]
            );

            // Check if the response status is 200, otherwise return null
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $weatherData = $response->toArray();

            // Add weather icon based on the weather code
            $weatherData['current_weather']['icon'] = $this->getWeatherIcon(
                $weatherData['current_weather']['weathercode'],
                $weatherData['current_weather']['is_day']
            );

            // Process the forecast data
            $weatherData['forecast_data'] = $this->processForecast($weatherData['daily']);

            return $weatherData;
        } catch (TransportExceptionInterface|
            ServerExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface $e) {
                // If expected error occurs return null
                return null;
            }
    }

    /**
     * Zwraca ikonę pogody na podstawie kodu pogodowego.
     */
    private function getWeatherIcon(int $weatherCode, int $isDay = 1): string
    {
        $iconMapDay = [
            // Clear sky
            0 => 'wi-day-sunny',

            // Mainly clear, partly cloudy, overcast
            1 => 'wi-cloud',
            2 => 'wi-cloudy',
            3 => 'wi-cloudy',

            // Fog and depositing rime fog
            45 => 'wi-fog',
            48 => 'wi-fog',

            // Drizzle: Light, moderate, dense intensity
            51 => 'wi-sprinkle',
            53 => 'wi-sprinkle',
            55 => 'wi-sprinkle',

            // Freezing Drizzle: Light and dense intensity
            56 => 'wi-sleet',
            57 => 'wi-sleet',

            // Rain: Slight, moderate, heavy intensity
            61 => 'wi-rain',
            63 => 'wi-rain',
            65 => 'wi-rain',

            // Freezing Rain: Light and heavy intensity
            66 => 'wi-sleet',
            67 => 'wi-sleet',

            // Snow fall: Slight, moderate, heavy intensity
            71 => 'wi-snow',
            73 => 'wi-snow',
            75 => 'wi-snow',

            // Snow grains
            77 => 'wi-snow-wind',

            // Rain showers: Slight, moderate, violent
            80 => 'wi-showers',
            81 => 'wi-showers',
            82 => 'wi-showers',

            // Snow showers slight and heavy
            85 => 'wi-snow',
            86 => 'wi-snow',

            // Thunderstorm: Slight or moderate
            95 => 'wi-thunderstorm',

            // Thunderstorm with slight and heavy hail
            96 => 'wi-thunderstorm',
            99 => 'wi-thunderstorm',
        ];

        $iconMapNight = [
            // Clear sky
            0 => 'wi-night-clear',

            // Mainly clear, partly cloudy, overcast
            1 => 'wi-night-alt-cloudy',
            2 => 'wi-night-alt-cloudy',
            3 => 'wi-night-alt-cloudy',

            // Fog and depositing rime fog
            45 => 'wi-night-fog',
            48 => 'wi-night-fog',

            // Drizzle: Light, moderate, dense intensity
            51 => 'wi-night-alt-sprinkle',
            53 => 'wi-night-alt-sprinkle',
            55 => 'wi-night-alt-sprinkle',

            // Freezing Drizzle: Light and dense intensity
            56 => 'wi-night-alt-sleet',
            57 => 'wi-night-alt-sleet',

            // Rain: Slight, moderate, heavy intensity
            61 => 'wi-night-alt-rain',
            63 => 'wi-night-alt-rain',
            65 => 'wi-night-alt-rain',

            // Freezing Rain: Light and heavy intensity
            66 => 'wi-night-alt-sleet',
            67 => 'wi-night-alt-sleet',

            // Snow fall: Slight, moderate, heavy intensity
            71 => 'wi-night-alt-snow',
            73 => 'wi-night-alt-snow',
            75 => 'wi-night-alt-snow',

            // Snow grains
            77 => 'wi-night-alt-snow-wind',

            // Rain showers: Slight, moderate, violent
            80 => 'wi-night-alt-showers',
            81 => 'wi-night-alt-showers',
            82 => 'wi-night-alt-showers',

            // Snow showers slight and heavy
            85 => 'wi-night-alt-snow',
            86 => 'wi-night-alt-snow',

            // Thunderstorm: Slight or moderate
            95 => 'wi-night-alt-thunderstorm',

            // Thunderstorm with slight and heavy hail
            96 => 'wi-night-alt-thunderstorm',
            99 => 'wi-night-alt-thunderstorm',
        ];

        if ($isDay) {
            $iconMap = $iconMapDay;
        } else {
            $iconMap = $iconMapNight;
        }

        return $iconMap[$weatherCode] ?? 'wi-na';
    }

    /**
     * Przetwarza dane prognozy pogody, aby dopasować do szablonu.
     */
    private function processForecast(array $dailyData): array
    {
        $forecast = [];
        for ($i = 1; $i < count($dailyData['time']); ++$i) {
            $forecast[] = [
                'label' => date('d M', strtotime($dailyData['time'][$i])), // Data
                'temperature_max' => $dailyData['temperature_2m_max'][$i], // Maksymalna temperatura
                'temperature_min' => $dailyData['temperature_2m_min'][$i], // Minimalna temperatura
                'weather_code' => $this->getWeatherIcon($dailyData['weather_code'][$i]), // Ikona pogody
                'precipitation_probability' => $dailyData['precipitation_probability_mean'][$i], // Prawdopodobieństwo opadów
            ];
        }

        return $forecast;
    }
}
