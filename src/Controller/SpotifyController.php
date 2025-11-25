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
    private SpotifyService $spotifyService;
    private EntityManagerInterface $em;

    public function __construct(SpotifyService $spotifyService, EntityManagerInterface $em)
    {
        $this->spotifyService = $spotifyService;
        $this->em = $em;
    }

    #[Route('/spotify-login', name: 'spotify-login')]
    public function spotifyLogin(): RedirectResponse
    {
        $authorizationScope = 'user-read-currently-playing';
        $authorizationUrl = 'https://accounts.spotify.com/authorize';

        $redirectUri = $this->generateUrl('spotify-callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $query = http_build_query([
            'client_id' => $this->getParameter('env(SPOTIFY_CLIENT_ID)'),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $authorizationScope,
        ]);

        return $this->redirect($authorizationUrl.'?'.$query);
    }

    #[Route('/spotify-callback', name: 'spotify-callback')]
    public function spotifyCallback(Request $request): Response
    {
        $authorizationCode = $request->query->get('code');

        if ($authorizationCode) {
            $this->spotifyService->handleCallback($authorizationCode);
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
