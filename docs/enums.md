# Enums Reference

This file documents all enums available in the package (as of current code).
It is intended for:
- documentation reference
- AI-assisted refactoring
- static analysis / contract clarity

Conventions:
- **Case** = PHP enum case name
- **Value** = raw enum value used in CLI / parsing
- **Label/Name** = human-readable label (if available)
- **Property** = normalized property key used by the wrapper (if available)

---

## AstroProperties (string)

Namespace: `DivineaLabs\Swisseph\Enums\AstroProperties`  
Purpose: Output formatting / property selection (swetest format tokens, `-f...`)

Columns:
- **Value** = swetest format token
- **Label** = `getLabel()`
- **Property** = `getPropertyName()`

| Case | Value | Label | Property |
|---|---:|---|---|
| YEAR | `y` | Year | `year` |
| YEAR_FRACTION | `Y` | Year with fraction (Y.xx) | `year_fraction` |
| PLANET_INDEX | `p` | Planet index used by Swisseph | `planet_index` |
| PLANET_NAME | `P` | Planet name | `planet_name` |
| ABSOLUTE_JUL_DATE | `J` | Absolute Date in Julian format | `absolute_jul_date` |
| DATE_FORMAT_DD_MM_YYYY | `T` | Date formatted as DD.MM.YYYY | `date_dd_mm_yyyy` |
| DATE_FORMAT_YYMMDD | `t` | Date formatted as YYMMDD | `date_yymmdd` |
| LONGITUDE_DEGREE | `L` | Longitude in degree ddd mm'ss | `longitude_degree` |
| LONGITUDE_DECIMAL | `l` | Longitude decimal | `longitude_decimal` |
| LONGITUDE_DD_SIGN | `Z` | Longitude ddsignmm'ss | `longitude_ddsignmmss` |
| SPEED_LONGITUDE_DEGREE | `S` | Speed in longitude in degree ddd:mm:ss per day | `speed_longitude_degree` |
| SPEED_ALL_VALUES_DEGREE_FMT | `SS` | Speed for all values specified in fmt | `speed_all_values_degree_fmt` |
| SPEED_LONGITUDE_DECIMAL | `s` | Speed longitude decimal (degrees/day) | `speed_longitude_decimal` |
| SPEED_ALL_VALUES_DECIMAL_FMT | `ss` | Speed for all values specified in fmt ? | `speed_all_values_fmt2` |
| LATITUDE_DEGREE | `B` | Latitude degree | `latitude_degree` |
| LATITUDE_DECIMAL | `b` | Latitude decimal | `latitude_decimal` |
| DISTANCE_DECIMAL_AU | `R` | Distance decimal in AU | `distance_decimal_au` |
| DISTANCE_DECIMAL_AU_MOON | `r` | Distance decimal in AU, Moon in seconds parallax | `distance_decimal_au_moon` |
| DISTANCE_DECIMAL_LIGHT_YEARS | `W` | Distance decimal in light years | `distance_decimal_ly` |
| DISTANCE_DECIMAL_KM | `w` | Distance decimal in km | `distance_decimal_km` |
| RELATIVE_DISTANCE | `q` | Relative distance (1000=nearest, 0=furthest) | `relative_distance` |
| RIGHT_ASCENSION_HH_MM_SS | `A` | Right ascension in hh:mm:ss | `right_ascension` |
| RIGHT_ASCENSION_HOURS_DECIMAL | `a` | Right ascension hours decimal | `right_ascension_hours_decimal` |
| MERIDIAN_DISTANCE | `m` | Meridian distance | `meridian_distance` |
| ZENITH_DISTANCE | `z` | Zenith distance | `zenith_distance` |
| DECLINATION_DEGREE | `D` | Declination degree | `declination_degree` |
| DECLINATION_DECIMAL | `d` | Declination decimal | `declination_decimal` |
| AZIMUTH_DEGREE | `I` | Azimuth degree | `azimuth_degree` |
| AZIMUTH_DECIMAL | `i` | Azimuth decimal | `azimuth_decimal` |
| ALTITUDE_DEGREE | `H` | Altitude degree | `altitude_degree` |
| ALTITUDE_DECIMAL | `h` | Altitude decimal | `altitude_decimal` |
| ALTITUDE_DEGREE_REFRACTION | `K` | Altitude (with refraction) degree | `altitude_refraction_degree` |
| ALTITUDE_DECIMAL_REFRACTION | `k` | Altitude (with refraction) decimal | `altitude_no_refraction_decimal` |
| HOUSE_POSITION_DEGREES | `G` | House position in degrees | `house_position_degrees` |
| HOUSE_POSITION_DEGREES_DECIMAL | `g` | House position in degrees decimal | `house_position_degrees_decimal` |
| HOUSE_NUMBER_DECIMAL | `j` | House number 1.0 - 12.99999 | `house_number_decimal` |
| COORDINATES_ECLIPTICAL | `X` | x-, y-, and z-coordinates ecliptical | `coordinates_ecliptical` |
| COORDINATES_EQUATORIAL | `x` | x-, y-, and z-coordinates equatorial | `coordinates_equatorial` |
| UNIT_VECTOR_ECLIPTICAL | `U` | Unit vector ecliptical | `unit_vector_ecliptical` |
| UNIT_VECTOR_EQUATORIAL | `u` | Unit vector equatorial | `unit_vector_equatorial` |
| NODES_MEAN_LONGITUDE_DECIMAL | `n` | Nodes (mean): ascending/descending (Me - Ne); longitude decimal | `nodes_mean_longitude_decimal` |
| NODES_OSCULATING_LONGITUDE_DECIMAL | `N` | Nodes (osculating): ascending/descending, longitude; decimal | `nodes__osculating_longitude_decimal` |
| APSIDES_MEAN | `f` | Apsides (mean): perihelion, aphelion, second focal point; longitude dec. | `apsides_mean` |
| APSIDES_OSC | `F` | Apsides (osc.): perihelion, aphelion, second focal point; longitude dec. | `apsides_osc` |
| PHASE_ANGLE | `+` | Phase angle | `phase_angle` |
| PHASE | `-` | Phase | `phase` |
| ELONGATION | `*` | Elongation | `elongation` |
| APPARENT_DIAMETER | `/` | Apparent diameter of disc | `apparent_diameter` |
| MAGNITUDE | `=` | Magnitude | `magnitude` |

