<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

final class InvalidEphOptionPassedException extends InvalidArgumentException
{
    public function __construct(mixed $value)
    {
        parent::__construct(sprintf(
            'Invalid ephemeris option. Expected %s, got %s.',
            'DivineaLabs\\Swisseph\\Enums\\EphOptions',
            is_object($value) ? $value::class : gettype($value),
        ));
    }
}
