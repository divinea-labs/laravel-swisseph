<?php

namespace DivineaLabs\Swisseph;

use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Parsing\SwissephParser;
use DivineaLabs\Swisseph\Support\Rising\RiseCommandBuilder;
use DivineaLabs\Swisseph\Support\Rising\RiseParser;
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
        $this->app->bind(SwissephCommandBuilder::class);
        $this->app->bind(SwissephExecutor::class);
        $this->app->bind(SwissephParser::class);
        $this->app->bind(RiseCommandBuilder::class);
        $this->app->bind(RiseParser::class);

        $this->app->bind(Swisseph::class, function ($app) {
            return new Swisseph(
                $app->make(SwissephCommandBuilder::class),
                $app->make(SwissephExecutor::class),
                $app->make(SwissephParser::class),
                $app->make(RiseCommandBuilder::class),
                $app->make(RiseParser::class),
            );
        });

        // Alias for easier access
        $this->app->alias(Swisseph::class, 'swisseph');
    }
}
