<?php

namespace App\Controller;

use App\Entity\Spotify;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SpotifyController extends AbstractController
{
    private function refreshAccessToken(Spotify $spotify, EntityManagerInterface $entityManager): ?string
    {
        $client = HttpClient::create();
        $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($_ENV['SPOTIFY_CLIENT_ID'] . ':' . $_ENV['SPOTIFY_CLIENT_SECRET']),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $spotify->getRefreshToken(),
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();
            $newAccessToken = $data['access_token'];
            $spotify->setAccessToken($newAccessToken);

            // Zapisz nowy token w bazie danych
            $entityManager->persist($spotify);
            $entityManager->flush();

            return $newAccessToken;
        }

        return null;
    }

    #[Route('/spotify-login', name: 'spotify-login')]
    public function spotifyLogin(): RedirectResponse
    {
        $authorizationScope = 'user-read-currently-playing';
        $authorizationUrl = 'https://accounts.spotify.com/authorize';

        if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
            $serverAddress = 'https://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
        } else {
            $serverAddress = 'https://' . $_ENV['REDIRECT_URL'];
        }
        $redirectUri = $serverAddress . '/spotify-callback';

        $query = http_build_query([
            'client_id' => $_ENV['SPOTIFY_CLIENT_ID'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $authorizationScope,
        ]);

        return $this->redirect($authorizationUrl . '?' . $query);
    }

    #[Route('/spotify-callback', name: 'spotify-callback')]
    public function spotifyCallback(Request $request, EntityManagerInterface $entityManager): Response
    {
        $authorizationCode = $request->query->get('code');

        if ($authorizationCode) {
            if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
                $serverAddress = 'https://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
            } else {
                $serverAddress = 'https://' . $_ENV['REDIRECT_URL'];
            }
            $redirectUri = $serverAddress . '/spotify-callback';

            $client = HttpClient::create();
            $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($_ENV['SPOTIFY_CLIENT_ID'] . ':' . $_ENV['SPOTIFY_CLIENT_SECRET']),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $authorizationCode,
                    'redirect_uri' => $redirectUri,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                $accessToken = $data['access_token'];
                $refreshToken = $data['refresh_token'];

                $allSettings = $entityManager->getRepository(Spotify::class)->findAll();
                foreach ($allSettings as $setting) {
                    $entityManager->remove($setting);
                }
                $entityManager->flush();

                $spotifySettings = new Spotify();
                $spotifySettings->setAccessToken($accessToken);
                $spotifySettings->setRefreshToken($refreshToken);

                $entityManager->persist($spotifySettings);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('settings');
    }

    #[Route('/spotify-logout', name: 'spotify-logout')]
    public function spotifyLogout(EntityManagerInterface $entityManager): RedirectResponse
    {
        $spotify = $entityManager->getRepository(Spotify::class)->findAll();
        if ($spotify) {
            foreach ($spotify as $setting) {
                $entityManager->remove($setting);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('settings', ['tab' => 'spotify-settings']);
    }

    public function getPlayingNow(EntityManagerInterface $entityManager): ?array
    {
        $spotify = $entityManager->getRepository(Spotify::class)->findOneBy([]);
        if ($spotify) {
            $client = HttpClient::create();
            $apiUrl = 'https://api.spotify.com/v1/me/player/currently-playing?market=PL';

            $accessToken = $spotify->getAccessToken();
            if ($accessToken) {
                try {
                    $response = $client->request('GET', $apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                        ],
                    ]);

                    if ($response->getStatusCode() === 401) {
                        $accessToken = $this->refreshAccessToken($spotify, $entityManager);
                        if ($accessToken) {
                            $response = $client->request('GET', $apiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $accessToken,
                                ],
                            ]);
                        } else {
                            return ['error' => 'Could not refresh access token.'];
                        }
                    }
                } catch (TransportExceptionInterface $e) {
                    // Check if the error is related to DNS resolution or network issues
                    if (strpos($e->getMessage(), 'Could not resolve host') !== false) {
                        return ['error' => 'No internet connection.'];
                    }
                    return ['error' => 'Unable to fetch currently playing track. Please check your network connection.'];
                }

                if ($response->getStatusCode() === 200) {
                    $responseData = $response->toArray();
                    $title = $responseData['item']['name'];
                    $artist = $responseData['item']['artists'][0]['name'];
                    $album = $responseData['item']['album']['name'];
                    return [
                        'title' => $title,
                        'artist' => $artist,
                        'album' => $album,
                    ];
                } else {
                    return [
                        'error' => 'Not playing anything right now.',
                    ];
                }
            }
        }

        return null;
    }
}