<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\OrbitalElements;

use DivineaLabs\Swisseph\Data\OrbitalElementsData;
use DivineaLabs\Swisseph\Enums\PlanetBody;

class OrbitalElementsParser
{
    /**
     * Map of swetest -orbel `key` (text before the tab, trimmed) → DTO field.
     * Header/context lines (date/UT/TT/Epsilon/Nutation and the body-position
     * line) use space separators, not a tab, so they never match and are skipped.
     *
     * @var array<string, string>
     */
    private const KEYS = [
        'semiaxis' => 'semiAxis',
        'eccentricity' => 'eccentricity',
        'inclination' => 'inclination',
        'asc. node' => 'ascendingNode',
        'arg. pericenter' => 'argPericenter',
        'pericenter' => 'pericenter',
        'mean longitude' => 'meanLongitude',
        'mean anomaly' => 'meanAnomaly',
        'ecc. anomaly' => 'eccentricAnomaly',
        'true anomaly' => 'trueAnomaly',
        'dist. pericenter' => 'distPericenter',
        'dist. apocenter' => 'distApocenter',
        'mean daily motion' => 'meanDailyMotion',
        'sid. period (y)' => 'siderealPeriodYears',
        'trop. period (y)' => 'tropicalPeriodYears',
        'synodic cycle (d)' => 'synodicCycleDays',
    ];

    /**
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines, PlanetBody $body): OrbitalElementsData
    {
        /** @var array<string, float> $floats */
        $floats = [];
        $timePericenterJd = null;
        $timePericenterCivil = null;

        foreach ($lines as $line) {
            // key and value are tab-separated; lines without a tab are headers → skip.
            $parts = explode("\t", $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            $key = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($key === 'time pericenter') {
                // value: "<jd>  <d.m.Y>,  <H:i:s.f>" — JD then civil date string.
                $tokens = preg_split('/\s+/', $value) ?: [];
                $timePericenterJd = isset($tokens[0]) ? (float) $tokens[0] : null;
                $timePericenterCivil = trim((string) preg_replace('/^\S+\s*/', '', $value));

                continue;
            }

            if (isset(self::KEYS[$key])) {
                $floats[self::KEYS[$key]] = (float) $value;
            }
        }

        return new OrbitalElementsData(
            body: $body,
            semiAxis: $this->require($floats, 'semiAxis'),
            eccentricity: $this->require($floats, 'eccentricity'),
            inclination: $this->require($floats, 'inclination'),
            ascendingNode: $this->require($floats, 'ascendingNode'),
            argPericenter: $this->require($floats, 'argPericenter'),
            pericenter: $this->require($floats, 'pericenter'),
            meanLongitude: $this->require($floats, 'meanLongitude'),
            meanAnomaly: $this->require($floats, 'meanAnomaly'),
            eccentricAnomaly: $this->require($floats, 'eccentricAnomaly'),
            trueAnomaly: $this->require($floats, 'trueAnomaly'),
            timePericenterJd: $timePericenterJd ?? throw new \RuntimeException('Missing orbital element: time pericenter (JD).'),
            timePericenterCivil: $timePericenterCivil ?? throw new \RuntimeException('Missing orbital element: time pericenter (civil).'),
            distPericenter: $this->require($floats, 'distPericenter'),
            distApocenter: $this->require($floats, 'distApocenter'),
            meanDailyMotion: $this->require($floats, 'meanDailyMotion'),
            siderealPeriodYears: $this->require($floats, 'siderealPeriodYears'),
            tropicalPeriodYears: $this->require($floats, 'tropicalPeriodYears'),
            synodicCycleDays: $this->require($floats, 'synodicCycleDays'),
        );
    }

    /**
     * @param  array<string, float>  $floats
     */
    private function require(array $floats, string $field): float
    {
        return $floats[$field] ?? throw new \RuntimeException("Missing orbital element: {$field}.");
    }
}
