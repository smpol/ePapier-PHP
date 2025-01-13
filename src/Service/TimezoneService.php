<?php

namespace App\Service;

class TimezoneService
{
    public function getTimezones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }
}