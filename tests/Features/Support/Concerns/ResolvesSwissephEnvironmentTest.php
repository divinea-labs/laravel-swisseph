<?php

use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

it('resolves executable and normalized ephemeris dir from config', function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', 'C:\\ephe\\');

    $host = new class
    {
        use ResolvesSwissephEnvironment;

        public function __construct()
        {
            $this->bootSwissephEnvironment();
        }

        public function exe(): string
        {
            return $this->executable;
        }

        public function edir(): string
        {
            return $this->epheDirArg();
        }
    };

    expect($host->exe())->toBe('/bin/swetest');
    expect($host->edir())->toBe('edir/C:/ephe');
});

it('builds date and ut time arguments in swetest format', function () {
    $host = new class
    {
        use ResolvesSwissephEnvironment;

        public function __construct()
        {
            $this->bootSwissephEnvironment();
        }

        public function d(): string
        {
            return $this->dateArg();
        }

        public function t(): string
        {
            return $this->utTimeArg();
        }
    };
    $host->setDateTime('2026-01-01 12:30:00', 'UTC');

    expect($host->d())->toBe('b01.01.2026');
    expect($host->t())->toBe('ut12:30:00');
});

it('de-dupes and sorts eph options', function () {
    config()->set('swisseph.eph_options', []); // start clean — no config defaults
    $host = new class
    {
        use ResolvesSwissephEnvironment;

        public function __construct()
        {
            $this->bootSwissephEnvironment();
        }

        public function opts(): array
        {
            return $this->ephOptionArgs();
        }
    };
    $host->withEphOptions(EphOptions::TRUE_POSITIONS, EphOptions::SWISS_TYPE, EphOptions::SWISS_TYPE);

    expect($host->opts())->toBe(['eswe', 'true']);
});