Notes:
- `SPEED_ALL_VALUES_DECIMAL_FMT (ss)` and some commented tokens are marked `TO-CHECK` in code.
- There are a few naming inconsistencies preserved from code (e.g. `LONGITUDE_DEGREE`, `nodes__osculating_longitude_decimal`).

---

## EphOptions (string)

Namespace: `DivineaLabs\Swisseph\Enums\EphOptions`  
Purpose: Ephemeris configuration flags and options (mapped to swetest params)

### Type of ephemeris
- SWISS_TYPE => `eswe`
- JPL_TYPE => `ejpl`
- MOSHIER => `emos`

### Reference frame / precession
- NO_PRECESSION => `j2000`
- ICRS => `icrs`

### Corrections
- NO_ABERRATION => `noaberr`
- NO_LIGHT_DEFLECTION => `nodefl`
- NO_NUTATION => `nonut`
- TRUE_POSITIONS => `true`

### Path prefix
- EPHEMERIS_PATH => `edir`

---

## House (string)

Namespace: `DivineaLabs\Swisseph\Enums\House`  
Purpose: House points / angles / additional points returned by house computations

| Case | Value | Name (`getName()`) |
|---|---:|---|
| HOUSE_1 | `1` | House 1 |
| HOUSE_2 | `2` | House 2 |
| HOUSE_3 | `3` | House 3 |
| HOUSE_4 | `4` | House 4 |
| HOUSE_5 | `5` | House 5 |
| HOUSE_6 | `6` | House 6 |
| HOUSE_7 | `7` | House 7 |
| HOUSE_8 | `8` | House 8 |
| HOUSE_9 | `9` | House 9 |
| HOUSE_10 | `10` | House 10 |
| HOUSE_11 | `11` | House 11 |
| HOUSE_12 | `12` | House 12 |
| ASCENDANT | `13` | Ascendant |
| MC | `14` | Midheaven |
| ARMC | `15` | ARMC |
| VERTEX | `16` | Vertex |
| EQUAT_ASC | `17` | Equatorial Ascendant |
| CO_ASC_KOCH | `18` | CO-Ascendant" (W. Koch) |
| CO_ASC_MUNKASEY | `19` | CO-Ascendant" (M. Munkasey) |
| POLAR_ASC_MUNKASEY | `20` | Polar Ascendant" (M. Munkasey) |

