<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * The four contact instants of an occultation (Moon limb vs. occulted body).
 *
 * Global: exterior/interior ingress + interior/exterior egress.
 * Local: the same four columns, with `-` placeholders mapped to null.
 */
class OccultationContacts extends Data
{
    public function __construct(
        public readonly ?Carbon $exteriorStart,
        public readonly ?Carbon $interiorStart,
        public readonly ?Carbon $interiorEnd,
        public readonly ?Carbon $exteriorEnd,
    ) {}
}
