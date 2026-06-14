# FixedStar & Asteroid Convenience Enums Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add curated `FixedStar` (string-backed) and `Asteroid` (int-backed) enums and accept them alongside raw `string`/`int` in the fixed-star and asteroid selectors, so `selectAsteroid(433)` can become `selectAsteroid(Asteroid::EROS)`.

**Architecture:** Two new backed enums in `src/Enums/` following the existing `PlanetBody`/`PlanetBodySelection` convention (`getName()` helper). The four builder methods that take a fixed-star name or asteroid number are widened to `Enum|scalar` union types; a one-line normalization converts an enum to its backing value, after which existing validation/CLI-arg logic is untouched. Docs (`docs/enums.md`, `README.md`) updated.

**Tech Stack:** PHP 8.x backed enums, Pest test suite (`./vendor/bin/pest`), Laravel 12/13 package.

**Spec:** `docs/superpowers/specs/2026-06-14-fixed-star-asteroid-enums-design.md`

---

## File Structure

- Create: `src/Enums/FixedStar.php` — curated string-backed fixed-star catalog names.
- Create: `src/Enums/Asteroid.php` — curated int-backed asteroid MPC numbers.
- Create: `tests/Features/Enums/FixedStarTest.php` — enum unit tests.
- Create: `tests/Features/Enums/AsteroidTest.php` — enum unit tests.
- Modify: `src/Support/Positions/PositionsBuilder.php` — widen `selectFixedStar`, `selectAsteroid`.
- Modify: `src/Support/Occultations/OccultationsBuilder.php` — widen `forStar`.
- Modify: `src/Support/Heliacal/HeliacalBuilder.php` — widen `forStar`.
- Modify: `tests/Features/Support/Positions/PositionsBodiesExtraTest.php` — enum-path builder tests.
- Modify: `docs/enums.md` — add `FixedStar` and `Asteroid` sections + long-tail note.
- Modify: `README.md` — update fixed-star/asteroid examples to use enums.

All values were verified against `~/www/swisseph/ephe/sefstars.txt` and `seasnam.txt` on 2026-06-14. Note the catalog spelling `Zubeneshamali` (one `s`).

---

## Task 1: `FixedStar` enum

**Files:**
- Create: `src/Enums/FixedStar.php`
- Test: `tests/Features/Enums/FixedStarTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Features/Enums/FixedStarTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Enums\FixedStar;

it('backs each fixed star with its Swiss Ephemeris catalog name', function () {
    expect(FixedStar::SIRIUS->value)->toBe('Sirius');
    expect(FixedStar::ALDEBARAN->value)->toBe('Aldebaran');
    expect(FixedStar::GALACTIC_CENTER->value)->toBe('Galactic Center');
});

it('keeps the catalog value for Rigil Kentaurus even though the display name differs', function () {
    expect(FixedStar::RIGIL_KENTAURUS->value)->toBe('Bungula');
    expect(FixedStar::RIGIL_KENTAURUS->getName())->toBe('Rigil Kentaurus');
});

it('uses the spelling Swiss Ephemeris expects for Zubeneshamali', function () {
    expect(FixedStar::ZUBENESHAMALI->value)->toBe('Zubeneshamali');
});

it('returns a non-empty value and display name for every case', function () {
    foreach (FixedStar::cases() as $star) {
        expect($star->value)->not->toBe('');
        expect($star->getName())->not->toBe('');
    }
});

it('round-trips through from()', function () {
    expect(FixedStar::from('Sirius'))->toBe(FixedStar::SIRIUS);
    expect(FixedStar::tryFrom('not-a-star'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Features/Enums/FixedStarTest.php`
Expected: FAIL — `Class "DivineaLabs\Swisseph\Enums\FixedStar" not found`.

- [ ] **Step 3: Write the enum**

Create `src/Enums/FixedStar.php`:

