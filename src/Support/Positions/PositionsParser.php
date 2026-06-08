<?php

namespace DivineaLabs\Swisseph\Support\Positions;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\AstroTimeSeries;
use DivineaLabs\Swisseph\Data\HouseData;
use DivineaLabs\Swisseph\Data\PlanetBodyData;
use DivineaLabs\Swisseph\Data\PlanetBodyPropertyData;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\House;
use DivineaLabs\Swisseph\Enums\PlanetBody;

/**
 * Class parsing swisseph CLI output into data structures.
 */
class PositionsParser
{
    // Some properties used in swisseph output span multiple columns
    /** @var array<string, int> */
    private array $propertyColumnCounts = [
        AstroProperties::SPEED_ALL_VALUES_DEGREE_FMT->value => 3,
        AstroProperties::SPEED_ALL_VALUES_DECIMAL_FMT->value => 3,
        AstroProperties::COORDINATES_ECLIPTICAL->value => 3,
        AstroProperties::COORDINATES_EQUATORIAL->value => 3,
        AstroProperties::UNIT_VECTOR_ECLIPTICAL->value => 3,
        AstroProperties::UNIT_VECTOR_EQUATORIAL->value => 3,
        AstroProperties::NODES_MEAN_LONGITUDE_DECIMAL->value => 2,
        AstroProperties::NODES_OSCULATING_LONGITUDE_DECIMAL->value => 2,
        AstroProperties::APSIDES_MEAN->value => 3,
        AstroProperties::APSIDES_OSC->value => 3,
    ];

    /**
     * Get number of columns occupied by given property in swisseph output.
     */
    private function getPropertyColumnCount(AstroProperties $property): int
    {
        return $this->propertyColumnCounts[$property->value] ?? 1;
    }

    /**
     * Parse swisseph output lines into AstroTimeFrame data structure.
     *
     * @param  string[]  $lines
     */
    public function parse(array $lines, PositionsBuilder $builder): AstroTimeFrame
    {
        $bodies = [];
        $houses = [];

        $sequence = $builder->getProperties();

        foreach ($lines as $line) {
            $parts = explode('PPP', $line);
            $firstToken = trim($parts[0]);

            // Non-numeric first token (star/asteroid/computed label) always routes to
            // the planet path even when the column count is below the full sequence length.
            // For numeric tokens, preserve the original heuristic: fewer columns than the
            // full sequence signals a house row (house points omit house_number_decimal).
            $isHouse = $this->isIntegerToken($firstToken)
                && count($parts) < count($sequence);

            if ($isHouse) {
                $houses[] = $this->parseHouseRow($parts, $sequence);
            } else {
                $bodies[] = $this->parsePlanetRow($parts, $sequence);
            }
        }

        return AstroTimeFrame::from([
            'place' => $builder->getPlace(),
            'date' => $builder->getDate(),
            'latitude' => $builder->getLatitude(),
            'longitude' => $builder->getLongitude(),
            'house_system' => $builder->getHouseSystem()?->name,
            'planet_bodies' => $bodies,
            'houses' => $houses,
        ]);
    }

    /**
     * Parse a batch (`-n`/`-s`) swetest run into a time series.
     *
     * Each row is prefixed by the T time column (`d.m.Y H:i:s UT`) which occupies
     * parts[0]; the remaining parts follow the same layout the single-frame parser
     * uses. Rows are grouped by their leading timestamp into one AstroTimeFrame each.
     *
     * @param  string[]  $lines
     */
    public function parseSeries(array $lines, PositionsBuilder $builder): AstroTimeSeries
    {
        $sequence = $builder->getProperties();

        // The first property is the T column added by steps(); the body/house
        // layout the row parsers expect is the sequence WITHOUT that leading column.
        $bodySequence = array_values(array_filter(
            $sequence,
            static fn (AstroProperties $p) => $p !== AstroProperties::DATE_FORMAT_DD_MM_YYYY,
        ));

        /** @var array<string, array{date: Carbon, bodies: array<int, mixed>, houses: array<int, mixed>}> $grouped */
        $grouped = [];
        $order = [];

        foreach ($lines as $line) {
            $parts = explode('PPP', $line);

            if (count($parts) < 2) {
                continue; // malformed; skip (mirrors parser tolerance)
            }

            $timestampToken = trim(array_shift($parts)); // e.g. "01.01.2026 12:00:00 UT"
            $date = $this->parseSeriesTimestamp($timestampToken);

            if ($date === null) {
                continue; // leading token is not a valid timestamp; skip this row
            }

            $key = $date->format('d.m.Y H:i:s');

            if (! isset($grouped[$key])) {
                $grouped[$key] = ['date' => $date, 'bodies' => [], 'houses' => []];
                $order[] = $key;
            }

            // After dropping the T column, the remaining parts use the body/house layout.
            $firstToken = trim($parts[0]);
            $isHouse = $this->isIntegerToken($firstToken)
                && count($parts) < count($bodySequence);

            if ($isHouse) {
                $grouped[$key]['houses'][] = $this->parseHouseRow($parts, $bodySequence);
            } else {
                $grouped[$key]['bodies'][] = $this->parsePlanetRow($parts, $bodySequence);
            }
        }

        $frames = [];
        foreach ($order as $key) {
            $g = $grouped[$key];
            $frames[] = AstroTimeFrame::from([
                'place' => $builder->getPlace(),
                'date' => $g['date'],
                'latitude' => $builder->getLatitude(),
                'longitude' => $builder->getLongitude(),
                'house_system' => $builder->getHouseSystem()?->name,
                'planet_bodies' => $g['bodies'],
                'houses' => $g['houses'],
            ]);
        }

        return new AstroTimeSeries(frames: $frames);
    }

