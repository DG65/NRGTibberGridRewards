🇩🇪 [Deutsche Fassung](README.md)

# TibberGridRewards for Symcon

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Module Version](https://img.shields.io/badge/Module_Version-2.12.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![Check Style](https://github.com/DG65/NRGTibberGridRewards/actions/workflows/check-style.yml/badge.svg)](https://github.com/DG65/NRGTibberGridRewards/actions/workflows/check-style.yml)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

The module follows the language of your Symcon (English or German): forms, variables, status and error messages, and the tile. Idents, function names and contract fields stay the same in both languages.

Symcon library that subscribes to the **Tibber Grid Rewards status** via the Tibber app API and
provides it as variables. At its core is an actionable signal (`Grid Reward active`) that you can use to
trigger your own automations.

> Part of the **NRG-Stack** — which module versions fit together is listed in the
> internal suite manifest of the NRG-Stack.

> **Scope:** Consumption/production and live measurement (Pulse) via the official Tibber API are
> **not** covered here – for that, there is the mature module
> [Tibber V.2](https://github.com/da8ter/TibberV2). This library complements it with Grid Rewards
> **and** – optionally, using a separate access token – the end-customer price curve
> (`TIBBERGR_GetPriceCurve()`, see [Price curve for price-driven automations](#price-curve-for-price-driven-automations)).

---

## Table of contents

- [What are Tibber Grid Rewards?](#what-are-tibber-grid-rewards)
- [How the module works](#how-the-module-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Variables](#variables)
- [Tile for the visualization](#tile-for-the-visualization)
- [Wallbox power & EMS handover](#wallbox-power--ems-handover)
- [Price curve for price-driven automations](#price-curve-for-price-driven-automations)
- [Usage example: controlling battery & wallbox](#usage-example-controlling-battery--wallbox)
- [Detecting when Tibber itself controls your vehicle or battery](#detecting-when-tibber-itself-controls-your-vehicle-or-battery)
- [Setting a vehicle setting](#setting-a-vehicle-setting-experimental-since-280)
- [FAQ](#faq)
- [Troubleshooting](#troubleshooting)
- [Technical details](#technical-details)
- [Disclaimer](#disclaimer)
- [License](#license)

---

## What are Tibber Grid Rewards?

With **Grid Rewards**, Tibber pays you for providing flexibility that stabilizes the grid: a
registered flex device (e.g. an electric car or a supported home battery) is temporarily controlled by
Tibber in a way that relieves the grid – e.g. charging during surplus. You receive a reward for it.

During such an event, the Tibber app reports the status **“Delivering”**. This is exactly the signal
the module makes usable in Symcon.

## How the module works

The **TibberGridReward** module signs in with your Tibber app credentials, opens a
persistent WebSocket connection to the Tibber app API and subscribes to the Grid Rewards status of your
home. Every status change is written to Symcon variables immediately.

**Important to understand:** Tibber only controls the flex device **registered with Tibber** itself
(e.g. your electric car). Other devices – such as a GoodWe home battery – are **not** controlled by Tibber.
Instead, you react to the `Delivering` signal in Symcon yourself and control your devices with
your own logic (see [Usage example](#usage-example-controlling-battery--wallbox)).

The signal is tied to **your** participation – it is not a general, region-wide grid signal.
Without a registered flex device, the status permanently stays `Unavailable`.

## Requirements

- Symcon **9.0** or newer.
- An active Tibber contract **and participation in Grid Rewards with a supported device**
  (e.g. an electric car).
- Credentials of the **Tibber app** (email + password). Grid Rewards can only be reached through the
  app API, **not** through the official access token.

## Installation

**Option A – Module Store (recommended):** Open the **Module Store** in the Management Console and
search for **“Tibber Grid Rewards”** → install.

**Option B – manually via GitHub:**

1. In Symcon, open **Module Control** (Object Tree → Core Instances → *Modules*, or via the
   Management Console).
2. **Add module** and enter the URL:
   ```
   https://github.com/DG65/NRGTibberGridRewards
   ```

Then use **Add instance** to create an instance of type **TibberGridReward** (the required
WebSocket client is created automatically as its parent). For the tile, additionally create an instance
of **TibberGridRewardTile** (see [Tile](#tile-for-the-visualization)).

> Never copy modules manually into the `modules/` folder – always use Module Store/Module Control.

## Configuration

| Field | Meaning |
|---|---|
| **Active** | Switches the connection on/off |
| **Tibber app – email** | Email address of your Tibber account |
| **Tibber app – password** | Password of your Tibber account |
| **Home** | Selection of the home (selection list) |

**Initial setup:**

1. Enter email and password, tick **Active**, **Apply**.
2. Click **Reload home list** – the selection list fills with your Tibber homes.
3. Select the desired home and **Apply** again.

After that, the status shows “Active” and the variables are updated live.

> **About the password field:** After applying, it appears empty when you open the form again –
> this is intentional. The value is permanently stored internally only, not as a visible form value;
> the password mask is only used for entering it once or for entering a new password.

## Variables

| Ident | Type | Meaning |
|---|---|---|
| `Delivering` | Boolean | **Primary signal** – `true` while a Grid Reward event is running |
| `State` | String | `Available` / `Unavailable` / `Delivering` |
| `StateReason` | String | Raw reason (`kind` / `reason` / `reasons`) – including `excess` (surplus) / `shortage` (scarcity) → direction |
| `RewardCurrentMonth` | Float | Reward in the current month |
| `RewardAllTime` | Float | Reward in total |
| `Currency` | String | Currency |
| `FlexDeviceCount` | Integer | Number of flex devices |
| `FlexDevices` | String | Readable list of the flex devices including individual status |
| `WallboxPowerTotal` | Float (W) | Sum of the active power of all selected wallboxes |
| `GridRewardWallboxRequest` | Float (W) | **EMS setpoint**: wallbox power while the car is charging (mode 1 or 2), otherwise `0` |
| `GridRewardMode` | Integer | **EMS mode** (a single value): 0 Normal · 1 Car charging · 2 Grid Reward charge · 3 Curtailment |
| `WallboxCharging` | Boolean | `true` if the sum is above the “Charging” threshold |
| `DataValid` | Boolean | `false` if a wallbox reading is missing or outdated |
| `GridRewardEnergyEvent/Today/Month/Total` | Float (kWh) | Energy shifted during Grid Reward per period |
| `GridRewardEffectiveRate` | Float (€/kWh) | KPI: reward (month) ÷ shifted energy (month) |
| `LastEventStart/End/Duration/Energy` | — | Event log of the last Grid Reward event |

## Tile for the visualization

For this, the library contains a **second module**, `TibberGridRewardTile` – a standalone
HTML SDK tile that fills the tile **without a border** (no additional inner frame) and is deliberately
separate from the data logic (a tile problem cannot disturb the data connection).

1. Create an instance of **TibberGridRewardTile**.
2. The **data source** is detected automatically if there is exactly one `TibberGridReward` instance;
   only with several do you have to choose it manually in the form.
3. Place the instance tile on a page in the tile visualization.

By default the **background is transparent** and the text automatically adapts to the light/dark
Symcon theme – so the tile blends in seamlessly. If needed, you can adapt **all
colors** (status, background, boxes, text), the **typeface** and the **font size** in the instance form; the
button **“Reset colors & font to default”** restores everything. The tile
updates automatically whenever a variable of the source changes.

A permanent **mode band** (fixed tile height) shows the `GridRewardMode`: grayed out “No event”,
teal “Car charging · from grid”, green “Charge from grid” and amber “Curtailment”. The status and
curtailment colors have their own color pickers.

> All tile settings are located exclusively in the `TibberGridRewardTile` module. The data module
> `TibberGridReward` no longer contains any display options.

### Simulation buttons on the tile (optional, off by default)

In the tile form, under **🧪 Simulation buttons on the tile**, you can tick a checkbox
that shows four buttons directly on the tile in the Webfront: **Available**, **Simulate charging (excess)**,
**Simulate curtailment (shortage)** and **Back to real status**. They call the same `Simulate()`/`ResetSimulation()`
functions of the connected data source as the buttons in the configuration form – handy for testing
the EMS wiring without opening the instance configuration.

> **Caution:** These buttons trigger real `RequestAction` commands to the automations
> configured in the data source. **Disabled** by default, because anyone with access to the
> Webfront (e.g. family, guests via a shared view) could tap them.

### Rule editor on the tile (optional, off by default)

In the tile form, under **🤖 Show automations**, you can tick a checkbox that shows the configured
If→Then rules on the tile itself – with enable/disable and delete per rule,
as well as a full rule editor (any number of conditions and actions, with dependent
selection lists for comparison and target values).

> **Caution:** The editor lets you directly create, change and delete the automations of the
> data source – including real actuators such as battery discharge lock and wallbox control.
> **Disabled** by default, for the same reason as the simulation buttons.

## Wallbox power & EMS handover

In the data module, under **🔌 Wallboxes (grid import calculation)**, you can select the active power data points of any number
of wallboxes (list, one variable + factor per row, e.g. `1000` if a source
delivers kW). The module:

- **sums up** the active power continuously (event-based via `VM_UPDATE`) →
  `WallboxPowerTotal`,
- checks the readings for **age/plausibility** (`DataValid`, adjustable threshold) – important because
  the EMS controls based on this value,
- provides the **ready-made EMS setpoint** `GridRewardWallboxRequest` (= wallbox sum only in mode
  “Charge from grid”/excess, otherwise `0`) as well as the direction as `GridRewardMode` (see below),
- records the **energy** shifted during an event (event/today/month/total) and calculates the
  **effective €/kWh value** (`GridRewardEffectiveRate`) from it. If you optionally specify an
  **energy meter** per wallbox (“energy delivered in the charging cycle”, kWh), the energy comes **exactly from
  that meter** (reset-proof against cycle rollbacks); without a meter it is integrated from the power,
- keeps an **event log** (start/end/duration/energy of the last event).

The EMS only has to read the variables with stable idents (above all `Delivering` and
`GridRewardWallboxRequest`). The actual inverter/battery control deliberately stays in the EMS –
this module only provides clean input values. The total wallbox power is additionally
shown in the tile.

### EMS mode (`GridRewardMode`) – one value for control

`GridRewardMode` combines all relevant situations into **one** value from which the EMS derives
the action directly. It is formed from the Grid Reward status (`Delivering` + `StateReason`) **and** the
charging state of the wallboxes:

| Value | Situation | Typical EMS reaction |
|---|---|---|
| `0` | **Normal** – no event, car is not charging | Self-consumption as usual; manage the battery by price |
| `1` | **Car charging, no reward** (smart charge / forced charging / release) | Wallbox load **from the grid** (reduce battery discharge by the wallbox power) |
| `2` | **Grid Reward: charge** (`excess`) | like 1, **plus** charge the battery from the grid (rewarded); **never** discharge the battery |
| `3` | **Grid Reward: curtailment** (`shortage`) | Car off, house from battery/PV → minimize grid import |

The cases “price ok / release” and “forced charging” deliberately fall into **value 1** – the EMS action
(power from the grid) is identical there. `GridRewardWallboxRequest` is the associated power
(active for value 1 **and** 2). Energy counting and event log refer to **`excess` (value 2)** –
the period in which charging actually takes place from the grid for the reward.

### Automations “If → Then” – configurable without your own script

So that **every user** can freely define how their EMS/inverter reacts, the data module has a list of
**rules** under **🎯 Automations (If → Then)**. Each rule consists of:

- a **condition** on a data point of this instance (e.g. “Grid reward mode = 2”, “Wallbox
  charging turns ON”, “Status detail changes”),
- **target variable 1 + action + value 1** (required),
- **target variable 2 + action + value 2** (optional) – for the typical case that a Grid Reward
  transition needs **two data points at the same time** (e.g. the operating mode switch of your
  inverter **and** a power setpoint) – both in **one** rule, without two separate
  configurations.

As soon as the condition occurs, the module sets both target variables automatically via `RequestAction`
(**edge-triggered** – a rule fires when the condition occurs, not again with every data message). The
available actions are Switch on/Switch off/Toggle/Set value; a value you enter is converted
automatically to the type of the target variable (number, `true`/`false` or text). There is a special
placeholder for the value:

- **`WALLBOX`** (upper or lower case) is replaced by the wallbox power currently required
  and is **tracked live** as long as the condition stays true – so the EMS only buys as much energy
  from the grid as is actually needed for charging. A write only happens on a
  relevant change, so the actuator is not spammed.
- Any other text/number is used as a **fixed value** (useful, for example, for modes in which the
  power setpoint is ignored anyway because the operating mode is “Automatic”).

**Any number of rules** are allowed – if your EMS needs more than two data points for one
transition, simply create another rule with the same condition.

**Looking up values:** Because each rule can have a different target variable with a different profile,
Symcon does not support a selection list here that adapts to the target variable chosen in the same row
(list columns are the same for all rows). As a substitute, there is **“🔍 Look up values”** below the list: choose a variable,
apply, click **“Show values”** – below it, all possible values of the target variable
appear as text (e.g. “0 = Stopped · 1 = Automatic · …”) to read off and type into comparison value/value 1/2.

**Convenient rule editor in the tile:** The `TibberGridRewardTile` tile offers, under
**🤖 Show automations** (**disabled** by default, see the warning there), an
interactive editor directly in the Webfront – with **any number of AND-linked conditions** and
**any number of actions** (up to 6) per rule, each with real, independent selection lists
for comparison and target values (where the respective variable has fixed values). Rules can
also be enabled/disabled and deleted right there, without opening the instance configuration. This also covers
more complex, dynamic logic that would require several rows with different
target variables and profiles in the classic form.

For very dynamic logic (e.g. “only charge if the battery was already charging at the start of the event, and
calculate the power from the previous setting + wallbox power”), your own
event script on `GridRewardMode` remains the better choice – the module still provides
all the required values for that (`GridRewardMode`, `GridRewardWallboxRequest`, `WallboxPowerTotal`).

> **Updating from an older version:** An existing configuration from the fields
> “EMS power mode/setpoint” (up to version 1.15.x) or from the “Automations” rows (1.16.0/
> 1.17.0) is automatically and once converted to the new rule format on first start – no
> manual rework needed.

### Economically optimal control (strategy for the EMS)

Basic rule: **every kWh from the cheapest source.** Marginal costs: PV (~0) < battery **value** (= most expensive
hour you are saving it for) vs. grid price − Grid Reward.

- **Charging the car ⇒ always from the grid** (it only charges in cheap/rewarded periods; the battery is more valuable
  for the expensive evening). → Modes 1 and 2.
- **`excess` (mode 2):** Grid import is rewarded → charge car **and** battery from the grid, never
  discharge the battery.
- **`shortage` (mode 3):** Grid import is “penalized” (reward for reducing) → car off, house from
  battery/PV.
- **Battery arbitrage** (when to charge from / discharge to the grid) is done by the **EMS based on the Tibber prices**:
  charge if `price + losses < battery value`, discharge if `price − reward > battery value`. This module provides
  the Grid Reward layer as well as – optionally, see below – the price curve itself
  (`TIBBERGR_GetPriceCurve()`); alternatively, TibberV2 provides the price forecast.

## Price curve for price-driven automations

Optional and **completely independent** of the Grid Rewards part above: With your own
[access token](https://developer.tibber.com/settings/access-token) – called “Personal
Access Token” at Tibber – the module delivers the end-customer price curve via
the **official** Tibber API as a public function:

```php
TIBBERGR_GetPriceCurve(int $InstanzID): array
// List, ascending by 'start'. Gaps are allowed – do not assume gap-free coverage.
// [[ 'start' => int,        // Unix timestamp, slot start (inclusive)
//    'end'   => int,        // Unix timestamp, slot end (EXCLUSIVE -> interval [start, end))
//    'price' => float,      // Gross price in ct/kWh (not EUR/kWh), incl. VAT
//    'basis' => 'endkunde', // this module always delivers the complete end-customer price
//    'netzentgelt' => 'enthalten', // time-variable grid fees are already included in the price
//    'level' => null,       // classification is the EMS's job, see below
//    'level_tibber' => string|null, // Tibber's own price level, unchanged (e.g. "VERY_CHEAP")
// ], …]
```

A list instead of a single object (even when the result is empty), so that later extensions do not
break the signature – the same convention as the other module-suite contracts (e.g. `MHUB_GetFunctions`).

**Price breakdown for invoice checking (optional):** If the breakdown is enabled in the “🧾 Tariff & grid fees (invoice check)” panel
and the grid-area values are entered, each slot additionally contains:

```php
//    'components' => [        // all ct/kWh NET
//        'spot'           => float,  // exchange price (EPEX), remainder = price_net − all surcharges; can be negative
//        'beschaffung'    => float,  // Tibber surcharge §4 terms
//        'netzentgelt'    => float,  // §14a module 3 by time of day + quarter, otherwise work price
//        'steuernAbgaben' => float,  // concession levy + electricity tax + levies
//    ],
//    'vat'          => float,        // VAT in %, e.g. 19
//    'tibberEnergy' => float|null,   // Tibber's own two-part split (raw) as an independent …
//    'tibberTax'    => float|null,   // … cross-check anchor
```

`components` is a **reconstruction** from the configuration; `price` remains Tibber's authoritative
figure. If they differ, that is an audit finding (the same separation as with `level`). By construction, the sum of the
`components` equals the net price exactly. Confirmed on the live system: the `spot` formed this way
matches Tibber's own `tibberEnergy` across all rate levels to within ~0.01 ct/kWh
– the independent cross-check that the grid config is correct. The **fixed** items (grid base price,
§14a reduction, Tibber base fee) deliberately do **not** belong in `components` – they are not per
kWh and go into a separate monthly bill.

**Why `level` is always `null`:** `CHEAP`/`NORMAL`/`EXPENSIVE` would be Tibber's own vocabulary, calculated from a
moving average – a spot price source would not have it and would have to
replicate it. If the same field appeared with two different calculations, an EMS could
**decide differently for an identical price situation depending on the source**, without anyone noticing. The
classification is therefore deliberately the EMS's job, uniform for all price sources – not this module's.
Tibber's own, unchanged classification is still available, but clearly separated in
`level_tibber`.

**Why a separate access token instead of passing it through from TibberV2:** Such a token
(`developer.tibber.com`) is intended by design for the price/consumption data of the official API,
does not expire and makes this module **self-sufficient** for it – without a silent dependency on
a third-party instance whose maintenance state you cannot influence.

**Why the full end-customer price and not a pure spot price:** With time-variable grid fees
(e.g. §14a EnWG module 3), the difference between the high and low grid-fee rate can amount to several
cents/kWh – often more relevant for a charging decision than the spot price fluctuation itself. An
EMS that only sees the spot price makes the wrong decision in such time windows (charges too little at night,
does not avoid the evening peak). That is why `price` always delivers the complete price that
Tibber actually charges the customer.

**Setup:** In the “💶 Price curve (official Tibber API)” panel, enter the access token and apply – if there is
only one home, it is selected automatically; otherwise choose it from the list below and apply again. The curve is then
requested again every 20 minutes – often enough to pick up the prices for the
following day promptly once Tibber publishes them (usually between 1 and 2 pm). The convenience variables “Current price” (€/kWh) and “Current price level” – the latter
labeled in plain words for Tibber's five levels (very cheap … very expensive) – show
the current slot for a quick look, without having to call the function.

> The access token also appears empty when you reopen the form after applying – see the note on the
> password field above, it applies here in the same way.

**Price resolution (hourly / quarter-hourly):** Tibber has billed quarter-hourly in Germany since 1 October 2025,
but delivers the 15-minute prices via the API only on explicit request. The “Price resolution” setting
defaults to **Automatic**: the module requests the finer quarter-hour prices and falls back to hourly values if
the tariff does not provide 15-minute prices (older or foreign tariffs). This is not cosmetic – the hourly value
is an average over the four quarter hours and can differ from the actually billed quarter-hour price
by more than 1 ct/kWh. If you are billed hourly, you can set the resolution to “Force hourly”; however, that is not necessary, “Automatic” does the right thing in both cases. The slot width
is available in the `GetPriceCurve()` contract via `start`/`end` – consumers must therefore **never** assume
60 minutes, but read the duration from `end − start`.

### Archive price history (invoice check)

With the **“📈 Enable archiving of the price”** button (panel “Price curve”) you enable archiving of the
“Current price” variable and thereby build a gap-free price history – the basis for recalculating a
Tibber invoice later.

To make this reliable, the price is switched **exactly at the change of the hour or quarter hour**
(own timer, purely from the cache, without additional API calls) and kept with **4 decimal places**.
Neither is a detail: if the price were only updated in the 20-minute grid of the API
request, the change in the archive would be off by up to 20 minutes; rounding to 3 places
would already amount to about 0.3 ct/kWh deviation.

**Without archiving there is no price look-back.** `TIBBERGR_GetPriceCurve()` deliberately returns only
today and tomorrow; everything in the past comes from the archive. This concerns not only invoice checking,
but also the historical price curve in evaluations and tiles of other modules (e.g. the
monitoring of [InverterHub](https://github.com/DG65/NRGInverterHub), which draws the look-back from this
archived variable and the preview from the function). Recording starts **from the moment you click
the button** – there is no data retroactively.

> **Note:** The module never enables archiving by itself, only when you click (since 2.12.0; before that there
> was a checkbox). The button only switches archiving **on**, never off – archiving that is already running
> stays in place, otherwise the recording would silently break off.
> To switch it off, remove archiving directly at the variable.

> **What is still missing:** A complete invoice check needs not only the price but also the
> **consumption per time slice**. This module does not provide it – it comes from the meter
> (MeterHub) or from “Tibber V.2”. This module provides the price side.

## Usage example: controlling battery & wallbox

Typical use case with a large home battery and a wallbox: during a Grid Reward event, the
**battery should not discharge** and the **wallbox load should be drawn from the grid** – so that the behavior
requested by the grid is not undermined by battery discharge.

To do this, create a **triggered event** on the `Delivering` variable (trigger: “on change”) with the
following script:

```php
<?php
// $_IPS['VALUE'] = new value of "Delivering"
$delivering = $_IPS['VALUE'];

if ($delivering) {
    // Grid Reward event starts:
    // set the home battery to discharge lock and draw the wallbox load from the grid.
    // -> insert your GoodWe/EMS control commands here, e.g.:
    // GOODWE_SetEntladesperre($speicherInstanzID, true);
} else {
    // Event ended: restore normal operation.
    // GOODWE_SetEntladesperre($speicherInstanzID, false);
}
```

The concrete control commands depend on your battery/EMS module. The module only provides the
signal – controlling the devices stays in your hands.

> **Check the direction:** Whether an event means “charge from the grid” (surplus) or “discharge into the grid”
> (scarcity) is detailed in `StateReason`. Observe this variable during the first
> real events before you finalize your control logic.

### Detecting when Tibber itself controls your vehicle or battery

If Tibber at some point registers a vehicle or a home battery directly as a flex device (e.g. through
a future battery partnership), Tibber accesses the device **outside** of Symcon –
your EMS would otherwise not notice. `TIBBERGR_GetActiveControls()` closes exactly this gap:

```php
TIBBERGR_GetActiveControls(int $InstanzID): array
// Empty if Tibber is not currently controlling any device. Otherwise, per device:
// [[ 'contractVersion' => '2.0',
//    'type'       => 'vehicle' | 'battery' | 'charger', // currently only the first two are possible
//    'deviceId'   => string,  // Tibber's own vehicleId/batteryId – see below
//    'name'       => string,  // display name at Tibber
//    'make'       => string,  // manufacturer, Tibber's raw value
//    'managedBy'  => 'tibber',
//    'reason'     => string,  // plain text, e.g. "Grid Reward aktiv (excess) seit 14:32"
//    'since'      => int,     // Unix timestamp, since when continuously active
//    'valid'      => bool,    // is the WebSocket connection currently active?
// ], …]
```

This is **not a control command and not an override** – this module cannot prevent Tibber's access,
it can only report that it is currently happening, so that a higher-level control (your EMS) can redirect other
resources instead of working against Tibber. `deviceId` is Tibber's own, internal
device ID – **not a vehicle identification number (VIN) and not a local Symcon instance ID**. Without a manually
maintained cross-reference, it cannot be assigned automatically to a local vehicle/battery instance,
but it is stable across multiple queries (reliably distinguishes several devices
from each other). `name`/`make` remain the way to identify a device in a human-readable way.

### Setting a vehicle setting (experimental, since 2.8.0)

`TIBBERGR_SetVehicleSetting()` sets a smart charging preference directly on the vehicle at Tibber –
departure time per weekday, minimum charge level, smart charging on/off, solar consideration:

```php
TIBBERGR_SetVehicleSetting(int $InstanzID, string $VehicleId, string $Key, ?string $Value): array
// ['contractVersion' => '1.0', 'success' => bool, 'error' => string|null, 'charging' => array|null]
```

`$Key` is restricted to a fixed list: `online.vehicle.smartCharging.isEnabled`,
`.minChargeLimit` (0–75 in steps of 5), `.isChargingOnOverProductionEnabled`,
`.departureTimes.montag` … `.sonntag` (format `"HH:MM:SS"`, or `null` to delete the
departure time). `$Value` is passed as a string but, depending on `$Key`, is sent internally as the matching
GraphQL type (bool or number without quotation marks for `isEnabled`/
`isChargingOnOverProductionEnabled`/`minChargeLimit`, string for `departureTimes.*`).

> **Important:** This is **not an officially documented Tibber function** but a mutation
> identified by capturing network traffic from your own app usage – it can change
> or disappear at any time. No two-controller problem: it only sets a preference **within**
> Tibber's own smart charging algorithm, not a charging command that could compete with another
> controller.

## FAQ

**Do I need a Tibber Pulse / Watty?**
No. Grid Rewards is independent of real-time consumption measurement.

**Why not the official API token for Grid Rewards?**
The Grid Rewards status is not available through the official Tibber API (access token),
only through the app API, which requires an app login (email/password). The Personal Access
Token is still used – but only for the optional price curve (see
[Price curve for price-driven automations](#price-curve-for-price-driven-automations)), which
is a completely different, independent part of the module.

**Does the module control my inverter/battery directly?**
No. It only provides the status. You build the device control in Symcon yourself, triggered by the
`Delivering` variable.

**Why do I need a control signal for the EMS at all?**
During a Grid Reward event, the car is deliberately charged from the grid. Without a countermeasure,
a home battery would cover this load from the **battery** – the result: the battery is
empty **and** the energy “rewarded” from the grid did not actually come from the grid. With the
`Delivering` signal (and `GridRewardWallboxRequest`), your EMS makes sure the battery
**does not discharge** during this period and the wallbox load comes from the grid – only then does the event truly count.

**`State` permanently stays “Unavailable” – why?**
Then no supported device is currently taking part in Grid Rewards or none is registered. Without a
registered flex device, no `Delivering` signal is produced.

## Simulation without a real event

Real Grid Reward events are often rare and unpredictable – to test your own EMS wiring,
the form has three buttons under **🧪 Simulation (for testing without a real event)**: **Available (no event)**, **Simulate charging (excess)**
and **Simulate curtailment (shortage)**. They run through exactly the same code as a real
Tibber event – mode determination, energy counting, event log **and** the configured automations
(“🎯 Automations (If → Then)”). This lets you check the complete chain without waiting for a
real event.

> **Caution:** The simulation really triggers the configured `RequestAction` commands to your
> target variables (e.g. the power mode of your inverter). This is intended – a
> real functional test – but not a purely passive “look”.

The simulation stays in place until either the next real Tibber message arrives or you
click **“↩ Back to real status”**. This button immediately shows the last known real
(non-simulated) status as an approximation – and additionally **actively requests a fresh status directly
from Tibber** (the running subscription is cancelled and immediately re-subscribed). The fresh message
arrives right away as a normal update and automatically overwrites the approximation if the
real status has changed in the meantime – more reliable than relying only on a cached
state.

## Troubleshooting

- **Status “Login failed”:** Check email/password of the **Tibber app** (not the API token).
- **Status “No home selected”:** Click *Reload home list* and select a home.
- **No values:** Open the instance's **Debug** (right-click → Debug). There, `Login erfolgreich`
  (debug messages stay in German), `connection_ack` and `next` packets should appear one after the other. A WebSocket client as parent
  must be present and active.
- **Connection drops:** The module automatically signs in again when data stops arriving
  (watchdog/relogin sequence).

## Technical details

- **Login:** `POST https://app.tibber.com/v1/login.credentials` → JWT; automatic token refresh
  shortly before expiry.
- **Home:** `POST https://app.tibber.com/v4/gql` (`me { homes { id title } }`).
- **Status:** WebSocket `wss://app.tibber.com/v4/gql/ws` with subprotocol `graphql-transport-ws`;
  subscription `gridRewardStatus(homeId)`.
- **Parent:** Symcon WebSocket client (I/O), created automatically as the parent.

## Disclaimer

This project uses an **unofficial** Tibber app interface that can change at any time without
notice. It is not affiliated with Tibber. Use at your own risk.

## License

[PolyForm Noncommercial License 1.0.0](LICENSE) © 2026 Dietmar Gureth (DG65) — part of the **NRG-Stack**.
Private and non-commercial use is free; **commercial use requires a separate
license** (contact: DG65). Older versions that were published under MIT remain MIT.
