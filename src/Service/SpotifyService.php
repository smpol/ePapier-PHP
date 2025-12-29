<?php

namespace App\Service;

use App\Entity\Spotify;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyService
{
    private EntityManagerInterface $em;
    private SpotifyClientFactory $clientFactory;
    private LoggerInterface $logger;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUrl;

    public function __construct(
        EntityManagerInterface $em,
        SpotifyClientFactory $clientFactory,
        LoggerInterface $logger,
        string $clientId,
        string $clientSecret,
        string $redirectUrl
    ) {
        $this->em = $em;
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
    }

    public function getPlayingNow(): ?array
    {
        $spotify = $this->em->getRepository(Spotify::class)->findOneBy([]);
        if (!$spotify) {
            return null;
        }

        $client = $this->clientFactory->createClient();
        $apiUrl = 'https://api.spotify.com/v1/me/player/currently-playing?market=PL';

        try {
            $response = $client->request('GET', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.$spotify->getAccessToken(),
                ],
            ]);

            if (401 === $response->getStatusCode()) {
                $newAccessToken = $this->refreshAccessToken($spotify);
                if ($newAccessToken) {
                    $response = $client->request('GET', $apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer '.$newAccessToken,
                        ],
                    ]);
                } else {
                    return ['error' => 'Could not refresh access token.'];
                }
            }

            if (200 === $response->getStatusCode()) {
                $responseData = $response->toArray();
                $title = $responseData['item']['name'];
                $artists = array_map(fn ($artist) => $artist['name'], $responseData['item']['artists']);
                $album = $responseData['item']['album']['name'];

                return [
                    'title' => $title,
                    'artists' => $artists,
                    'album' => $album,
                ];
            } else {
                return ['error' => 'Not playing anything right now.'];
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Spotify API error: '.$e->getMessage());

            return ['error' => 'Unable to fetch currently playing track.'];
        }
    }

    private function refreshAccessToken(Spotify $spotify): ?string
    {
        $client = $this->clientFactory->createClient();

        try {
            $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $spotify->getRefreshToken(),
                ],
            ]);

            if (200 === $response->getStatusCode()) {
                $data = $response->toArray();
                $newAccessToken = $data['access_token'];
                $spotify->setAccessToken($newAccessToken);
                $this->em->flush();

                return $newAccessToken;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh Spotify access token: '.$e->getMessage());
        }

        return null;
    }

    public function handleCallback(string $authorizationCode, ?string $redirectUri = null, ?string $codeVerifier = null): void
    {
        $client = $this->clientFactory->createClient();
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
                    'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]);

            if (200 === $response->getStatusCode()) {
                $data = $response->toArray();
                $accessToken = $data['access_token'];
                $refreshToken = $data['refresh_token'];

                $this->em->createQuery('DELETE FROM App\Entity\Spotify')->execute();

                $spotifySettings = new Spotify();
                $spotifySettings->setAccessToken($accessToken);
                $spotifySettings->setRefreshToken($refreshToken);

                $this->em->persist($spotifySettings);
                $this->em->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Spotify callback error: '.$e->getMessage());
        }
    }
}
