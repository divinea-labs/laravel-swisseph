<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class MeridianGeoPositionNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No geographic position set for the meridian transit. Call at($lon, $lat, $elev) before get().'
        );
    }
}
