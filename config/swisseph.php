<?php

// config for DivineaLabs/Swisseph
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\HouseSystems;

return [
    /*
   |--------------------------------------------------------------------------
   | Path to swetest executable
   |--------------------------------------------------------------------------
   */
    'executable' => env('SWISSEPH_EXECUTABLE', base_path('swisseph/swetests')),

    /*
    |--------------------------------------------------------------------------
    | Path to ephemeris directory
    |--------------------------------------------------------------------------
    */
    'ephemeris_dir' => env('SWISSEPH_EPHEMERIS_DIR', base_path('swisseph/ephe')),

    /*
    |--------------------------------------------------------------------------
    | Ephemeris computation options
    |
    | ALL VALUES COME FROM .env AND ARE MAPPED SAFELY TO ENUMS
    |
    | .env example:
    |   SWISSEPH_EPHEMERIS_TYPE=eswe
    |   SWISSEPH_TRUE_POSITIONS=true
    |   SWISSEPH_NO_NUTATION=nonut
    |
    | All allowed values are those of EphOptions::cases()
    |--------------------------------------------------------------------------
    */
    'eph_options' => array_values(array_filter([
        EphOptions::tryFrom(env('SWISSEPH_EPHEMERIS_TYPE', EphOptions::SWISS_TYPE->value))
        ?? EphOptions::SWISS_TYPE,

        EphOptions::tryFrom(env('SWISSEPH_TRUE_POSITIONS', EphOptions::TRUE_POSITIONS->value))
        ?? EphOptions::TRUE_POSITIONS,

        EphOptions::tryFrom(env('SWISSEPH_NO_NUTATION', EphOptions::NO_NUTATION->value))
        ?? EphOptions::NO_NUTATION,
    ])),

    /*
    |--------------------------------------------------------------------------
    | Default house system
    |
    | Example in .env:
    |   SWISSEPH_HOUSESYSTEM=P
    |--------------------------------------------------------------------------
    */
    'default_house_system' => HouseSystems::tryFrom(env('SWISSEPH_HOUSESYSTEM', 'P'))
        ?? HouseSystems::PLACIDUS,

    /*
     * |--------------------------------------------------------------------------
     * | Timeout for swetest execution (in seconds)
     * |--------------------------------------------------------------------------
     */
    'timeout' => env('SWISSEPH_TIMEOUT', 10),
];
