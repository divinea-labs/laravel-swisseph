# Fixed Star & Named Asteroid convenience enums

**Date:** 2026-06-14
**Status:** Approved (design), pending implementation plan

## Problem

The package wraps swetest with strongly-typed enums for nearly every selectable
entity (`PlanetBody`, `PlanetBodySelection`, `HouseSystems`, `Sidereal`,
`AstroProperties`, …). Two selectors are the exception:

- `selectFixedStar(string $name)` — fixed stars are passed as raw catalog strings.
- `selectAsteroid(int $mpcNumber)` — asteroids are passed as raw MPC numbers.

This forces magic values into user code. The motivating example from the README:

```php
// Asteroid by Minor Planet Center number (433 = Eros)
$frame = Swisseph::positions()->selectAsteroid(433)->get();
```

`433` is opaque; nothing tells the reader it means Eros, and there is no
discoverable, type-checked list of the common bodies.

### Why no enum existed

This is a deliberate-but-inconsistent trade-off, not a pure oversight. The
underlying catalogs are *open* and *huge*:

- Fixed stars: `ephe/sefstars.txt`, ~1357 entries. Lookup is by traditional name
  (case-insensitive, whitespace-stripped), Bayer/Flamsteed designation
  (`,alTau`), or sequential line number.
- Asteroids: `ephe/seasnam.txt`, ~600,000 MPC-numbered entries. Only six have
  reserved `SE_*` constants (Chiron, Pholus, Ceres, Pallas, Juno, Vesta) — and
  those are **already** modelled in `PlanetBody` and selected via
  `selectBodies()`, not `selectAsteroid()`.

A full enum of either catalog is the wrong call — not primarily for CPU reasons
(with opcache a large enum is survivable), but because of source size,
unmaintainable `match` arms, and near-zero value (the long tail is rarely used).

## Goals

1. Provide **curated** enums for the commonly-used named fixed stars and
   asteroids, so `433` becomes `Asteroid::EROS` in user code.
2. **Preserve raw input** (`string` / `int`) for the long tail — zero breaking
   changes.
3. Make the curated set **discoverable**: self-documenting enums + a table in
   `docs/enums.md`, plus docs pointing to the Swiss Ephemeris catalog files for
   everything outside the curated set.

## Non-goals

- Generating the full ~1357-star or ~600k-asteroid catalog into PHP. Out of scope.
- Adding a name→number lookup service or bundling catalog data into the repo.
  The source of truth for the long tail remains the ephemeris files
  (`sefstars.txt`, `seasnam.txt`).
- Re-modelling the six `SE_*` asteroids already in `PlanetBody`.

## Design

### New enum: `FixedStar` (string-backed)

`src/Enums/FixedStar.php`, namespace `DivineaLabs\Swisseph\Enums`.

- **Backing value = the Swiss Ephemeris catalog traditional name** (the exact
  string swetest expects after `-xf`). e.g. `case SIRIUS = 'Sirius';`
- `getName(): string` — human display label (convention across the package).
  For most stars this equals the value; the one divergence is
  `RIGIL_KENTAURUS` whose catalog value is `'Bungula'` but display name is
  `'Rigil Kentaurus'`.
- ~44 cases (see Appendix A). Selection criteria: all first-magnitude stars +
  classical astrological stars (incl. Behenian/Royal stars) + Galactic Center.

Case-name note: case identifiers use the popular name; the **value** is whatever
the catalog uses. Documented inline so nobody "fixes" `Bungula` into
`Rigil Kentaurus`.

### New enum: `Asteroid` (int-backed)

`src/Enums/Asteroid.php`, namespace `DivineaLabs\Swisseph\Enums`.

- **Backing value = MPC number** (the int swetest expects after `-xs`).
  e.g. `case EROS = 433;`
- `getName(): string` — asteroid name (e.g. `'Eros'`).
- ~24 cases (see Appendix B). Excludes Ceres/Pallas/Juno/Vesta/Chiron/Pholus —
  those have `SE_*` constants and live in `PlanetBody` (different selection
  path). A class-doc comment states this exclusion explicitly.
- `VARUNA = 20000` uses the MPC number (the `-xs` argument), not the
  `SE_VARUNA` (30000) internal constant.

### API integration — accept enum OR raw (overload)

Widen the existing signatures; raw input keeps working unchanged.

| File | Method | New signature |
|---|---|---|
| `src/Support/Positions/PositionsBuilder.php` | `selectFixedStar` | `selectFixedStar(FixedStar\|string $name): self` |
| `src/Support/Positions/PositionsBuilder.php` | `selectAsteroid` | `selectAsteroid(Asteroid\|int $mpcNumber): self` |
| `src/Support/Occultations/OccultationsBuilder.php` | `forStar` | `forStar(FixedStar\|string $name): self` |
| `src/Support/Heliacal/HeliacalBuilder.php` | `forStar` | `forStar(FixedStar\|string $name): self` |

Normalization at the top of each method:

```php
// fixed star
$name = $name instanceof FixedStar ? $name->value : trim($name);
// asteroid
$mpcNumber = $mpcNumber instanceof Asteroid ? $mpcNumber->value : $mpcNumber;
```

