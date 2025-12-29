<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateScreenService
{
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }

    public function updateScreen(): void
    {
        try {
            $host = $_ENV['EPAPIER_HOST'] ?? 'lokalny.przysiezny.pl';
            // Sanitize host to prevent concatenated garbage
            $host = preg_replace('/[^a-zA-Z0-9\-\.]/', '', $host);
            
            $url = 'http://' . $host . ':5002/updatescreen';
            // error_log('UpdateScreen URL: ' . $url); // Optional debug
            
            $response = $this->httpClient->request('GET', $url);
        } catch (TransportExceptionInterface $e) {
        } finally {

        }
    }
}
