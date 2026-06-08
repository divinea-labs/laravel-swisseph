<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class OccultationTargetNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No occultation target set. Call forStar() or forBody() before get().'
        );
    }
}
