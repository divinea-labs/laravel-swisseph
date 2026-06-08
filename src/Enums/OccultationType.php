<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum OccultationType: string
{
    case TOTAL = 'total';
    case ANNULAR = 'annular';
    case PARTIAL = 'partial';
}
