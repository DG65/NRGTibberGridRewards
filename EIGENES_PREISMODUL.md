# Eigenes Preismodul anbinden / Connect your own price module

🇩🇪 Deutsch · 🇬🇧 [English below](#english)

## Deutsch

Ein Energiemanagement (z. B. das EMS im NRG-Stack) findet Preisquellen über einen gemeinsamen
**`price`-Vertrag**. Wer ein Modul für einen anderen Stromanbieter oder eine andere Preisquelle
schreibt, kann sich mit einer einzigen Funktion einhängen. Referenz sind `TIBBERGR_GetPriceCurve()`
(Endkundenpreis) und `SPOT_GetPriceCurve()` (Börsenpreis); beide liefern dasselbe Format.

### Die Funktion

```php
<PRÄFIX>_GetPriceCurve(int $InstanceID): array
```

Gefunden wird sie über das Präfix deines Moduls (`function_exists('<PRÄFIX>_GetPriceCurve')`). Der
Aufruf ist rein lesend.

### Rückgabe: Liste, aufsteigend nach `start`

Je Zeitabschnitt (Stunde oder Viertelstunde) ein Eintrag. Lücken sind erlaubt, Konsumenten nehmen
keine lückenlose Abdeckung an.

| Feld | Typ | Bedeutung |
|---|---|---|
| `start` | int | Beginn, Unix-Sekunden, **inklusive** |
| `end` | int | Ende, Unix-Sekunden, **exklusiv** (Intervall `[start, end)`) |
| `price` | float | Preis in **ct/kWh brutto** (nicht €/kWh) |
| `basis` | string | Art des Preises: `endkunde` (voller Verbraucherpreis) oder `spot` (Börsenpreis) |
| `netzentgelt` | string | `enthalten` oder `fehlt` |
| `level` | null | Bewusst immer `null`: Die Einstufung „günstig/teuer“ trifft der Konsument einheitlich |
| `contractVersion` | string | `Major.Minor`, derzeit `1.2` |
| `contractType` | string | immer `price` |
| `updatedAt` | int | *optional:* Unix-Sekunden, wann das Modul die Kurve zuletzt von der Quelle geholt hat (Frischemarke) |

Optionale, quellenspezifische Zusatzfelder sind erlaubt (Tibber: `level_tibber`, `components`; Börsenpreis:
`quelle`, `aufloesung`). Konsumenten dürfen sie ignorieren.

### Regeln

- `contractVersion` und `contractType` stehen **in jedem Eintrag**, nicht oben: Die Funktion liefert eine
  Liste, ein Schlüssel auf oberster Ebene würde die Iteration brechen.
- Kompatibel ist nur dieselbe **Major**. Zusätzliche Felder erhöhen nur die Minor.
- Die Slot-Dauer immer aus `end − start` lesen, nie 60 oder 15 Minuten annehmen.
- Preise für heute und morgen, sobald verfügbar. Alles Vergangene kommt aus dem Archiv der
  Preisvariable, nicht aus dieser Funktion.
- Kein Schreibzugriff, kein Rückfallwert (`failsafeSec`) nötig: Der Vertrag ist rein lesend.
- Ohne Daten eine leere Liste liefern, nie einen Fehler werfen.

### Beispiel

```php
[
  [
    'start' => 1794092400, 'end' => 1794093300, 'price' => 31.42,
    'basis' => 'endkunde', 'netzentgelt' => 'enthalten', 'level' => null,
    'contractVersion' => '1.2', 'contractType' => 'price', 'updatedAt' => 1794092000,
  ],
]
```

<a id="english"></a>

## English

An energy management system (for example the EMS in the NRG-Stack) finds price sources through a
shared **`price` contract**. If you write a module for another electricity provider or another price
source, one function is enough to hook in. The references are `TIBBERGR_GetPriceCurve()` (end-customer
price) and `SPOT_GetPriceCurve()` (exchange price); both return the same format.

### The function

```php
<PREFIX>_GetPriceCurve(int $InstanceID): array
```

It is found through your module's prefix (`function_exists('<PREFIX>_GetPriceCurve')`). The call is
read-only.

### Return value: list, ascending by `start`

One entry per period (hour or quarter hour). Gaps are allowed; consumers do not assume gap-free coverage.

| Field | Type | Meaning |
|---|---|---|
| `start` | int | Start, Unix seconds, **inclusive** |
| `end` | int | End, Unix seconds, **exclusive** (interval `[start, end)`) |
| `price` | float | Price in **ct/kWh gross** (not €/kWh) |
| `basis` | string | Kind of price: `endkunde` (full consumer price) or `spot` (exchange price) |
| `netzentgelt` | string | `enthalten` (included) or `fehlt` (missing) |
| `level` | null | Deliberately always `null`: the consumer classifies “cheap/expensive” uniformly |
| `contractVersion` | string | `major.minor`, currently `1.2` |
| `contractType` | string | always `price` |
| `updatedAt` | int | *optional:* Unix seconds of when the module last fetched the curve from its source (freshness marker) |

Optional source-specific extra fields are allowed (Tibber: `level_tibber`, `components`; exchange price:
`quelle`, `aufloesung`). Consumers may ignore them.

### Rules

- `contractVersion` and `contractType` are **in every entry**, not at the top: the function returns a list,
  and a top-level key would break iteration.
- Only the same **major** version is compatible. Additional fields only raise the minor version.
- Always read the slot duration from `end − start`; never assume 60 or 15 minutes.
- Prices for today and tomorrow, as soon as available. Everything in the past comes from the archive of the
  price variable, not from this function.
- No write access and no fallback value (`failsafeSec`) needed: the contract is read-only.
- Without data return an empty list; never throw an error.

### Example

```php
[
  [
    'start' => 1794092400, 'end' => 1794093300, 'price' => 31.42,
    'basis' => 'endkunde', 'netzentgelt' => 'enthalten', 'level' => null,
    'contractVersion' => '1.2', 'contractType' => 'price', 'updatedAt' => 1794092000,
  ],
]
```
