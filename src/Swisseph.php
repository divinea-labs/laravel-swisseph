<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph;

use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Rising\RisingsBuilder;

class Swisseph
{
    public function positions(): PositionsBuilder
    {
        return app(PositionsBuilder::class);
    }

    public function risings(): RisingsBuilder
    {
        return app(RisingsBuilder::class);
    }

    public function eclipses(): EclipsesBuilder
    {
        return app(EclipsesBuilder::class);
    }

    public function occultations(): OccultationsBuilder
    {
        return app(OccultationsBuilder::class);
    }

    public function meridianTransits(): MeridianTransitBuilder
    {
        return app(MeridianTransitBuilder::class);
    }

    public function orbitalElements(): OrbitalElementsBuilder
    {
        return app(OrbitalElementsBuilder::class);
    }

    public function heliacal(): HeliacalBuilder
    {
        return app(HeliacalBuilder::class);
    }
}
