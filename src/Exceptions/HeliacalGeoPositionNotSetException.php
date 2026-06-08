<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class HeliacalGeoPositionNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No geographic position set for the heliacal events. Call at($lon, $lat, $elev) before get().'
        );
    }
}
