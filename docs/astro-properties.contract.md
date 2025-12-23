# AstroProperties — Swiss Ephemeris `-f` (SEQ) Reference

This document describes all `AstroProperties` enums used by Laravel-Swisseph.
Each enum corresponds to a Swiss Ephemeris `-f` format token.

Contract:
- **Token** = 1–2 char value used in `-f...` (SEQ)
- **Enum case** = `AstroProperties::...`
- **Label** = `AstroProperties::getLabel()`
- **Property key** = `AstroProperties::getPropertyName()` (used in parsed wrapper output)

---

## Machine-friendly mapping (Token → Enum → Output key)

| Token | Enum case | Output key | Label |
|---:|---|---|---|
| `y` | YEAR | `year` | Year |
| `Y` | YEAR_FRACTION | `year_fraction` | Year with fraction (Y.xx) |
| `p` | PLANET_INDEX | `planet_index` | Planet index used by Swisseph |
| `P` | PLANET_NAME | `planet_name` | Planet name |
| `J` | ABSOLUTE_JUL_DATE | `absolute_jul_date` | Absolute Date in Julian format |
| `T` | DATE_FORMAT_DD_MM_YYYY | `date_dd_mm_yyyy` | Date formatted as DD.MM.YYYY |
| `t` | DATE_FORMAT_YYMMDD | `date_yymmdd` | Date formatted as YYMMDD |
| `L` | LONGITUDE_DEGREE | `longitude_degree` | Longitude in degree ddd mm'ss |
| `l` | LONGITUDE_DECIMAL | `longitude_decimal` | Longitude decimal |
| `Z` | LONGITUDE_DD_SIGN | `longitude_ddsignmmss` | Longitude ddsignmm'ss |
| `S` | SPEED_LONGITUDE_DEGREE | `speed_longitude_degree` | Speed in longitude in degree ddd:mm:ss per day |
| `SS` | SPEED_ALL_VALUES_DEGREE_FMT | `speed_all_values_degree_fmt` | Speed for all values specified in fmt |
| `s` | SPEED_LONGITUDE_DECIMAL | `speed_longitude_decimal` | Speed longitude decimal (degrees/day) |
| `ss` | SPEED_ALL_VALUES_DECIMAL_FMT | `speed_all_values_fmt2` | Speed for all values specified in fmt ? |
| `B` | LATITUDE_DEGREE | `latitude_degree` | Latitude degree |
| `b` | LATITUDE_DECIMAL | `latitude_decimal` | Latitude decimal |
| `R` | DISTANCE_DECIMAL_AU | `distance_decimal_au` | Distance decimal in AU |
| `r` | DISTANCE_DECIMAL_AU_MOON | `distance_decimal_au_moon` | Distance decimal in AU, Moon in seconds parallax |
| `W` | DISTANCE_DECIMAL_LIGHT_YEARS | `distance_decimal_ly` | Distance decimal in light years |
| `w` | DISTANCE_DECIMAL_KM | `distance_decimal_km` | Distance decimal in km |
| `q` | RELATIVE_DISTANCE | `relative_distance` | Relative distance (1000=nearest, 0=furthest) |
| `A` | RIGHT_ASCENSION_HH_MM_SS | `right_ascension` | Right ascension in hh:mm:ss |
| `a` | RIGHT_ASCENSION_HOURS_DECIMAL | `right_ascension_hours_decimal` | Right ascension hours decimal |
| `m` | MERIDIAN_DISTANCE | `meridian_distance` | Meridian distance |
| `z` | ZENITH_DISTANCE | `zenith_distance` | Zenith distance |
| `D` | DECLINATION_DEGREE | `declination_degree` | Declination degree |
| `d` | DECLINATION_DECIMAL | `declination_decimal` | Declination decimal |
| `I` | AZIMUTH_DEGREE | `azimuth_degree` | Azimuth degree |
| `i` | AZIMUTH_DECIMAL | `azimuth_decimal` | Azimuth decimal |
| `H` | ALTITUDE_DEGREE | `altitude_degree` | Altitude degree |
| `h` | ALTITUDE_DECIMAL | `altitude_decimal` | Altitude decimal |
| `K` | ALTITUDE_DEGREE_REFRACTION | `altitude_refraction_degree` | Altitude (with refraction) degree |
| `k` | ALTITUDE_DECIMAL_REFRACTION | `altitude_no_refraction_decimal` | Altitude (with refraction) decimal |
| `G` | HOUSE_POSITION_DEGREES | `house_position_degrees` | House position in degrees |
| `g` | HOUSE_POSITION_DEGREES_DECIMAL | `house_position_degrees_decimal` | House position in degrees decimal |
| `j` | HOUSE_NUMBER_DECIMAL | `house_number_decimal` | House number 1.0 - 12.99999 |
| `X` | COORDINATES_ECLIPTICAL | `coordinates_ecliptical` | x-, y-, and z-coordinates ecliptical |
| `x` | COORDINATES_EQUATORIAL | `coordinates_equatorial` | x-, y-, and z-coordinates equatorial |
| `U` | UNIT_VECTOR_ECLIPTICAL | `unit_vector_ecliptical` | Unit vector ecliptical |
| `u` | UNIT_VECTOR_EQUATORIAL | `unit_vector_equatorial` | Unit vector equatorial |
| `n` | NODES_MEAN_LONGITUDE_DECIMAL | `nodes_mean_longitude_decimal` | Nodes (mean): ascending/descending (Me - Ne); longitude decimal |
| `N` | NODES_OSCULATING_LONGITUDE_DECIMAL | `nodes__osculating_longitude_decimal` | Nodes (osculating): ascending/descending, longitude; decimal |
| `f` | APSIDES_MEAN | `apsides_mean` | Apsides (mean): perihelion, aphelion, second focal point; longitude dec. |
| `F` | APSIDES_OSC | `apsides_osc` | Apsides (osc.): perihelion, aphelion, second focal point; longitude dec. |
| `+` | PHASE_ANGLE | `phase_angle` | Phase angle |
| `-` | PHASE | `phase` | Phase |
| `*` | ELONGATION | `elongation` | Elongation |
| `/` | APPARENT_DIAMETER | `apparent_diameter` | Apparent diameter of disc |
| `=` | MAGNITUDE | `magnitude` | Magnitude |

