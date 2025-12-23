<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

final class InvalidPlanetBodySelectionException extends InvalidArgumentException
{
    public static function notEnum(mixed $value): self
    {
        return new self(sprintf(
            'Expected PlanetBodySelection enum, got %s.',
            is_object($value) ? $value::class : gettype($value),
        ));
    }

    public static function invalidValue(string $value): self
    {
        return new self(sprintf('Invalid PlanetBodySelection value "%s".', $value));
    }
}
