<?php

namespace App\Entity;

use App\Repository\EmailSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailSettingsRepository::class)]
class EmailSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $imapServer = null;

    #[ORM\Column]
    private ?int $imapPort = null;

    #[ORM\Column(length: 255)]
    private ?string $imapUser = null;

    #[ORM\Column(length: 255)]
    private ?string $imapPassword = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImapServer(): ?string
    {
        return $this->imapServer;
    }

    public function setImapServer(string $imapServer): static
    {
        $this->imapServer = $imapServer;

        return $this;
    }

    public function getImapPort(): ?int
    {
        return $this->imapPort;
    }

    public function setImapPort(int $imapPort): static
    {
        $this->imapPort = $imapPort;

        return $this;
    }

    public function getImapUser(): ?string
    {
        return $this->imapUser;
    }

    public function setImapUser(string $imapUser): static
    {
        $this->imapUser = $imapUser;

        return $this;
    }

    public function getImapPassword(): ?string
    {
        return $this->imapPassword;
    }

    public function setImapPassword(string $imapPassword): static
    {
        $this->imapPassword = $imapPassword;

        return $this;
    }
}