```php
<?php

namespace DivineaLabs\Swisseph\Enums;

/**
 * Curated set of commonly-used fixed stars.
 *
 * The backing value is the Swiss Ephemeris catalog traditional name (the exact
 * string swetest expects after `-xf`). This is a convenience subset — any entry
 * from `ephe/sefstars.txt` may still be passed to the selectors as a raw string.
 */
enum FixedStar: string
{
    case SIRIUS = 'Sirius';
    case CANOPUS = 'Canopus';
    case RIGIL_KENTAURUS = 'Bungula'; // display "Rigil Kentaurus"; catalog name is "Bungula"
    case ARCTURUS = 'Arcturus';
    case VEGA = 'Vega';
    case CAPELLA = 'Capella';
    case RIGEL = 'Rigel';
    case PROCYON = 'Procyon';
    case BETELGEUSE = 'Betelgeuse';
    case ACHERNAR = 'Achernar';
    case ALTAIR = 'Altair';
    case ACRUX = 'Acrux';
    case ALDEBARAN = 'Aldebaran';
    case ANTARES = 'Antares';
    case SPICA = 'Spica';
    case POLLUX = 'Pollux';
    case FOMALHAUT = 'Fomalhaut';
    case DENEB = 'Deneb';
    case REGULUS = 'Regulus';
    case CASTOR = 'Castor';
    case BELLATRIX = 'Bellatrix';
    case ALGOL = 'Algol';
    case ALPHECCA = 'Alphecca';
    case ALCYONE = 'Alcyone';
    case POLARIS = 'Polaris';
    case ALPHARD = 'Alphard';
    case HAMAL = 'Hamal';
    case ALPHERATZ = 'Alpheratz';
    case RASALHAGUE = 'Rasalhague';
    case DENEBOLA = 'Denebola';
    case MENKAR = 'Menkar';
    case ZUBENELGENUBI = 'Zubenelgenubi';
    case ZUBENESHAMALI = 'Zubeneshamali';
    case VINDEMIATRIX = 'Vindemiatrix';
    case MARKAB = 'Markab';
    case SCHEAT = 'Scheat';
    case ALGENIB = 'Algenib';
    case SADALMELEK = 'Sadalmelek';
    case MIRFAK = 'Mirfak';
    case WEZEN = 'Wezen';
    case ALNILAM = 'Alnilam';
    case MIRZAM = 'Mirzam';
    case MIRA = 'Mira';
    case GALACTIC_CENTER = 'Galactic Center';

    /**
     * Human-readable display name. Equal to the catalog value for every star
     * except RIGIL_KENTAURUS, whose catalog name is "Bungula".
     */
    public function getName(): string
    {
        return match ($this) {
            self::RIGIL_KENTAURUS => 'Rigil Kentaurus',
            default => $this->value,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Features/Enums/FixedStarTest.php`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
git add src/Enums/FixedStar.php tests/Features/Enums/FixedStarTest.php
git commit -m "feat: add FixedStar enum for curated fixed stars"
```

---

## Task 2: `Asteroid` enum

**Files:**
- Create: `src/Enums/Asteroid.php`
- Test: `tests/Features/Enums/AsteroidTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Features/Enums/AsteroidTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Enums\Asteroid;

it('backs each asteroid with its MPC number', function () {
    expect(Asteroid::EROS->value)->toBe(433);
    expect(Asteroid::ERIS->value)->toBe(136199);
    expect(Asteroid::VARUNA->value)->toBe(20000);
});

it('returns a non-empty name for every case', function () {
    foreach (Asteroid::cases() as $asteroid) {
        expect($asteroid->getName())->not->toBe('');
    }
});

it('exposes a positive MPC number for every case', function () {
    foreach (Asteroid::cases() as $asteroid) {
        expect($asteroid->value)->toBeGreaterThan(0);
    }
});

it('maps a case to its asteroid name', function () {
    expect(Asteroid::EROS->getName())->toBe('Eros');
    expect(Asteroid::QUAOAR->getName())->toBe('Quaoar');
});

