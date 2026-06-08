<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;
use Spatie\LaravelData\Data;

class EclipseEventData extends Data
{
    public function __construct(
        public readonly EclipseKind $kind,
        public readonly EclipseType $type,
        public readonly EclipseScope $scope,
        public readonly Carbon $maxAt,
        public readonly float $julianDay,
        public readonly float $deltaT,
        public readonly EclipseMagnitudes $magnitudes,
        public readonly SarosSeries $saros,
        public readonly EclipseContacts $contacts,
        public readonly ?EclipseLocation $location,
        public readonly ?float $coreShadowKm,
        public readonly ?float $duration,
    ) {}

    public function isSolar(): bool
    {
        return $this->kind === EclipseKind::SOLAR;
    }

    public function isLunar(): bool
    {
        return $this->kind === EclipseKind::LUNAR;
    }

    public function isTotal(): bool
    {
        return $this->type === EclipseType::TOTAL;
    }

    public function isLocal(): bool
    {
        return $this->scope === EclipseScope::LOCAL;
    }
}
