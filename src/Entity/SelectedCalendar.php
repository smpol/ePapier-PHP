<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'selected_calendars')]
class SelectedCalendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $calendarId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $calendarName;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalendarId(): ?string
    {
        return $this->calendarId;
    }

    public function setCalendarId(string $calendarId): self
    {
        $this->calendarId = $calendarId;
        return $this;
    }

    public function getCalendarName(): ?string
    {
        return $this->calendarName;
    }

    public function setCalendarName(string $calendarName): self
    {
        $this->calendarName = $calendarName;
        return $this;
    }
}
