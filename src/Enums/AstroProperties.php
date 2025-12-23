<?php

namespace DivineaLabs\Swisseph\Enums;

// fSEQ
enum AstroProperties: string
{
    case YEAR = 'y';
    case YEAR_FRACTION = 'Y';
    case PLANET_INDEX = 'p';
    case PLANET_NAME = 'P';
    case ABSOLUTE_JUL_DATE = 'J';
    case DATE_FORMAT_DD_MM_YYYY = 'T';
    case DATE_FORMAT_YYMMDD = 't';
    case LONGITUDE_DEGREE = 'L';
    case LONGITUDE_DECIMAL = 'l';
    case LONGITUDE_DD_SIGN = 'Z';
    case SPEED_LONGITUDE_DEGREE = 'S';
    case SPEED_ALL_VALUES_DEGREE_FMT = 'SS';
    case SPEED_LONGITUDE_DECIMAL = 's';
    case SPEED_ALL_VALUES_DECIMAL_FMT = 'ss'; // TO-CHECK with SS => SPEED_ALL_VALUES_DEGREE_FMT
    case LATITUDE_DEGREE = 'B';
    case LATITUDE_DECIMAL = 'b';
    case DISTANCE_DECIMAL_AU = 'R';
    case DISTANCE_DECIMAL_AU_MOON = 'r';
    case DISTANCE_DECIMAL_LIGHT_YEARS = 'W';
    case DISTANCE_DECIMAL_KM = 'w';
    case RELATIVE_DISTANCE = 'q';
    case RIGHT_ASCENSION_HH_MM_SS = 'A';
    case RIGHT_ASCENSION_HOURS_DECIMAL = 'a';
    case MERIDIAN_DISTANCE = 'm';
    case ZENITH_DISTANCE = 'z';
    case DECLINATION_DEGREE = 'D';
    case DECLINATION_DECIMAL = 'd';
    case AZIMUTH_DEGREE = 'I';
    case AZIMUTH_DECIMAL = 'i';
    case ALTITUDE_DEGREE = 'H';
    case ALTITUDE_DECIMAL = 'h';
    case ALTITUDE_DEGREE_REFRACTION = 'K';
    case ALTITUDE_DECIMAL_REFRACTION = 'k';
    case HOUSE_POSITION_DEGREES = 'G';
    case HOUSE_POSITION_DEGREES_DECIMAL = 'g';
    case HOUSE_NUMBER_DECIMAL = 'j';
    case COORDINATES_ECLIPTICAL = 'X';
    case COORDINATES_EQUATORIAL = 'x';
    case UNIT_VECTOR_ECLIPTICAL = 'U';
    case UNIT_VECTOR_EQUATORIAL = 'u';
    // Q l, b, r, dl, db, dr, a, d, da, dd => TO-CHECK
    case NODES_MEAN_LONGITUDE_DECIMAL = 'n';
    case NODES_OSCULATING_LONGITUDE_DECIMAL = 'N';
    case APSIDES_MEAN = 'f';
    case APSIDES_OSC = 'F';
    case PHASE_ANGLE = '+';
    case PHASE = '-';
    case ELONGATION = '*';
    case APPARENT_DIAMETER = '/';
    case MAGNITUDE = '=';

