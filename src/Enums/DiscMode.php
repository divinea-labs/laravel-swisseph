<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum DiscMode: string
{
    case BOTTOM = 'discbottom';  // civil sunrise — bottom limb on horizon (default)
    case CENTER = 'disccenter';  // disc centre crosses horizon
    case HINDU  = 'hindu';       // Hindu sunrise definition
}
