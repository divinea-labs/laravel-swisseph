<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

final class InvalidPropertyPassedException extends InvalidArgumentException
{
    public function __construct(mixed $value)
    {
        parent::__construct(sprintf(
            'Invalid property. Expected %s, got %s.',
            'DivineaLabs\\Swisseph\\Enums\\AstroProperties',
            is_object($value) ? $value::class : gettype($value),
        ));
    }
}
