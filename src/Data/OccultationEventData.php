<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;
use Spatie\LaravelData\Data;

/**
 * One occultation event (a star/planet occulted by the Moon).
 *
 * `centrality`, `coreShadowKm` and `location` are global-only (null for local).
 * `duration` is local-only (seconds; null for global).
 */
class OccultationEventData extends Data
{
    public function __construct(
        public readonly OccultationType $type,
        public readonly ?Centrality $centrality,
        public readonly OccultationScope $scope,
        public readonly Carbon $maxAt,
        public readonly float $julianDay,
        public readonly float $deltaT,
        public readonly float $magnitude,
        public readonly ?float $coreShadowKm,
        public readonly OccultationContacts $contacts,
        public readonly ?OccultationLocation $location,
        public readonly ?float $duration,
    ) {}

    public function isLocal(): bool
    {
        return $this->scope === OccultationScope::LOCAL;
    }

    public function isTotal(): bool
    {
        return $this->type === OccultationType::TOTAL;
    }
}
