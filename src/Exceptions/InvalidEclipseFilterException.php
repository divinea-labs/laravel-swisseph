<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use DivineaLabs\Swisseph\Enums\EclipseKind;
use InvalidArgumentException;

final class InvalidEclipseFilterException extends InvalidArgumentException
{
    public static function notAllowed(string $filter, EclipseKind $kind): self
    {
        return new self(sprintf(
            'Filter %s() is not valid for %s eclipses.',
            $filter,
            $kind->value,
        ));
    }

    public static function lunarLocalUnsupported(): self
    {
        return new self(
            'Lunar eclipses do not support local() in SP1; use global().',
        );
    }
}