    public function getLabel(): string
    {
        return match ($this) {
            self::YEAR => 'Year',
            self::YEAR_FRACTION => 'Year with fraction (Y.xx)',
            self::PLANET_INDEX => 'Planet index used by Swisseph',
            self::PLANET_NAME => 'Planet name',
            self::ABSOLUTE_JUL_DATE => 'Absolute Date in Julian format',
            self::DATE_FORMAT_DD_MM_YYYY => 'Date formatted as DD.MM.YYYY',
            self::DATE_FORMAT_YYMMDD => 'Date formatted as YYMMDD',
            self::LONGITUDE_DEGREE => "Longitude in degree ddd mm'ss",
            self::LONGITUDE_DECIMAL => 'Longitude decimal',
            self::LONGITUDE_DD_SIGN => "Longitude ddsignmm'ss",
            self::SPEED_LONGITUDE_DEGREE => 'Speed in longitude in degree ddd:mm:ss per day',
            self::SPEED_ALL_VALUES_DEGREE_FMT => 'Speed for all values specified in fmt',
            self::SPEED_LONGITUDE_DECIMAL => 'Speed longitude decimal (degrees/day)',
            self::SPEED_ALL_VALUES_DECIMAL_FMT => 'Speed for all values specified in fmt ?', // TO-CHECK with SS =>> SPEED_ALL_VALUES_DEGREE_FMT
            self::LATITUDE_DEGREE => 'Latitude degree',
            self::LATITUDE_DECIMAL => 'Latitude decimal',
            self::DISTANCE_DECIMAL_AU => 'Distance decimal in AU',
            self::DISTANCE_DECIMAL_AU_MOON => 'Distance decimal in AU, Moon in seconds parallax',
            self::DISTANCE_DECIMAL_LIGHT_YEARS => 'Distance decimal in light years',
            self::DISTANCE_DECIMAL_KM => 'Distance decimal in km',
            self::RELATIVE_DISTANCE => 'Relative distance (1000=nearest, 0=furthest)',
            self::RIGHT_ASCENSION_HH_MM_SS => 'Right ascension in hh:mm:ss',
            self::RIGHT_ASCENSION_HOURS_DECIMAL => 'Right ascension hours decimal',
            self::MERIDIAN_DISTANCE => 'Meridian distance',
            self::ZENITH_DISTANCE => 'Zenith distance',
            self::DECLINATION_DEGREE => 'Declination degree',
            self::DECLINATION_DECIMAL => 'Declination decimal',
            self::AZIMUTH_DEGREE => 'Azimuth degree',
            self::AZIMUTH_DECIMAL => 'Azimuth decimal',
            self::ALTITUDE_DEGREE => 'Altitude degree',
            self::ALTITUDE_DECIMAL => 'Altitude decimal',
            self::ALTITUDE_DEGREE_REFRACTION => 'Altitude (with refraction) degree',
            self::ALTITUDE_DECIMAL_REFRACTION => 'Altitude (with refraction) decimal',
            self::HOUSE_POSITION_DEGREES => 'House position in degrees',
            self::HOUSE_POSITION_DEGREES_DECIMAL => 'House position in degrees decimal',
            self::HOUSE_NUMBER_DECIMAL => 'House number 1.0 - 12.99999',
            self::COORDINATES_ECLIPTICAL => 'x-, y-, and z-coordinates ecliptical',
            self::COORDINATES_EQUATORIAL => 'x-, y-, and z-coordinates equatorial',
            self::UNIT_VECTOR_ECLIPTICAL => 'Unit vector ecliptical',
            self::UNIT_VECTOR_EQUATORIAL => 'Unit vector equatorial',
            // Q l, b, r, dl, db, dr, a, d, da, dd =>> TO-CHECK
            self::NODES_MEAN_LONGITUDE_DECIMAL => 'Nodes (mean): ascending/descending (Me - Ne); longitude decimal',
            self::NODES_OSCULATING_LONGITUDE_DECIMAL => 'Nodes (osculating): ascending/descending, longitude; decimal',
            self::APSIDES_MEAN => 'Apsides (mean): perihelion, aphelion, second focal point; longitude dec.',
            self::APSIDES_OSC => 'Apsides (osc.): perihelion, aphelion, second focal point; longitude dec.',
            self::PHASE_ANGLE => 'Phase angle',
            self::PHASE => 'Phase',
            self::ELONGATION => 'Elongation',
            self::APPARENT_DIAMETER => 'Apparent diameter of disc',
            self::MAGNITUDE => 'Magnitude',
        };
    }

