# Basic Example of Laravel Swisseph Wrapper

This document demonstrates how the Laravel Swisseph wrapper works by showing:

1. Generated `swetest` CLI command
2. Raw CLI output
3. Parsed and structured wrapper result

---

## Important note: coordinate order

`setLocation()` expects coordinates in the order:

* **longitude, latitude**

Example:

* Wrocław: `lon = 17.038538`, `lat = 51.107883`

---

## 1. Generating CLI Command

PHP

```php
use DivineaLabs\Swisseph\Facades\Swisseph;

$result = Swisseph::setDateTime('2025-03-23 21:21:00', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883, 'Wroclaw') // lon, lat
    ->getCliCommand();

dd($result);
```

Output

```bash
/home/user/www/swisseph/swetests -edir/home/user/www/swisseph/ephe -eswe -true -nonut -b23.03.2025 -ut20:21:00 -pd -fpPls -gPPP -head
```

> Input time is provided in `Europe/Warsaw`, and the generated command uses **UT** (`-ut20:21:00`), which corresponds to `21:21` local time.

---

## 2. Raw CLI Output (swetest)

CLI Command

```bash
/home/user/www/swisseph/swetests -edir/home/user/www/swisseph/ephe -eswe -true -nonut -b23.03.2025 -ut20:21:00 -pd -fpPls -gPPP -head
```

CLI Output

```text
0PPPSun            PPP  3.4519333PPP  0.9918181
1PPPMoon           PPP 289.5431212PPP 13.0034629
2PPPMercury        PPP  5.2590075PPP -0.8528543
3PPPVenus          PPP  2.1467057PPP -0.6276189
4PPPMars           PPP 111.1525617PPP  0.2725780
5PPPJupiter        PPP  74.7873944PPP  0.1379018
6PPPSaturn         PPP 353.5081649PPP  0.1223149
7PPPUranus         PPP  54.4061606PPP  0.0412627
8PPPNeptune        PPP 359.7557093PPP  0.0378879
9PPPPluto          PPP 303.4167662PPP  0.0188337
10PPPmean Node      PPP 357.1631708PPP -0.0529537
11PPPtrue Node      PPP 357.3827313PPP  0.0061017
12PPPmean Apogee    PPP 209.6471701PPP  0.1111239
```

---

## 3. Wrapper Result (get())

PHP (get planetary positions)

```php
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Enums\HouseSystems;

$result = Swisseph::setDateTime('2025-03-23 21:21:00', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883, 'Wroclaw') // lon, lat
    ->withHouses(HouseSystems::KOCH)
    ->get();

dd($result);
```

Wrapper Output

```text
AstroTimeFrame {
  place: "Wroclaw"
  date: 2025-03-23 20:21:00 UTC
  longitude: 17.038538
  latitude: 51.107883
  house_system: "KOCH"

  planet_bodies: [
    Sun {
      longitude_decimal: 3.4519333
      speed_longitude_decimal: 0.9918181
      house_position_degrees: 139°21' 8.5742
      house_position_degrees_decimal: 139.3523817
      house_number_decimal: 5.6450794
    },
    Moon {
      longitude_decimal: 289.5431212
      speed_longitude_decimal: 13.0034629
      house_position_degrees: 80°27'18.9411
      house_position_degrees_decimal: 80.4552614
      house_number_decimal: 3.6818420
    },
    Mercury {
      longitude_decimal: 5.2590075
      speed_longitude_decimal: -0.8528543
      house_position_degrees: 143°31'16.6478
      house_position_degrees_decimal: 143.5212911
      house_number_decimal: 5.7840430
    },
    Venus {
      longitude_decimal: 2.1467057
      speed_longitude_decimal: -0.6276189
      house_position_degrees: 143° 2'23.1387
      house_position_degrees_decimal: 143.0397608
      house_number_decimal: 5.7679920
    },
    Mars {
      longitude_decimal: 111.1525617
      speed_longitude_decimal: 0.2725780
      house_position_degrees: 258° 4'27.5728
      house_position_degrees_decimal: 258.0743258
      house_number_decimal: 9.6024775
    },
    Jupiter {
      longitude_decimal: 74.7873944
      speed_longitude_decimal: 0.1379018
      house_position_degrees: 221°35'32.4027
      house_position_degrees_decimal: 221.5923341
      house_number_decimal: 8.3864111
    },
    Saturn {
      longitude_decimal: 353.5081649
      speed_longitude_decimal: 0.1223149
      house_position_degrees: 126°30'59.3560
      house_position_degrees_decimal: 126.5164878
      house_number_decimal: 5.2172163
    },
    Uranus {
      longitude_decimal: 54.4061606
      speed_longitude_decimal: 0.0412627
      house_position_degrees: 199° 3'38.3418
      house_position_degrees_decimal: 199.0606505
      house_number_decimal: 7.6353550
    },
    Neptune {
      longitude_decimal: 359.7557093
      speed_longitude_decimal: 0.0378879
      house_position_degrees: 134°15' 4.5104
      house_position_degrees_decimal: 134.2512529
      house_number_decimal: 5.4750418
    },
    Pluto {
      longitude_decimal: 303.4167662
      speed_longitude_decimal: 0.0188337
      house_position_degrees: 86°16'46.8331
      house_position_degrees_decimal: 86.2796759
      house_number_decimal: 3.8759892
    },
    Mean Node {
      longitude_decimal: 357.1631708
      speed_longitude_decimal: -0.0529537
      house_position_degrees: 131°59'32.5618
      house_position_degrees_decimal: 131.9923783
      house_number_decimal: 5.3997459
    },
    True Node {
      longitude_decimal: 357.3827313
      speed_longitude_decimal: 0.0061017
      house_position_degrees: 132°14'57.6976
      house_position_degrees_decimal: 132.2493604
      house_number_decimal: 5.4083120
    },
    Mean Apogee (Lilith) {
      longitude_decimal: 209.6471701
      speed_longitude_decimal: 0.1111239
      house_position_degrees: 352°13' 9.1269
      house_position_degrees_decimal: 352.2192019
      house_number_decimal: 12.7406401
    }
  ]
}
```

---

Summary

* The wrapper generates the same `swetest` CLI command as manual usage.
* Raw CLI output is parsed into structured DTO objects.
* Data is ready for further processing in Laravel (including houses when `withHouses()` is enabled).
