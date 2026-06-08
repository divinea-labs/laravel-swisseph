<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

class InvalidStepCountException extends InvalidArgumentException
{
    public static function mustBePositive(int $count): self
    {
        return new self("Step count must be >= 1, got {$count}.");
    }
}