    /**
     * Parse a swetest batch timestamp token (`d.m.Y H:i:s UT`) into a UTC Carbon.
     *
     * Returns null when the token does not match the expected format (e.g. a planet
     * index leaked into the leading column because T was not first in the -f sequence).
     * Callers should skip rows whose timestamp is null.
     */
    private function parseSeriesTimestamp(string $token): ?Carbon
    {
        // Strip a trailing " UT" / "TT" marker, then parse the civil datetime.
        $clean = trim(preg_replace('/\s*(UT|TT)\s*$/u', '', $token) ?? $token);

        try {
            $result = Carbon::createFromFormat('d.m.Y H:i:s', $clean, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        return ($result instanceof Carbon) ? $result->utc() : null;
    }

    /**
     * Function parsing a single planet/star/asteroid/computed row.
     *
     * Numeric first token  → numbered planet: [index, name, …values].
     * Non-numeric first token → catalog/computed body: [name, …values] (no index column).
     *
     * @param  string[]  $parts
     * @param  AstroProperties[]  $sequence
     * @return array{planet_body: mixed, properties: array}
     */
    private function parsePlanetRow(array $parts, array $sequence): array
    {
        $firstToken = trim($parts[0]);

        if ($this->isIntegerToken($firstToken)) {
            // Numbered planet: index in col 0, name in col 1, values from col 2.
            $planetBody = PlanetBody::from($firstToken);
            $bodyData = PlanetBodyData::from([
                'name' => $planetBody->getName(),
                'index' => $planetBody->value,
            ]);
            $valueStartCol = 2;
        } else {
            // Fixed star / asteroid / computed value: name in col 0, values from col 1.
            // index is null; raw name token stored verbatim.
            $bodyData = PlanetBodyData::from([
                'name' => $firstToken,
                'index' => null,
            ]);
            $valueStartCol = 1;
        }

        $properties = [];
        $col = $valueStartCol;
        $valueProperties = $this->valueProperties($sequence);

        foreach ($valueProperties as $propEnum) {
            if ($col >= count($parts)) {
                break;
            }

            $colCount = $this->getPropertyColumnCount($propEnum);

            $value = $colCount === 1
                ? trim($parts[$col])
                : array_map('trim', array_slice($parts, $col, $colCount));

            $properties[] = PlanetBodyPropertyData::from([
                'label' => $propEnum->getLabel(),
                'property' => $propEnum->getPropertyName(),
                'value' => $value,
            ]);

            $col += $colCount;
        }

        return [
            'planet_body' => $bodyData,
            'properties' => $properties,
        ];
    }

    /**
     * The value properties (everything after the index/name header columns).
     *
     * Filters out PLANET_INDEX and PLANET_NAME from the sequence, leaving only
     * the value-data properties that map onto the output columns after the body identifier.
     *
     * @param  AstroProperties[]  $sequence
     * @return AstroProperties[]
     */
    private function valueProperties(array $sequence): array
    {
        return array_values(array_filter(
            $sequence,
            static fn (AstroProperties $p): bool => $p !== AstroProperties::PLANET_INDEX
                && $p !== AstroProperties::PLANET_NAME,
        ));
    }

    /**
     * Whether a column token is a plain integer (a numbered planet index).
     */
    private function isIntegerToken(string $token): bool
    {
        return $token !== '' && preg_match('/^-?\d+$/', $token) === 1;
    }

    /**
     * Function parsing a single planet row.
     *
     * @param  string[]  $parts
     * @param  AstroProperties[]  $sequence
     * @return array{house: mixed, properties: array}
     */
    private function parseHouseRow(array $parts, array $sequence): array
    {
        $house = House::from($parts[0]);
        $properties = [];

        // index 0: house index, 1: name – tak jak w oryginale
        for ($i = 2; $i < count($parts); $i++) {
            $propEnum = AstroProperties::from($sequence[$i]->value);

            $properties[] = [
                'index' => $propEnum->getLabel(),
                'name' => $propEnum->getPropertyName(),
                'value' => trim($parts[$i]),
            ];
        }

        return [
            'house' => HouseData::from([
                'name' => $house->getName(),
                'index' => $house->value,
            ]),
            'properties' => $properties,
        ];
    }
}