After normalization the existing validation/branching is unchanged: empty-string
guard for stars, `<= 0` guard for asteroids, then the same
`bodies` / `bodyTargetArgs` assignment. The enum path is always valid by
construction, so it simply flows through the existing guards.

`OccultationsBuilder::forStar` / `HeliacalBuilder::forStar` are included for
consistency — fixed stars are selectable in all three builders and all three
should accept the enum.

### Discoverability / docs

- `docs/enums.md`: add a `FixedStar` table (case → catalog value → display name →
  magnitude) and an `Asteroid` table (case → MPC number → name), matching the
  existing per-enum sections.
- Add a short "bodies outside the curated enums" note in `docs/enums.md` (and/or
  README near the fixed-star/asteroid examples) explaining that any entry from
  `ephe/sefstars.txt` / `ephe/seasnam.txt` can still be passed as a raw
  string/int, and where those files come from.
- README: update the motivating examples to use the enums, e.g.
  `selectAsteroid(Asteroid::EROS)` and `selectFixedStar(FixedStar::SIRIUS)`,
  keeping a one-line mention that raw values still work.

## Testing

- **Enum sanity tests**: every `FixedStar` value resolves in the catalog format
  expected (unit-level: value is non-empty, `getName()` returns non-empty);
  every `Asteroid` value is a positive int; `from()`/`tryFrom()` round-trip.
- **Builder tests** (extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`):
  - `selectFixedStar(FixedStar::SIRIUS)` produces the same args as
    `selectFixedStar('Sirius')` (`-pf -xfSirius`).
  - `selectAsteroid(Asteroid::EROS)` produces the same args as
    `selectAsteroid(433)` (`-ps -xs433`).
  - Raw-input paths and existing validation (empty string, non-positive int)
    remain green.
- **Occultation / Heliacal builders**: enum and string produce identical
  `-xf<name>` output.
- No new fixtures needed — output shape is already covered by existing
  Sirius/Eros fixtures.

## Risks / trade-offs

- **Catalog drift**: if Swiss Ephemeris renames/removes a curated star, its enum
  value could stop resolving. Mitigated by keeping the set small and well-known;
  values were verified against the shipped `sefstars.txt` / `seasnam.txt` on
  2026-06-14.
- **Case-name vs value divergence** (`RIGIL_KENTAURUS`/`Bungula`): documented
  inline to prevent well-meaning "corrections".
- Union-type signatures require the project's min PHP (already 8.x; backed enums
  + union types are fine on the supported Laravel 12/13 baseline).

## Appendix A — `FixedStar` cases (value | display | mag)

Verified against `ephe/sefstars.txt` on 2026-06-14.

```
SIRIUS            Sirius           -1.46    ALPHECCA        Alphecca         2.24
CANOPUS           Canopus          -0.74    ALCYONE         Alcyone          2.87
RIGIL_KENTAURUS   Bungula*         -0.10    POLARIS         Polaris          2.02
ARCTURUS          Arcturus         -0.05    ALPHARD         Alphard          1.97
VEGA              Vega              0.03    HAMAL           Hamal            2.01
CAPELLA           Capella           0.08    ALPHERATZ       Alpheratz        2.06
RIGEL             Rigel             0.13    RASALHAGUE      Rasalhague       2.07
PROCYON           Procyon           0.37    DENEBOLA        Denebola         2.13
BETELGEUSE        Betelgeuse        0.42    MENKAR          Menkar           2.53
ACHERNAR          Achernar          0.46    ZUBENELGENUBI   Zubenelgenubi    2.75
ALTAIR            Altair            0.76    ZUBENESHAMALI   Zubeneshamali    2.62
ACRUX             Acrux             0.81    VINDEMIATRIX    Vindemiatrix     2.79
ALDEBARAN         Aldebaran         0.86    MARKAB          Markab           2.48
ANTARES           Antares           0.91    SCHEAT          Scheat           2.42
SPICA             Spica             0.97    ALGENIB         Algenib          2.84
POLLUX            Pollux            1.14    SADALMELEK      Sadalmelek       2.94
FOMALHAUT         Fomalhaut         1.16    MIRFAK          Mirfak           1.79
DENEB             Deneb             1.25    WEZEN           Wezen            1.84
REGULUS           Regulus           1.40    ALNILAM         Alnilam          1.69
CASTOR            Castor            1.58    MIRZAM          Mirzam           1.97
BELLATRIX         Bellatrix         1.64    MIRA            Mira             var.
ALGOL             Algol             2.12    GALACTIC_CENTER Galactic Center  —
```

`*` `RIGIL_KENTAURUS` — display name "Rigil Kentaurus", catalog value `Bungula`.

## Appendix B — `Asteroid` cases (case | MPC number)

Verified against `ephe/seasnam.txt` on 2026-06-14. Excludes the six `SE_*`
asteroids already in `PlanetBody`.

```
EROS      433     HIDALGO   944
PSYCHE    16      NESSUS    7066
SAPPHO    80      HEKATE    100
AMOR      1221    NEMESIS   128
LILITH    1181    FORTUNA   19
HYGIEA    10      URANIA    30
APOLLO    1862    HEKTOR    624
ICARUS    1566    CHAOS     19521
ATEN      2062    SEDNA     90377
CRUITHNE  3753    ERIS      136199
POSEIDON  4341    ORCUS     90482
QUAOAR    50000   VARUNA    20000
```
