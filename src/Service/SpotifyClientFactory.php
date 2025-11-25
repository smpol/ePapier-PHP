<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyClientFactory
{
    public function createClient(): HttpClientInterface
    {
        return HttpClient::create();
    }
}
