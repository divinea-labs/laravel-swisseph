<?php

namespace DivineaLabs\Swisseph\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DivineaLabs\Swisseph\Swisseph
 */
class Swisseph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'swisseph';
    }
}
