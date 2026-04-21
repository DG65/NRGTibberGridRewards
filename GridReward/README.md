
# Tibber Grid Reward IP-Symcon Modul

Dieses Modul liest **Grid Reward Events** aus der Tibber GraphQL API.

⚠️ Tibber publiziert **keine offiziell dokumentierte Grid-Rewards-API**.
Dieses Modul ist daher **generisch** und erlaubt die freie Konfiguration:

- GraphQL Query
- JSON-Pfad zur Aktiv-Erkennung

## Benötigt
- Tibber API Token

## Ausgabevariablen
- `GridRewardActive` (bool)

## Typischer Einsatz
Das Modul wird vom EnergyAutopilot abgefragt und hat **höchste Priorität**.
