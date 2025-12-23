<?php

namespace DivineaLabs\Swisseph;

use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Parsing\SwissephParser;
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

        $this->app->bind(\DivineaLabs\Swisseph\Swisseph::class, function ($app) {
            return new Swisseph(
                $app->make(SwissephCommandBuilder::class),
                $app->make(SwissephExecutor::class),
                $app->make(SwissephParser::class),
            );
        });

        // Alias for easier access
        $this->app->alias(\DivineaLabs\Swisseph\Swisseph::class, 'swisseph');
    }
}