---

## HouseSystems (string)

Namespace: `DivineaLabs\Swisseph\Enums\HouseSystems`  
Purpose: House system selection (typically passed as one-letter code)

| Case | Value |
|---|---:|
| EQUAL | `A` |
| ALCABITIUS | `B` |
| CAMPANUS | `C` |
| EQUAL_MC | `D` |
| EQUAL_A | `E` |
| CARTER_POLI_EQUATORIAL | `F` |
| GAUQUELIN_36_SECTORS | `G` |
| HORIZON | `H` |
| SUNSHINE | `I` |
| SUNSHINE_ALTERNATIVE | `i` |
| KOCH | `K` |
| PULLEN_S_DELTA | `L` |
| MORINUS | `M` |
| WHOLE_SIGN | `N` |
| PORPHYRY | `O` |
| PLACIDUS | `P` |
| PULLEN_S_RATIO | `Q` |
| REGIOMONTANUS | `R` |
| SRIPATI | `S` |
| POLICH_PAGE | `T` |
| KRUSINSKI_PISA_GOELZER | `U` |
| EQUAL_VEHLOW | `V` |
| EQUAL_WHOLE_SIGN | `W` |
| AXIAL_ROTATION_SYSTEM_MERIDIAN | `X` |
| APC_HOUSES | `Y` |

Note:
- `WHOLE_SIGN (N)` is explicitly noted as “Aries = 1st House” in code.

---

## ObserverPosition (string)

Namespace: `DivineaLabs\Swisseph\Enums\ObserverPosition`  
Purpose: Observer reference position / output perspective

| Case | Value |
|---|---|
| HELIOCENTRIC | `hel` |
| BARYCENTRIC | `bary` |
| TOPOCENTRIC | `topo` |
| PLANETOCENTRIC | `pc` |

---

## PlanetBody (int)

Namespace: `DivineaLabs\Swisseph\Enums\PlanetBody`  
Purpose: Planet/body identifiers (Swiss Ephemeris indices)

| Case | Value | Name (`getName()`) |
|---|---:|---|
| ECL_NUT | -1 | Ecliptic / Nutation |
| SUN | 0 | Sun |
| MOON | 1 | Moon |
| MERCURY | 2 | Mercury |
| VENUS | 3 | Venus |
| MARS | 4 | Mars |
| JUPITER | 5 | Jupiter |
| SATURN | 6 | Saturn |
| URANUS | 7 | Uranus |
| NEPTUNE | 8 | Neptune |
| PLUTO | 9 | Pluto |
| MEAN_NODE | 10 | Mean Node |
| TRUE_NODE | 11 | True Node |
| MEAN_APOG | 12 | Mean Apogee (Lilith) |
| OSCU_APOG | 13 | Osculating Apogee |
| EARTH | 14 | Earth |
| CHIRON | 15 | Chiron |
| PHOLUS | 16 | Pholus |
| CERES | 17 | Ceres |
| PALLAS | 18 | Pallas |
| JUNO | 19 | Juno |
| VESTA | 20 | Vesta |
| INTP_APOG | 21 | Interpolated Apogee |
| INTP_PERG | 22 | Interpolated Perigee |
| NPLANETS | 23 | Planet count |
| CUPIDO | 40 | Cupido |
| HADES | 41 | Hades |
| ZEUS | 42 | Zeus |
| KRONOS | 43 | Kronos |
| APOLLON | 44 | Apollon |
| ADMETOS | 45 | Admetos |
| VULKANUS | 46 | Vulkanus |
| POSEIDON | 47 | Poseidon |
| ISIS | 48 | Isis |
| NIBIRU | 49 | Nibiru |
| HARRINGTON | 50 | Harrington |
| NEPTUNE_LEVERRIER | 51 | Neptune (Leverrier) |
| NEPTUNE_ADAMS | 52 | Neptune (Adams) |
| PLUTO_LOWELL | 53 | Pluto (Lowell) |
| PLUTO_PICKERING | 54 | Pluto (Pickering) |
| VULCAN | 55 | Vulcan |
| SELENA | 56 | Selena / White Moon |
| WALDEMATH | 58 | Waldemath |

