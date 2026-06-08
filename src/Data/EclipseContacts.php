<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class EclipseContacts extends Data
{
    /**
     * Six named contact phases, each nullable (UTC).
     *
     * Lunar uses all six. Solar maps its four contacts onto
     * partialStart/centralStart/centralEnd/partialEnd (penumbral slots null).
     * `centralStart`/`centralEnd` are the inner (umbral/annular) contacts — accurate
     * for both TOTAL and ANNULAR eclipse types.
     * Local/partial eclipses leave inapplicable slots null (the `-` placeholders).
     */
    public function __construct(
        public readonly ?Carbon $penumbralStart,
        public readonly ?Carbon $partialStart,
        public readonly ?Carbon $centralStart,
        public readonly ?Carbon $centralEnd,
        public readonly ?Carbon $partialEnd,
        public readonly ?Carbon $penumbralEnd,
    ) {}
}
