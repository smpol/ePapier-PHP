<?php

namespace App\Entity;

use App\Repository\SpotifyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpotifyRepository::class)]
class Spotify
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $refreshToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }
}