### Additional information (from `getAdditionalInformation()`)

Only provided for the following bodies:

- HARRINGTON:
  This is another attempt to predict Planet X's orbit and position from perturbations in the orbits of Uranus and Neptune.
  It was published in The Astronomical Journal 96(4), October 1988, p. 1476ff. Its precision is meant to be of the order of
  +/- 30 degrees. According to Harrington there is also the possibility that it is actually located in the opposite
  constellation, i.e. Taurus instead of Scorpio. The planet has a mean solar distance of about 100 AU and a period of about
  1000 years.

- NIBIRU:
  A highly speculative planet derived from the theory of Zecharia Sitchin, who is an expert in ancient Mesopotamian history
  and a "paleoastronomer". The elements have been supplied by Christian Woeltge, Hannover. This planet is interesting
  because of its bizarre orbit. It moves in clockwise direction and has a period of 3600 years. Its orbit is extremely eccentric.
  It has its perihelion within the asteroid belt, whereas its aphelion lies at about 12 times the mean distance of Pluto. In
  spite of its retrograde motion, it seems to move counterclockwise in recent centuries. The reason is that it is so slow that
  it does not even compensate the precession of the equinoxes.

- VULCAN:
  This is a ‘hypothetical’ planet inside the orbit of Mercury (not identical to the "Uranian" planet Vulkanus). Orbital elements
  according to L.H. Weston. Note that the speed of this “planet” does not agree with the Kepler laws. It is too fast by 10
  degrees per year.

- SELENA:
  This is a "hypothetical" planet inside the orbit of Mercury (not identical to the "Uranian" planet Vulkanus). Orbital elements
  according to L.H. Weston. Note that the speed of this “planet” does not agree with the Kepler laws. It is too fast by 10
  degrees per year.

- WALDEMATH:
  This is another hypothetical second Moon of the Earth, postulated by a Dr. Waldemath in the Monthly Wheather Review
  1/1898. Its distance from the Earth is 2.67 times the distance of the Moon, its daily motion about 3 degrees. The orbital
  elements have been derived from Waldemath’s original data. There are significant differences from elements used in
  earlier versions of Solar Fire, due to different interpretations of the values given by Waldemath. After a discussion
  between Graham Dawson and Dieter Koch it has been agreed that the new solution is more likely to be correct. The new
  ephemeris does not agree with Delphine Jay’s ephemeris either, which is obviously inconsistent with Waldemath’s data.
  This body has never been confirmed. With its 700-km diameter and an apparent diameter of 2.5 arc min, this should
  have been possible very soon after Waldemath’s publication.

---

## PlanetBodySelection (string)

Namespace: `DivineaLabs\Swisseph\Enums\PlanetBodySelection`  
Purpose: Selection mask / group selection for bodies (typically `-p...` style selection)

### Selection groups
| Case | Value | Name (`getName()`) |
|---|---:|---|
| DEFAULT_FACTORS | `d` | Default planetary factors |
| DEFAULT_FACTORS_ASTEROIDS | `p` | Default asteroid set |
| FICTITIOUS_FACTORS | `h` | Fictitious / hypothetical bodies |
| ALL_FACTORS | `a` | All available planetary factors |

