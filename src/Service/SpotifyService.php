<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Spotify;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SpotifyService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUrl;
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $em;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        HttpClientInterface $httpClient,
        EntityManagerInterface $em
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
        $this->httpClient = $httpClient;
        $this->em = $em;
    }

    public function getAuthorizationUrl(): string
    {
        $scope = [
            'user-read-currently-playing',
            'user-read-playback-state',
        ];

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(' ', $scope),
        ];

        return 'https://accounts.spotify.com/authorize?' . http_build_query($params);
    }

    public function handleCallback(string $authorizationCode, ?string $redirectUri = null, ?string $codeVerifier = null): void
    {
        $client = $this->httpClient;
        $redirectUrl = $redirectUri ?? $this->redirectUrl;

        $body = [
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
            'redirect_uri' => $redirectUrl,
        ];

        if ($codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        try {
            $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]);

            if (200 === $response->getStatusCode()) {
                $data = $response->toArray();
                $this->saveToken($data);
            }
        } catch (\Exception $e) {
            // Check for specific error response from Spotify
             if (method_exists($e, 'getResponse') && $e->getResponse()) {
                 try {
                     $errorData = $e->getResponse()->toArray(false);
                     error_log('Spotify Token Error: ' . print_r($errorData, true));
                 } catch (\Exception $decodeError) {
                     error_log('Spotify Token Error (Raw): ' . $e->getMessage());
                 }
             } else {
                 error_log('Spotify Token Exchange Failed: ' . $e->getMessage());
             }
             throw $e;
        }
    }

    public function getAccessToken(): ?string
    {
        $spotify = $this->em->getRepository(Spotify::class)->findOneBy([], ['id' => 'DESC']);

        if (!$spotify) {
            return null;
        }

        if ($spotify->getExpiresAt() < new \DateTime()) {
            $this->refreshToken($spotify);
        }

        return $spotify->getAccessToken();
    }

    public function getPlayingNow(): ?array
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return null;
        }

        $client = $this->httpClient;

        try {
            $response = $client->request('GET', 'https://api.spotify.com/v1/me/player/currently-playing', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            if (200 === $response->getStatusCode()) {
                return $response->toArray();
            }

            if (204 === $response->getStatusCode()) {
                return null; // Nothing playing
            }

        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    private function saveToken(array $data): void
    {
        $spotify = $this->em->getRepository(Spotify::class)->findOneBy([], ['id' => 'DESC']);

        if (!$spotify) {
            $spotify = new Spotify();
        }

        $spotify->setAccessToken($data['access_token']);
        $spotify->setRefreshToken($data['refresh_token'] ?? $spotify->getRefreshToken());
        $spotify->setExpiresAt((new \DateTime())->modify('+' . $data['expires_in'] . ' seconds'));

        $this->em->persist($spotify);
        $this->em->flush();
    }

    private function refreshToken(Spotify $spotify): void
    {
        $client = $this->httpClient;

        try {
            $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $spotify->getRefreshToken(),
                ],
            ]);

            if (200 === $response->getStatusCode()) {
                $data = $response->toArray();
                $spotify->setAccessToken($data['access_token']);
                $spotify->setExpiresAt((new \DateTime())->modify('+' . $data['expires_in'] . ' seconds'));
                
                if (isset($data['refresh_token'])) {
                    $spotify->setRefreshToken($data['refresh_token']);
                }

                $this->em->persist($spotify);
                $this->em->flush();
            }
        } catch (\Exception $e) {
            // Handle refresh error
        }
    }
}
