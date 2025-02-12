<?php

namespace App\Service;

use App\Entity\SolarEdge;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SolarEdgeService
{
    private $httpClient;
    private $logger;
    private $encryptionService;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, OpenSSLEncryptionSerivce $encryptionService)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->encryptionService = $encryptionService;
    }

    /**
     * Pobiera dane z SolarEdge API.
     */
    public function fetchSolarEdgeData(string $apiKey, string $siteId): ?array
    {
        try {
            $url = "https://monitoringapi.solaredge.com/site/$siteId/overview?api_key=$apiKey";
            $this->logger->info("Fetching SolarEdge data from URL: $url");

            // Wysyłamy zapytanie do API
            $response = $this->httpClient->request('GET', $url);

            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Failed to fetch SolarEdge data: HTTP '.$response->getStatusCode());

                return null;
            }

            // Dekodowanie odpowiedzi
            $data = $response->toArray();
            $this->logger->info('SolarEdge response data', $data);

            // Przetwarzanie danych (konwersja energii do kWh)
            $data['overview']['currentPower']['power'] = round($data['overview']['currentPower']['power'] / 1000, 2);
            $data['overview']['lastDayData']['energy'] = round($data['overview']['lastDayData']['energy'] / 1000, 2);
            $data['overview']['lastMonthData']['energy'] = round($data['overview']['lastMonthData']['energy'] / 1000, 2);
            $data['overview']['lastYearData']['energy'] = round($data['overview']['lastYearData']['energy'] / 1000, 2);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching SolarEdge data: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Zwraca dane SolarEdge z bazy danych i API.
     */
    public function getSolarEdgeData(EntityManagerInterface $entityManager): ?array
    {
        // Pobieramy rekord SolarEdge z bazy danych
        $this->logger->info('Retrieving SolarEdge configuration from database...');
        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);

        if (!$solarEdge) {
            $this->logger->warning('No SolarEdge configuration found in database.');

            return null;
        }

        // Deszyfrujemy klucz API
        try {
            $apiKey = $this->encryptionService->decrypt($solarEdge->getApiKey());
            $siteId = $solarEdge->getSiteId();
            $this->logger->info('Using decrypted API key and Site ID for SolarEdge data.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to decrypt SolarEdge API key: '.$e->getMessage());

            return null;
        }

        // Pobieramy dane z API
        $this->logger->info('Attempting to fetch SolarEdge data...');
        $data = $this->fetchSolarEdgeData($apiKey, $siteId);

        if (null === $data) {
            $this->logger->error('Failed to retrieve SolarEdge data from API.');
        } else {
            $this->logger->info('SolarEdge data successfully retrieved.');
        }

        return $data;
    }

    /**
     * Pobiera tygodniową produkcję energii dla bieżącego i czterech poprzednich tygodni.
     */
    public function fetchWeeklyProductionData(string $apiKey, string $siteId): array
    {
        $weeklyProduction = [];
        $currentDate = new \DateTime();

        for ($i = 0; $i < 5; ++$i) {
            $endDate = $currentDate->format('Y-m-d');
            $startDate = $currentDate->modify('-7 days')->format('Y-m-d');

            $url = "https://monitoringapi.solaredge.com/site/$siteId/timeFrameEnergy?startDate=$startDate&endDate=$endDate&api_key=$apiKey";
            $this->logger->info("Fetching weekly production data from URL: $url");

            try {
                $response = $this->httpClient->request('GET', $url);
                if (200 === $response->getStatusCode()) {
                    $data = $response->toArray();
                    $energyProduced = $data['timeFrameEnergy']['energy'] ?? 0;
                    $weeklyProduction[] = [
                        'week' => 0 === $i ? 'Wk' : "Wk -$i",
                        'energyProduced' => round($energyProduced / 1000, 2), // Convert to kWh
                    ];
                } else {
                    $this->logger->error("Failed to fetch weekly production data for week -$i: HTTP ".$response->getStatusCode());
                }
            } catch (\Exception $e) {
                $this->logger->error("Error fetching weekly production data for week -$i: ".$e->getMessage());
            }
        }
        // odwórć tablicę
        $weeklyProduction = array_reverse($weeklyProduction);

        return $weeklyProduction;
    }

    public function getSolarEdgeDataWeekly(EntityManagerInterface $entityManager): ?array
    {
        // Pobieramy rekord SolarEdge z bazy danych
        $this->logger->info('Retrieving SolarEdge configuration from database...');
        $solarEdge = $entityManager->getRepository(SolarEdge::class)->findOneBy([], ['id' => 'DESC']);

        if (!$solarEdge) {
            $this->logger->warning('No SolarEdge configuration found in database.');

            return null;
        }

        // Deszyfrujemy klucz API
        try {
            $apiKey = $this->encryptionService->decrypt($solarEdge->getApiKey());
            $siteId = $solarEdge->getSiteId();
            $this->logger->info('Using decrypted API key and Site ID for SolarEdge data.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to decrypt SolarEdge API key: '.$e->getMessage());

            return null;
        }

        // Pobieramy dane z API
        $this->logger->info('Attempting to fetch SolarEdge data...');
        $data = $this->fetchWeeklyProductionData($apiKey, $siteId);

        if (null === $data) {
            $this->logger->error('Failed to retrieve SolarEdge data from API.');
        } else {
            $this->logger->info('SolarEdge data successfully retrieved.');
        }

        return $data;
    }
}
