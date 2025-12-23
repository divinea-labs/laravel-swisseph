<?php

namespace DivineaLabs\Swisseph\Support\Parsing;

use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\HouseData;
use DivineaLabs\Swisseph\Data\PlanetBodyData;
use DivineaLabs\Swisseph\Data\PlanetBodyPropertyData;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\House;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;

/**
 * Class parsing swisseph CLI output into data structures.
 */
class SwissephParser
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
    public function parse(array $lines, SwissephCommandBuilder $builder): AstroTimeFrame
    {
        $bodies = [];
        $houses = [];

        $sequence = $builder->getProperties();

        foreach ($lines as $line) {
            $parts = explode('PPP', $line);

            $isHouse = count($parts) < count($sequence);

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
     * Function parsing a single planet row.
     *
     * @param  string[]  $parts
     * @param  AstroProperties[]  $sequence
     * @return array{planet_body: mixed, properties: array}
     */
    private function parsePlanetRow(array $parts, array $sequence): array
    {
        $planetBody = PlanetBody::from($parts[0]);
        $properties = [];

        $col = 2; // index 0: planet index, 1: name
        for ($seq = 2; $seq < count($sequence) && $col < count($parts); $seq++) {
            $propEnum = AstroProperties::from($sequence[$seq]->value);
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
            'planet_body' => PlanetBodyData::from([
                'name' => $planetBody->getName(),
                'index' => $planetBody->value,
            ]),
            'properties' => $properties,
        ];
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
