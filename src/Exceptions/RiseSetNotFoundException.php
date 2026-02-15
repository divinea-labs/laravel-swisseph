<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use DivineaLabs\Swisseph\Enums\PlanetBody;

class RiseSetNotFoundException extends \RuntimeException
{
    public static function forUtcDate(PlanetBody $body, string $utcDate): self
    {
        return new self(
            "No rise/set found for {$body->getName()} on UTC date {$utcDate}."
        );
    }

    public static function forLocalDate(PlanetBody $body, string $localDate, string $timezone): self
    {
        return new self(
            "No rise/set found for {$body->getName()} on {$localDate} in {$timezone}."
        );
    }
}
