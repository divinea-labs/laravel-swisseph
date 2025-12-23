<?php

namespace DivineaLabs\Swisseph\Enums;

enum ObserverPosition: string
{
    case HELIOCENTRIC = 'hel';
    case BARYCENTRIC = 'bary';
    case TOPOCENTRIC = 'topo';
    case PLANETOCENTRIC = 'pc';
}