Notes:
- `SPEED_ALL_VALUES_DECIMAL_FMT (ss)` and some commented “Q ...” items are marked `TO-CHECK` in code.
- Spelling and naming are preserved from code (e.g. `LONGITUDE_DEGREE`, `nodes__osculating_longitude_decimal`).

---

## Human-friendly documentation (by category)

### 1. Time and Date
- `y` (YEAR) — Gregorian year (YYYY)
- `Y` (YEAR_FRACTION) — Year with fractional part (YYYY.xx)
- `J` (ABSOLUTE_JUL_DATE) — Julian Day Number
- `T` (DATE_FORMAT_DD_MM_YYYY) — Date formatted as DD.MM.YYYY
- `t` (DATE_FORMAT_YYMMDD) — Date formatted as YYMMDD

### 2. Body Identification
- `p` (PLANET_INDEX) — Swiss Ephemeris object ID (0=Sun)
- `P` (PLANET_NAME) — Name of the body

### 3. Ecliptic Longitude
- `L` (LONGITUDE_DEGREE) — ddd mm'ss
- `l` (LONGITUDE_DECIMAL) — decimal degrees
- `Z` (LONGITUDE_DD_SIGN) — ddsignmm'ss

### 4. Motion / Speed
- `S` (SPEED_LONGITUDE_DEGREE) — longitude speed d:m:s/day
- `SS` (SPEED_ALL_VALUES_DEGREE_FMT) — speeds of L, B, R in d:m:s format
- `s` (SPEED_LONGITUDE_DECIMAL) — longitude speed in degrees/day
- `ss` (SPEED_ALL_VALUES_DECIMAL_FMT) — speeds L, B, R in decimal degrees

### 5. Ecliptic Latitude
- `B` (LATITUDE_DEGREE) — ddd mm'ss
- `b` (LATITUDE_DECIMAL) — decimal degrees

### 6. Distance Values
- `R` (DISTANCE_DECIMAL_AU) — distance in AU
- `r` (DISTANCE_DECIMAL_AU_MOON) — AU or moon parallax arcseconds
- `W` (DISTANCE_DECIMAL_LIGHT_YEARS) — light-years
- `w` (DISTANCE_DECIMAL_KM) — kilometers
- `q` (RELATIVE_DISTANCE) — 0–1000 scale (0=farthest, 1000=closest)

### 7. Right Ascension / Declination
- `A` (RIGHT_ASCENSION_HH_MM_SS) — RA h:m:s
- `a` (RIGHT_ASCENSION_HOURS_DECIMAL) — RA decimal hours
- `D` (DECLINATION_DEGREE) — d:m:s
- `d` (DECLINATION_DECIMAL) — decimal degrees

### 8. Horizontal Coordinates (Azimuth / Altitude)
- `I` (AZIMUTH_DEGREE) — azimuth degrees
- `i` (AZIMUTH_DECIMAL) — azimuth decimal
- `H` (ALTITUDE_DEGREE) — altitude d:m:s
- `h` (ALTITUDE_DECIMAL) — altitude decimal
- `K` (ALTITUDE_DEGREE_REFRACTION) — altitude w/refraction d:m:s
- `k` (ALTITUDE_DECIMAL_REFRACTION) — altitude w/refraction decimal

### 9. Meridian & Zenith Distances
- `m` (MERIDIAN_DISTANCE)
- `z` (ZENITH_DISTANCE)

### 10. House System Values
Requires: `-house lon,lat,system`

- `G` (HOUSE_POSITION_DEGREES) — cusp position d:m:s
- `g` (HOUSE_POSITION_DEGREES_DECIMAL) — cusp position decimal
- `j` (HOUSE_NUMBER_DECIMAL) — house number 1.0–12.9999

### 11. Cartesian 3D Coordinates
- `X` (COORDINATES_ECLIPTICAL) — ecliptic x,y,z
- `x` (COORDINATES_EQUATORIAL) — equatorial x,y,z
- `U` (UNIT_VECTOR_ECLIPTICAL)
- `u` (UNIT_VECTOR_EQUATORIAL)

### 12. Nodes & Apsides
- `n` (NODES_MEAN_LONGITUDE_DECIMAL)
- `N` (NODES_OSCULATING_LONGITUDE_DECIMAL)
- `f` (APSIDES_MEAN)
- `F` (APSIDES_OSC)

### 13. Phases, Elongation, Magnitude
- `+` (PHASE_ANGLE)
- `-` (PHASE)
- `*` (ELONGATION)
- `/` (APPARENT_DIAMETER)
- `=` (MAGNITUDE)

---

## Summary

This document lists all supported `AstroProperties` used to build the `-f` sequence for Swiss Ephemeris.
These properties define exactly which values are returned for each celestial body or house in parsing.
