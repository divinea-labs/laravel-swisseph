<?php

namespace DivineaLabs\Swisseph;

use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Eclipses\EclipseParser;
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalParser;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitParser;
use DivineaLabs\Swisseph\Support\Occultations\OccultationParser;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsParser;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;
use DivineaLabs\Swisseph\Support\Rising\RiseParser;
use DivineaLabs\Swisseph\Support\Rising\RisingsBuilder;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SwissephServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-swisseph')
            ->hasConfigFile();
    }

    public function registeringPackage()
    {
        $this->app->bind(SwissephExecutor::class);
        $this->app->bind(PositionsBuilder::class);
        $this->app->bind(PositionsParser::class);
        $this->app->bind(RisingsBuilder::class);
        $this->app->bind(RiseParser::class);
        $this->app->bind(EclipsesBuilder::class);
        $this->app->bind(EclipseParser::class);
        $this->app->bind(OccultationsBuilder::class);
        $this->app->bind(OccultationParser::class);
        $this->app->bind(MeridianTransitBuilder::class);
        $this->app->bind(MeridianTransitParser::class);
        $this->app->bind(OrbitalElementsBuilder::class);
        $this->app->bind(OrbitalElementsParser::class);
        $this->app->bind(HeliacalBuilder::class);
        $this->app->bind(HeliacalParser::class);

        $this->app->bind(Swisseph::class, fn () => new Swisseph);

        // Alias for easier access
        $this->app->alias(Swisseph::class, 'swisseph');
    }
}
