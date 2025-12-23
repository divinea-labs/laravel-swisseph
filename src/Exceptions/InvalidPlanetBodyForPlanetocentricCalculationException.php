<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use LogicException;

final class InvalidPlanetBodyForPlanetocentricCalculationException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Planetocentric observer position requires a PlanetBody (e.g. PlanetBody::EARTH).');
    }
}