    public function getPropertyName(): string
    {
        return match ($this) {
            self::YEAR => 'year',
            self::YEAR_FRACTION => 'year_fraction',
            self::PLANET_INDEX => 'planet_index',
            self::PLANET_NAME => 'planet_name',
            self::ABSOLUTE_JUL_DATE => 'absolute_jul_date',
            self::DATE_FORMAT_DD_MM_YYYY => 'date_dd_mm_yyyy',
            self::DATE_FORMAT_YYMMDD => 'date_yymmdd',
            self::LONGITUDE_DEGREE => 'longitude_degree',
            self::LONGITUDE_DECIMAL => 'longitude_decimal',
            self::LONGITUDE_DD_SIGN => 'longitude_ddsignmmss',
            self::SPEED_LONGITUDE_DEGREE => 'speed_longitude_degree',
            self::SPEED_ALL_VALUES_DEGREE_FMT => 'speed_all_values_degree_fmt',
            self::SPEED_LONGITUDE_DECIMAL => 'speed_longitude_decimal',
            self::SPEED_ALL_VALUES_DECIMAL_FMT => 'speed_all_values_fmt2', // TO-CHECK with SS =>> SPEED_ALL_VALUES_DEGREE_FMT
            self::LATITUDE_DEGREE => 'latitude_degree',
            self::LATITUDE_DECIMAL => 'latitude_decimal',
            self::DISTANCE_DECIMAL_AU => 'distance_decimal_au',
            self::DISTANCE_DECIMAL_AU_MOON => 'distance_decimal_au_moon',
            self::DISTANCE_DECIMAL_LIGHT_YEARS => 'distance_decimal_ly',
            self::DISTANCE_DECIMAL_KM => 'distance_decimal_km',
            self::RELATIVE_DISTANCE => 'relative_distance',
            self::RIGHT_ASCENSION_HH_MM_SS => 'right_ascension',
            self::RIGHT_ASCENSION_HOURS_DECIMAL => 'right_ascension_hours_decimal',
            self::MERIDIAN_DISTANCE => 'meridian_distance',
            self::ZENITH_DISTANCE => 'zenith_distance',
            self::DECLINATION_DEGREE => 'declination_degree',
            self::DECLINATION_DECIMAL => 'declination_decimal',
            self::AZIMUTH_DEGREE => 'azimuth_degree',
            self::AZIMUTH_DECIMAL => 'azimuth_decimal',
            self::ALTITUDE_DEGREE => 'altitude_degree',
            self::ALTITUDE_DECIMAL => 'altitude_decimal',
            self::ALTITUDE_DEGREE_REFRACTION => 'altitude_refraction_degree',
            self::ALTITUDE_DECIMAL_REFRACTION => 'altitude_no_refraction_decimal',
            self::HOUSE_POSITION_DEGREES => 'house_position_degrees',
            self::HOUSE_POSITION_DEGREES_DECIMAL => 'house_position_degrees_decimal',
            self::HOUSE_NUMBER_DECIMAL => 'house_number_decimal',
            self::COORDINATES_ECLIPTICAL => 'coordinates_ecliptical',
            self::COORDINATES_EQUATORIAL => 'coordinates_equatorial',
            self::UNIT_VECTOR_ECLIPTICAL => 'unit_vector_ecliptical',
            self::UNIT_VECTOR_EQUATORIAL => 'unit_vector_equatorial',
            // Q l, b, r, dl, db, dr, a, d, da, dd =>> TO-CHECK
            self::NODES_MEAN_LONGITUDE_DECIMAL => 'nodes_mean_longitude_decimal',
            self::NODES_OSCULATING_LONGITUDE_DECIMAL => 'nodes_osculating_longitude_decimal',
            self::APSIDES_MEAN => 'apsides_mean',
            self::APSIDES_OSC => 'apsides_osc',
            self::PHASE_ANGLE => 'phase_angle',
            self::PHASE => 'phase',
            self::ELONGATION => 'elongation',
            self::APPARENT_DIAMETER => 'apparent_diameter',
            self::MAGNITUDE => 'magnitude',
        };
    }
}
