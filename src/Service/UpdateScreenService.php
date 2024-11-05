<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class UpdateScreenService
{
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        // Tworzenie instancji klienta HTTP automatycznie
        $this->httpClient = HttpClient::create();
    }

    public function updateScreen(): void
    {
        try {
            $response = $this->httpClient->request('GET', 'http://' . $_ENV['REDIRECT_URL'] . ':5002/updatescreen');
            //print response
//            dd($response->toArray());
        } catch (TransportExceptionInterface $e) {
            // Obsłuż wyjątek lub zaloguj, jeśli to konieczne
//            dd($e->getMessage());/
        }
    }
}
