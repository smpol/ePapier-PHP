<?php

namespace App\Entity;

use App\Repository\LayoutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutRepository::class)]
class Layout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $main = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $replacement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMain(): ?string
    {
        return $this->main;
    }

    public function setMain(string $main): static
    {
        $this->main = $main;

        return $this;
    }

    public function getReplacement(): ?string
    {
        return $this->replacement;
    }

    public function setReplacement(?string $replacement): static
    {
        $this->replacement = $replacement;

        return $this;
    }

    public function setLayout(string $main, ?string $replacement): static
    {
        $this->main = $main;
        $this->replacement = $replacement;

        return $this;
    }
}
