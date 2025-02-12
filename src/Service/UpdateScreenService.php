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
            $response = $this->httpClient->request('GET', 'http://'.$_ENV['REDIRECT_URL'].':5002/updatescreen');
        } catch (TransportExceptionInterface $e) {
        }
    }
}
