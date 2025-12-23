# DTO Reference

This document describes the public Data Transfer Objects (DTO) used by the package.

Conventions:
- **Nullable** means the value can be `null`.
- Array element types are described explicitly (even if the PHP type is currently `array`).
- **Field names are part of the public contract**. Renaming/removing fields is a **breaking change**.
- Adding new optional fields is allowed (non-breaking), as long as existing fields keep meaning and types.

---

## AstroTimeFrame

Namespace: `DivineaLabs\Swisseph\Data\AstroTimeFrame`  
Extends: `Spatie\LaravelData\Data`  
Purpose: Root result object returned by the wrapper (time + place + results).

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| id | int | ✅ | Optional identifier (if persisted externally). Not required for calculations. |
| place | string | ❌ | Human-readable location name (e.g. city). |
| date | Carbon\Carbon | ❌ | Date-time used for calculations. Internally expected to represent the resolved moment (typically UTC after timezone conversion). |
| longitude | float | ❌ | Longitude in decimal degrees. |
| latitude | float | ❌ | Latitude in decimal degrees. |
| house_system | string | ✅ | House system code (e.g. `'P'`, `'K'`, etc.). Usually corresponds to `HouseSystems` enum value. |
| planet_bodies | array | ❌ | List of planetary results. Recommended element shape: `array{planet_body: PlanetBodyData, properties: PlanetBodyPropertyData[]}`. |
| houses | array | ❌ | List of house results. Recommended element shape: `array{house: HouseData, properties: HousePropertyData[]}` or `HousePropertyData[]` depending on implementation. |

### Contract notes

- `planet_bodies` and `houses` are intentionally typed as `array` in PHP, but consumers should rely on the documented element shapes above.
- Property keys inside `PlanetBodyPropertyData.property` are expected to match `AstroProperties::getPropertyName()` values.

---

## HouseData

Namespace: `DivineaLabs\Swisseph\Data\HouseData`  
Extends: `Spatie\LaravelData\Data`  
Purpose: Identifies a house / angle / point (e.g. House 1, Ascendant).

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| index | int | ❌ | Numeric identifier / ordering index. |
| name | string | ❌ | Human-readable name (e.g. `House 1`, `Ascendant`). |

---

## HousePropertyData

Namespace: `DivineaLabs\Swisseph\Data\HousePropertyData`  
Extends: `Spatie\LaravelData\Data`  
Purpose: A computed property associated with a `HouseData` item.

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| label | string | ❌ | Human label for the property. |
| property | string | ❌ | Stable machine key (recommended to align with internal property naming conventions). |
| value | float | ❌ | Numeric value (units depend on property). |

### Contract notes

- `value` is a `float` (unlike `PlanetBodyPropertyData`, which may be `string|float`).

---

## PlanetBodyData

Namespace: `DivineaLabs\Swisseph\Data\PlanetBodyData`  
Extends: `Spatie\LaravelData\Data`  
Purpose: Identifies a planetary body / point (e.g. Sun, Moon, Mean Node).

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| index | int | ❌ | Swiss Ephemeris body index (e.g. 0 = Sun). Often corresponds to `PlanetBody` enum value. |
| name | string | ❌ | Human-readable body name. |

---

## PlanetBodyPropertyData

Namespace: `DivineaLabs\Swisseph\Data\PlanetBodyPropertyData`  
Extends: `Spatie\LaravelData\Data`  
Purpose: A computed property associated with a `PlanetBodyData` item.

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| label | string | ❌ | Human label for the property. |
| property | string | ❌ | Stable machine key. Typically comes from `AstroProperties::getPropertyName()`. |
| value | string\|float | ❌ | Parsed value from CLI output. Kept as `string|float` to preserve formatting/precision where needed. |

### Contract notes

- Consumers should not assume `value` is always numeric. Some CLI outputs may contain formatted strings.
- `property` should be treated as stable API surface (changing it is breaking).

---

## SwissephCommand

Namespace: `DivineaLabs\Swisseph\Data\SwissephCommand`  
Extends: `Spatie\LaravelData\Data`  
Purpose: Represents an executable + argument list for the `swetest` CLI call.

### Fields

| Field | Type | Nullable | Description |
|---|---|---:|---|
| executable | string | ❌ | Absolute or relative path to `swetest` binary. |
| arguments | string[] | ❌ | Command-line arguments **without leading dashes** (e.g. `['eswe', 'nonut', 'b23.03.1987']`). |

### Methods (behavior contract)

#### `toProcessArray(): array`

Returns a Symfony Process-compatible array:

- Output shape: `string[]`
- First element is the executable
- Each argument is prefixed with a dash (`-`)

Example:
- executable: `/path/swetest`
- arguments: `['eswe', 'nonut']`
- result: `['/path/swetest', '-eswe', '-nonut']`

#### `toCliString(): string`

Returns a string suitable for logging/debugging:

- Concatenates executable + space + dash-prefixed args
- Output is intended for display/logging, not necessarily for shell-escaping in every environment

Example:
- `/path/swetest -eswe -nonut`

---

## Stability & Versioning

Breaking changes (require major version bump):
- Renaming/removing any DTO field
- Changing field type (e.g. `float` → `string`)
- Changing meaning of existing fields
- Renaming `property` keys emitted in `PlanetBodyPropertyData` / `HousePropertyData`

Non-breaking changes:
- Adding new optional fields
- Adding new DTO classes
- Adding new property keys (as long as existing keys remain unchanged)