it('round-trips through from()', function () {
    expect(Asteroid::from(433))->toBe(Asteroid::EROS);
    expect(Asteroid::tryFrom(999999))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Features/Enums/AsteroidTest.php`
Expected: FAIL — `Class "DivineaLabs\Swisseph\Enums\Asteroid" not found`.

- [ ] **Step 3: Write the enum**

Create `src/Enums/Asteroid.php`:

```php
<?php

namespace DivineaLabs\Swisseph\Enums;

/**
 * Curated set of commonly-used named asteroids and trans-Neptunian objects.
 *
 * The backing value is the Minor Planet Center (MPC) number (the int swetest
 * expects after `-xs`). This is a convenience subset — any MPC number from
 * `ephe/seasnam.txt` may still be passed to selectAsteroid() as a raw int.
 *
 * Ceres, Pallas, Juno, Vesta, Chiron and Pholus are intentionally absent: they
 * have reserved Swiss Ephemeris constants and live in PlanetBody, selected via
 * selectBodies() rather than selectAsteroid().
 */
enum Asteroid: int
{
    case EROS = 433;
    case PSYCHE = 16;
    case SAPPHO = 80;
    case AMOR = 1221;
    case LILITH = 1181;
    case HYGIEA = 10;
    case APOLLO = 1862;
    case ICARUS = 1566;
    case ATEN = 2062;
    case CRUITHNE = 3753;
    case POSEIDON = 4341;
    case QUAOAR = 50000;
    case HIDALGO = 944;
    case NESSUS = 7066;
    case HEKATE = 100;
    case NEMESIS = 128;
    case FORTUNA = 19;
    case URANIA = 30;
    case HEKTOR = 624;
    case CHAOS = 19521;
    case SEDNA = 90377;
    case ERIS = 136199;
    case ORCUS = 90482;
    case VARUNA = 20000;

    public function getName(): string
    {
        return match ($this) {
            self::EROS => 'Eros',
            self::PSYCHE => 'Psyche',
            self::SAPPHO => 'Sappho',
            self::AMOR => 'Amor',
            self::LILITH => 'Lilith',
            self::HYGIEA => 'Hygiea',
            self::APOLLO => 'Apollo',
            self::ICARUS => 'Icarus',
            self::ATEN => 'Aten',
            self::CRUITHNE => 'Cruithne',
            self::POSEIDON => 'Poseidon',
            self::QUAOAR => 'Quaoar',
            self::HIDALGO => 'Hidalgo',
            self::NESSUS => 'Nessus',
            self::HEKATE => 'Hekate',
            self::NEMESIS => 'Nemesis',
            self::FORTUNA => 'Fortuna',
            self::URANIA => 'Urania',
            self::HEKTOR => 'Hektor',
            self::CHAOS => 'Chaos',
            self::SEDNA => 'Sedna',
            self::ERIS => 'Eris',
            self::ORCUS => 'Orcus',
            self::VARUNA => 'Varuna',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Features/Enums/AsteroidTest.php`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
git add src/Enums/Asteroid.php tests/Features/Enums/AsteroidTest.php
git commit -m "feat: add Asteroid enum for curated named asteroids"
```

---

## Task 3: Accept enums in `PositionsBuilder`

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php` (`selectFixedStar` ~line 143, `selectAsteroid` ~line 184)
- Test: `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

- [ ] **Step 1: Write the failing tests**

Add these to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php` (append after the existing asteroid test block, before the computed-value tests). First add the enum imports near the top with the other `use` lines:

```php
use DivineaLabs\Swisseph\Enums\Asteroid;
use DivineaLabs\Swisseph\Enums\FixedStar;
```

Then the tests:

```php
it('accepts a FixedStar enum and emits the same args as the raw name', function () {
    $fromEnum = (new PositionsBuilder)->selectFixedStar(FixedStar::SIRIUS)->build()->toCliString();
    $fromString = (new PositionsBuilder)->selectFixedStar('Sirius')->build()->toCliString();

    expect($fromEnum)->toContain('-pf');
    expect($fromEnum)->toContain('-xfSirius');
    expect($fromEnum)->toBe($fromString);
});

it('accepts an Asteroid enum and emits the same args as the raw MPC number', function () {
    $fromEnum = (new PositionsBuilder)->selectAsteroid(Asteroid::EROS)->build()->toCliString();
    $fromInt = (new PositionsBuilder)->selectAsteroid(433)->build()->toCliString();

    expect($fromEnum)->toContain('-ps');
    expect($fromEnum)->toContain('-xs433');
    expect($fromEnum)->toBe($fromInt);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `selectFixedStar()` rejects `FixedStar` (TypeError: expects string), `selectAsteroid()` rejects `Asteroid` (TypeError: expects int).

- [ ] **Step 3: Widen `selectFixedStar`**

Add the import near the top of `src/Support/Positions/PositionsBuilder.php` with the other `use DivineaLabs\Swisseph\Enums\...` lines:

```php
use DivineaLabs\Swisseph\Enums\Asteroid;
use DivineaLabs\Swisseph\Enums\FixedStar;
```

Replace the `selectFixedStar` method body (currently lines ~143-155):

```php
    /**
     * Select a fixed star by catalog name (-pf -xf<name>).
     *
     * Accepts a FixedStar enum for the curated set, or any raw catalog name
     * string from ephe/sefstars.txt. The result row's name column carries the
     * catalog name (e.g. "Sirius,alCMa").
     *
     * @return $this
     */
    public function selectFixedStar(FixedStar|string $name): self
    {
        $name = $name instanceof FixedStar ? $name->value : trim($name);

        if ($name === '') {
            throw InvalidPlanetBodySelectionException::invalidValue($name);
        }

        $this->bodies = [PlanetBodySelection::FIXED_STAR->value];
        $this->bodyTargetArgs = ['xf'.$name];

        return $this;
    }
```

- [ ] **Step 4: Widen `selectAsteroid`**

Replace the `selectAsteroid` method body (currently lines ~184-194):

```php
    /**
     * Select an asteroid by its MPC number (-ps -xs<number>).
     *
     * Accepts an Asteroid enum for the curated set, or any raw MPC number from
     * ephe/seasnam.txt.
     *
     * @return $this
     */
    public function selectAsteroid(Asteroid|int $mpcNumber): self
    {
        $mpcNumber = $mpcNumber instanceof Asteroid ? $mpcNumber->value : $mpcNumber;

        if ($mpcNumber <= 0) {
            throw InvalidPlanetBodySelectionException::invalidValue((string) $mpcNumber);
        }

        $this->bodies = [PlanetBodySelection::ASTEROID->value];
        $this->bodyTargetArgs = ['xs'.$mpcNumber];

        return $this;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS — new tests green and all pre-existing tests in the file (raw string/int paths, empty/non-positive guards) still green.

- [ ] **Step 6: Commit**

```bash
git add src/Support/Positions/PositionsBuilder.php tests/Features/Support/Positions/PositionsBodiesExtraTest.php
git commit -m "feat: accept FixedStar/Asteroid enums in PositionsBuilder selectors"
```

---

## Task 4: Accept `FixedStar` in `OccultationsBuilder` and `HeliacalBuilder`

**Files:**
- Modify: `src/Support/Occultations/OccultationsBuilder.php` (`forStar` ~line 50)
- Modify: `src/Support/Heliacal/HeliacalBuilder.php` (`forStar` ~line 57)
- Test: `tests/Features/Enums/FixedStarBuilderInteropTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `tests/Features/Enums/FixedStarBuilderInteropTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Enums\FixedStar;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

it('accepts a FixedStar enum in OccultationsBuilder::forStar', function () {
    $builder = (new OccultationsBuilder)->forStar(FixedStar::SIRIUS);

    expect($builder)->toBeInstanceOf(OccultationsBuilder::class);
})->throwsNoExceptions();

it('accepts a FixedStar enum in HeliacalBuilder::forStar', function () {
    $builder = (new HeliacalBuilder)->forStar(FixedStar::ALDEBARAN);

    expect($builder)->toBeInstanceOf(HeliacalBuilder::class);
})->throwsNoExceptions();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Features/Enums/FixedStarBuilderInteropTest.php`
Expected: FAIL — `forStar()` expects `string`, given `FixedStar` (TypeError).

- [ ] **Step 3: Widen `OccultationsBuilder::forStar`**

Add the import with the other enum `use` lines near the top of `src/Support/Occultations/OccultationsBuilder.php`:

```php
use DivineaLabs\Swisseph\Enums\FixedStar;
```

Replace the `forStar` method (currently lines ~50-56):

```php
    public function forStar(FixedStar|string $name): self
    {
        $this->star = $name instanceof FixedStar ? $name->value : $name;
        $this->body = null;

        return $this;
    }
```

- [ ] **Step 4: Widen `HeliacalBuilder::forStar`**

Add the import with the other enum `use` lines near the top of `src/Support/Heliacal/HeliacalBuilder.php`:

```php
use DivineaLabs\Swisseph\Enums\FixedStar;
```

Replace the `forStar` method (currently lines ~57-63):

```php
    public function forStar(FixedStar|string $name): self
    {
        $this->star = $name instanceof FixedStar ? $name->value : $name;
        $this->body = null;

        return $this;
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Features/Enums/FixedStarBuilderInteropTest.php`
Expected: PASS (2 passed).

- [ ] **Step 6: Commit**

```bash
git add src/Support/Occultations/OccultationsBuilder.php src/Support/Heliacal/HeliacalBuilder.php tests/Features/Enums/FixedStarBuilderInteropTest.php
git commit -m "feat: accept FixedStar enum in occultation and heliacal builders"
```

---

## Task 5: Documentation

**Files:**
- Modify: `docs/enums.md`
- Modify: `README.md`

- [ ] **Step 1: Add the `FixedStar` section to `docs/enums.md`**

Insert this section directly before the `## HouseSystems (string)` heading (keeps the file roughly alphabetical with the other enum sections):

```markdown
## FixedStar (string)

Namespace: `DivineaLabs\Swisseph\Enums\FixedStar`  
Purpose: Curated set of commonly-used fixed stars. Value = Swiss Ephemeris catalog name (the string passed after `-xf`). Any other star from `ephe/sefstars.txt` may still be passed to the selectors as a raw string.

| Case | Value | Name (`getName()`) | Mag |
|---|---|---|---:|
| SIRIUS | Sirius | Sirius | -1.46 |
| CANOPUS | Canopus | Canopus | -0.74 |
| RIGIL_KENTAURUS | Bungula | Rigil Kentaurus | -0.10 |
| ARCTURUS | Arcturus | Arcturus | -0.05 |
| VEGA | Vega | Vega | 0.03 |
| CAPELLA | Capella | Capella | 0.08 |
| RIGEL | Rigel | Rigel | 0.13 |
| PROCYON | Procyon | Procyon | 0.37 |
| BETELGEUSE | Betelgeuse | Betelgeuse | 0.42 |
| ACHERNAR | Achernar | Achernar | 0.46 |
| ALTAIR | Altair | Altair | 0.76 |
| ACRUX | Acrux | Acrux | 0.81 |
| ALDEBARAN | Aldebaran | Aldebaran | 0.86 |
| ANTARES | Antares | Antares | 0.91 |
| SPICA | Spica | Spica | 0.97 |
| POLLUX | Pollux | Pollux | 1.14 |
| FOMALHAUT | Fomalhaut | Fomalhaut | 1.16 |
| DENEB | Deneb | Deneb | 1.25 |
| REGULUS | Regulus | Regulus | 1.40 |
| CASTOR | Castor | Castor | 1.58 |
| BELLATRIX | Bellatrix | Bellatrix | 1.64 |
| ALGOL | Algol | Algol | 2.12 |
| ALPHECCA | Alphecca | Alphecca | 2.24 |
| ALCYONE | Alcyone | Alcyone | 2.87 |
| POLARIS | Polaris | Polaris | 2.02 |
| ALPHARD | Alphard | Alphard | 1.97 |
| HAMAL | Hamal | Hamal | 2.01 |
| ALPHERATZ | Alpheratz | Alpheratz | 2.06 |
| RASALHAGUE | Rasalhague | Rasalhague | 2.07 |
| DENEBOLA | Denebola | Denebola | 2.13 |
| MENKAR | Menkar | Menkar | 2.53 |
| ZUBENELGENUBI | Zubenelgenubi | Zubenelgenubi | 2.75 |
| ZUBENESHAMALI | Zubeneshamali | Zubeneshamali | 2.62 |
| VINDEMIATRIX | Vindemiatrix | Vindemiatrix | 2.79 |
| MARKAB | Markab | Markab | 2.48 |
| SCHEAT | Scheat | Scheat | 2.42 |
| ALGENIB | Algenib | Algenib | 2.84 |
| SADALMELEK | Sadalmelek | Sadalmelek | 2.94 |
| MIRFAK | Mirfak | Mirfak | 1.79 |
| WEZEN | Wezen | Wezen | 1.84 |
| ALNILAM | Alnilam | Alnilam | 1.69 |
| MIRZAM | Mirzam | Mirzam | 1.97 |
| MIRA | Mira | Mira | var. |
| GALACTIC_CENTER | Galactic Center | Galactic Center | — |

### Bodies outside the curated set

`FixedStar` and `Asteroid` cover the common cases only. For anything else, pass the raw value — the selectors accept it directly:

- Fixed stars: any traditional name, Bayer/Flamsteed designation (e.g. `,alTau`), or line number from `ephe/sefstars.txt` (shipped with the Swiss Ephemeris data). Name matching is case-insensitive and whitespace is ignored.
- Asteroids: any MPC number from `ephe/seasnam.txt`.

```php
Swisseph::positions()->selectFixedStar('Capella')->get();   // raw catalog name
Swisseph::positions()->selectAsteroid(1862)->get();         // raw MPC number (Apollo)
```
```

- [ ] **Step 2: Add the `Asteroid` section to `docs/enums.md`**

Insert this section directly after the `## AstroProperties (string)` section ends and before `## EphOptions (string)` (keeps the file alphabetical):

```markdown
## Asteroid (int)

Namespace: `DivineaLabs\Swisseph\Enums\Asteroid`  
Purpose: Curated set of commonly-used named asteroids and TNOs. Value = MPC number (the int passed after `-xs`). Ceres/Pallas/Juno/Vesta/Chiron/Pholus are not here — they have Swiss Ephemeris constants and live in `PlanetBody`. Any other MPC number from `ephe/seasnam.txt` may be passed to `selectAsteroid()` as a raw int.

| Case | Value (MPC) | Name (`getName()`) |
|---|---:|---|
| EROS | 433 | Eros |
| PSYCHE | 16 | Psyche |
| SAPPHO | 80 | Sappho |
| AMOR | 1221 | Amor |
| LILITH | 1181 | Lilith |
| HYGIEA | 10 | Hygiea |
| APOLLO | 1862 | Apollo |
| ICARUS | 1566 | Icarus |
| ATEN | 2062 | Aten |
| CRUITHNE | 3753 | Cruithne |
| POSEIDON | 4341 | Poseidon |
| QUAOAR | 50000 | Quaoar |
| HIDALGO | 944 | Hidalgo |
| NESSUS | 7066 | Nessus |
| HEKATE | 100 | Hekate |
| NEMESIS | 128 | Nemesis |
| FORTUNA | 19 | Fortuna |
| URANIA | 30 | Urania |
| HEKTOR | 624 | Hektor |
| CHAOS | 19521 | Chaos |
| SEDNA | 90377 | Sedna |
| ERIS | 136199 | Eris |
| ORCUS | 90482 | Orcus |
| VARUNA | 20000 | Varuna |
```

- [ ] **Step 3: Update the README examples**

In `README.md`, replace the fixed-star/asteroid example block (currently lines ~194-198):

```php
// Fixed star by catalog name (FixedStar enum, or any raw name from sefstars.txt)
$frame = Swisseph::positions()->selectFixedStar(FixedStar::SIRIUS)->get();
$frame = Swisseph::positions()->selectFixedStar('Capella')->get(); // raw name still works

// Asteroid by MPC number (Asteroid enum, or any raw number from seasnam.txt)
$frame = Swisseph::positions()->selectAsteroid(Asteroid::EROS)->get(); // 433
$frame = Swisseph::positions()->selectAsteroid(1862)->get();          // raw number (Apollo)
```

Ensure the README's import/use examples include (add near where other enums are imported in the README snippets, if such a block exists; otherwise no change needed since README snippets elsewhere use fully-illustrative short class names):

```php
use DivineaLabs\Swisseph\Enums\Asteroid;
use DivineaLabs\Swisseph\Enums\FixedStar;
```

At README line ~573, optionally update the occultation example `->forStar('Sirius')` to `->forStar(FixedStar::SIRIUS)` to showcase the enum; the raw string form remains valid so this is illustrative only.

- [ ] **Step 4: Verify the full suite is green**

Run: `./vendor/bin/pest`
Expected: PASS — entire suite green (no fixtures changed; docs-only step has no test impact).

- [ ] **Step 5: Commit**

```bash
git add docs/enums.md README.md
git commit -m "docs: document FixedStar/Asteroid enums and long-tail raw input"
```

---

## Self-Review Notes

- **Spec coverage:** FixedStar enum (Task 1), Asteroid enum (Task 2), enum-or-raw API for all three fixed-star sites + asteroid site (Tasks 3-4), docs tables + long-tail note + README (Task 5). All spec sections mapped.
- **Type consistency:** `getName()` on both enums; `FixedStar|string` / `Asteroid|int` union signatures used identically across `PositionsBuilder`, `OccultationsBuilder`, `HeliacalBuilder`; normalization idiom (`$x instanceof Enum ? $x->value : $x`) identical everywhere. `VARUNA = 20000` is the MPC `-xs` number, not `SE_VARUNA` (30000).
- **Catalog accuracy:** `Zubeneshamali` spelling (one `s`) and `Bungula` catalog value for Rigil Kentaurus are both verified and called out where they could trip up an implementer.
```