### Individual bodies
| Case | Value | Name (`getName()`) |
|---|---:|---|
| SUN | `0` | Sun |
| MOON | `1` | Moon |
| MERCURY | `2` | Mercury |
| VENUS | `3` | Venus |
| MARS | `4` | Mars |
| JUPITER | `5` | Jupiter |
| SATURN | `6` | Saturn |
| URANUS | `7` | Uranus |
| NEPTUNE | `8` | Neptune |
| PLUTO | `9` | Pluto |
| MEAN_NODE | `m` | Mean Node |
| TRUE_NODE | `t` | True Node |
| MEAN_APOG | `A` | Mean Apogee (Lilith) |
| OSCU_APOG | `B` | Osculating Apogee |
| EARTH | `C` | Earth |
| CHIRON | `D` | Chiron |
| PHOLUS | `E` | Pholus |
| CERES | `F` | Ceres |
| PALLAS | `G` | Pallas |
| JUNO | `H` | Juno |
| VESTA | `I` | Vesta |
| INTP_APOG | `c` | Interpolated Apogee |
| INTP_PERG | `g` | Interpolated Perigee |
| NPLANETS | `?` | Number of planets |
| CUPIDO | `J` | Cupido |
| HADES | `K` | Hades |
| ZEUS | `L` | Zeus |
| KRONOS | `M` | Kronos |
| APOLLON | `N` | Apollon |
| ADMETOS | `O` | Admetos |
| VULKANUS | `P` | Vulkanus |
| POSEIDON | `Q` | Poseidon |
| ISIS | `R` | Isis |
| NIBIRU | `S` | Nibiru |
| HARRINGTON | `T` | Harrington |
| NEPTUNE_LEVERRIER | `U` | Neptune (Leverrier) |
| NEPTUNE_ADAMS | `V` | Neptune (Adams) |
| PLUTO_LOWELL | `W` | Pluto (Lowell) |
| PLUTO_PICKERING | `X` | Pluto (Pickering) |
| VULCAN | `Y` | Vulcan |
| SELENA | `Z` | Selena / White Moon |
| WALDEMATH | `w` | Waldemath's Dark Moon |

Notes (from code comments):
- `x` sidereal time
- `e` print a line of labels

---

## Sidereal (int)

Namespace: `DivineaLabs\Swisseph\Enums\Sidereal`  
Purpose: Sidereal ayanamsa selection (Swiss Ephemeris constants)

| Case | Value |
|---|---:|
| FAGAN_BRADLEY | 0 |
| LAHIRI | 1 |
| DE_LUCE | 2 |
| RAMAN | 3 |
| SUSHA_SHASI | 4 |
| KRISHNAMURTI | 5 |
| DJWHAL_KHUL | 6 |
| YUKTESHWAR | 7 |
| BHASIN | 8 |
| BABYLONIAN_KUGLER_1 | 9 |
| BABYLONIAN_KUGLER_2 | 10 |
| BABYLONIAN_KUGLER_3 | 11 |
| BABYLONIAN_HUBER | 12 |
| BABYLONIAN_ETA | 13 |
| BABYLONIAN_ALDEBARAN | 14 |
| HIPPARCHOS | 15 |
| SASSANIAN | 16 |
| GALATIC_CENTER | 17 |
| J200 | 18 |
| J1900 | 19 |
| B1950 | 20 |
| SURYASIDDHANTA | 21 |
| SURYASIDDHANTA_MEAN_SUN | 22 |
| ARYABHATA | 23 |
| ARYABHATA_MEAN_SUN | 24 |
| SS_REVATI | 25 |
| SS_CITRA | 26 |
| TRUE_CITRA | 27 |
| TRUE_REVATI | 28 |
| TRUE_PUSHYA | 29 |
| GALATIC_GIL_BRAND | 30 |
| GALACTIC_EQUATOR_1958 | 31 |
| GALACTIC_EQUATOR | 32 |
| GALATIC_MID_MULA | 33 |
| SKYDRAM | 34 |
| TRUE_MULA | 35 |
| DHRUVA | 36 |
| ARYABHATA_522 | 37 |
| BABYLONIAN_BRITTON | 38 |
| VEDIC_SHEORAN | 39 |
| COCHRANE | 40 |
| GALACTIC_EQUATOR_FIORENZA | 41 |
| VETTIUS_VALENS | 42 |
| LAHIRI_1940 | 43 |
| LAHIRI_VP285 | 44 |
| KRISHNAMURTI_VP291 | 45 |
| LAHIRI_ICRC | 46 |
