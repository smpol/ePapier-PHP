<?php

namespace App\Controller;

use App\Entity\Spotify;
use App\Service\SpotifyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpotifyController extends AbstractController
{
    private string $clientId;
    private SpotifyService $spotifyService;
    private EntityManagerInterface $em;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(string $clientId, SpotifyService $spotifyService, EntityManagerInterface $em, \Psr\Log\LoggerInterface $logger)
    {
        $this->clientId = $clientId;
        $this->spotifyService = $spotifyService;
        $this->em = $em;
        $this->logger = $logger;
        $this->logger->info('SpotifyController initialized with Client ID: ' . ($this->clientId ? 'SET' : 'EMPTY'));
    }

    #[Route('/spotify-login', name: 'spotify-login')]
    public function spotifyLogin(Request $request): RedirectResponse
    {
        $authorizationScope = 'user-read-currently-playing';
        $authorizationUrl = 'https://accounts.spotify.com/authorize';

        // 1. Generate code_verifier
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');

        // 2. Generate code_challenge
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        // 3. Store code_verifier in session
        $session = $request->getSession();
        $session->set('spotify_code_verifier', $codeVerifier);

        $envRedirectUrl = $_ENV['REDIRECT_URL'] ?? null;
        if ($envRedirectUrl && filter_var($envRedirectUrl, FILTER_VALIDATE_URL)) {
             $redirectUri = $envRedirectUrl;
        } else {
             $redirectUri = $this->generateUrl('spotify-callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
             // Spotify does not allow 'localhost' as a redirect URI, it must be '127.0.0.1'
             $redirectUri = str_replace('localhost', '127.0.0.1', $redirectUri);
        }

        $this->logger->info('Spotify Authorization Redirect URI: ' . $redirectUri);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $authorizationScope,
            'code_challenge_method' => 'S256',
            'code_challenge' => $codeChallenge,
        ]);

        return $this->redirect($authorizationUrl.'?'.$query);
    }

    #[Route('/spotify-callback', name: 'spotify-callback')]
    public function spotifyCallback(Request $request): Response
    {
        $authorizationCode = $request->query->get('code');

        if ($authorizationCode) {
            // Retrieve code_verifier from session
            $session = $request->getSession();
            $codeVerifier = $session->get('spotify_code_verifier');

            if (!$codeVerifier) {
                // Log error if verifier is missing
                $this->logger->error('Spotify PKCE code_verifier missing in session.');
                // Optionally handle error (flash message, redirect)
            }

            $envRedirectUrl = $_ENV['REDIRECT_URL'] ?? null;
            if ($envRedirectUrl && filter_var($envRedirectUrl, FILTER_VALIDATE_URL)) {
                 $redirectUri = $envRedirectUrl;
            } else {
                 $redirectUri = $this->generateUrl('spotify-callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
                 // Spotify does not allow 'localhost' as a redirect URI, it must be '127.0.0.1'
                 $redirectUri = str_replace('localhost', '127.0.0.1', $redirectUri);
            }
            $this->spotifyService->handleCallback($authorizationCode, $redirectUri, $codeVerifier);
        }

        return $this->redirectToRoute('settings', ['tab' => 'spotify-settings']);
    }

    #[Route('/spotify-logout', name: 'spotify-logout')]
    public function spotifyLogout(): RedirectResponse
    {
        $spotify = $this->em->getRepository(Spotify::class)->findAll();
        if ($spotify) {
            foreach ($spotify as $setting) {
                $this->em->remove($setting);
            }
            $this->em->flush();
        }

        return $this->redirectToRoute('settings', ['tab' => 'spotify-settings']);
    }

    public function getPlayingNow(): ?array
    {
        return $this->spotifyService->getPlayingNow();
    }
}