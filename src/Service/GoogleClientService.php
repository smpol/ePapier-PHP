<?php

namespace App\Service;

use App\Entity\GoogleAccessToken;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class GoogleClientService
{
    private GoogleClient $client;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private RouterInterface $router;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        RouterInterface $router,
        string $googleClientId,
        string $googleClientSecret,
        string $redirectUrl
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->router = $router;

        $this->client = new GoogleClient();
        $this->client->setClientId($googleClientId);
        $this->client->setClientSecret($googleClientSecret);
        $this->client->setRedirectUri($this->router->generate('google-callback', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->client->addScope('https://www.googleapis.com/auth/calendar.readonly');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setHttpClient(new \GuzzleHttp\Client(['timeout' => 10]));
    }

    public function createAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function fetchAccessTokenWithAuthCode(string $authCode): array
    {
        try {
            return $this->client->fetchAccessTokenWithAuthCode($authCode);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch access token with auth code: '.$e->getMessage());

            throw new \RuntimeException('Failed to fetch access token.');
        }
    }

    public function getClient(): GoogleClient
    {
        $googleAccessToken = $this->em->getRepository(GoogleAccessToken::class)->findOneBy([], ['id' => 'DESC']);

        if (!$googleAccessToken) {
            return $this->client;
        }

        $this->client->setAccessToken($googleAccessToken->getAccessToken());

        if ($this->client->isAccessTokenExpired()) {
            $this->refreshAccessToken($googleAccessToken);
        }

        return $this->client;
    }

    private function refreshAccessToken(GoogleAccessToken $googleAccessToken): void
    {
        $refreshToken = $googleAccessToken->getRefreshToken();

        if (!$refreshToken) {
            $this->logger->error('No refresh token available.');

            throw new \RuntimeException('No refresh token available.');
        }

        try {
            $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newAccessToken['access_token'])) {
                $googleAccessToken->setAccessToken($newAccessToken['access_token']);
                $googleAccessToken->setExpiresAt(
                    (new \DateTime())->setTimestamp(time() + ($newAccessToken['expires_in'] ?? 3600))
                );

                if (isset($newAccessToken['refresh_token'])) {
                    $googleAccessToken->setRefreshToken($newAccessToken['refresh_token']);
                }

                $this->em->flush();
            } else {
                $this->logger->error('Failed to refresh access token.');

                throw new \RuntimeException('Failed to refresh access token.');
            }
        } catch (GoogleServiceException $e) {
            $this->logger->error('Error refreshing access token: '.$e->getMessage());

            throw new \RuntimeException('Error refreshing access token.');
        }
    }

    public function storeAccessToken(array $accessToken): void
    {
        $googleAccessToken = $this->em->getRepository(GoogleAccessToken::class)->findOneBy([], ['id' => 'DESC']) ?? new GoogleAccessToken();

        $googleAccessToken->setAccessToken($accessToken['access_token']);
        $googleAccessToken->setExpiresAt(
            (new \DateTime())->setTimestamp(time() + ($accessToken['expires_in'] ?? 3600))
        );

        if (isset($accessToken['refresh_token'])) {
            $googleAccessToken->setRefreshToken($accessToken['refresh_token']);
        }

        $this->em->persist($googleAccessToken);
        $this->em->flush();
    }

    public function revokeToken(): void
    {
        $googleAccessToken = $this->em->getRepository(GoogleAccessToken::class)->findOneBy([], ['id' => 'DESC']);

        if ($googleAccessToken && $googleAccessToken->getAccessToken()) {
            try {
                $this->client->revokeToken($googleAccessToken->getAccessToken());
            } catch (\Exception $e) {
                $this->logger->error('Failed to revoke token: '.$e->getMessage());
            }

            $this->em->remove($googleAccessToken);
            $this->em->flush();
        }
    }
}
