<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

final class EclipseKindNotSetException extends InvalidArgumentException
{
    public static function missing(): self
    {
        return new self('Eclipse kind not set — call solar() or lunar() before get().');
    }
}
