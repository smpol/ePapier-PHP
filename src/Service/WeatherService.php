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
     * Pobiera dane pogodowe dla podanej lokalizacji.
     */
    public function getAirQuality(float $lat, float $lng): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://air-quality-api.open-meteo.com/v1/air-quality', [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'current' => 'european_aqi,pm10,pm2_5,carbon_monoxide',
                ]
            ]);

            // Check if the response status is 200, otherwise return null
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $airQuality = $response->toArray();

            return $airQuality;

        } catch (TransportExceptionInterface | ServerExceptionInterface | ClientExceptionInterface | RedirectionExceptionInterface $e) {
            // Log the exception or handle it as needed, then return null
            return null;
        }
    }

    public function getWeatherData(float $lat, float $lng): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'current_weather' => true,
                    'daily' => ['temperature_2m_max', 'temperature_2m_min', 'weather_code', 'precipitation_probability_mean'],
                    'timezone' => 'Europe/Warsaw',
                    'forecast_days' => 3,
                ]
            ]);

            // Check if the response status is 200, otherwise return null
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $weatherData = $response->toArray();

            // Add weather icon based on the weather code
            $weatherData['current_weather']['icon'] = $this->getWeatherIcon($weatherData['current_weather']['weathercode']);

            // Process the forecast data
            $weatherData['forecast_data'] = $this->processForecast($weatherData['daily']);

            return $weatherData;

        } catch (TransportExceptionInterface | ServerExceptionInterface | ClientExceptionInterface | RedirectionExceptionInterface $e) {
            // Log the exception or handle it as needed, then return null
            return null;
        }
    }

    /**
     * Zwraca ikonę pogody na podstawie kodu pogodowego.
     */
    private function getWeatherIcon(int $weatherCode): string
    {
        $iconMap = [
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

        return $iconMap[$weatherCode] ?? 'wi-na';
    }

    /**
     * Przetwarza dane prognozy pogody, aby dopasować do szablonu.
     */
    private function processForecast(array $dailyData): array
    {
        $forecast = [];
        for ($i = 1; $i < count($dailyData['time']); $i++) {
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