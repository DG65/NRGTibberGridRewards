<?php
declare(strict_types=1);

// Prüfstand Vertrags-Selbstbeschreibung (SUITE.md "Fremdanbindung", contractType, seit 2.13.0):
// GetPriceCurve/GetTariffConfig/GetFlexDevices/GetActiveControls tragen contractType additiv neben
// contractVersion (Minor erhöht, Major unverändert). Lädt das ECHTE module.php mit gestubbten IPS-Funktionen.
// Aufruf: php .tools/test-contracts.php (0 = alles bestanden)

const IS_EBASE = 200;
const KR_READY = 10103;
const IPS_KERNELMESSAGE = 10100;
const VM_UPDATE = 10603;

$S = ['props' => [], 'attrs' => []];
class IPSModule {
    public $InstanceID = 99;
    public function __construct($id = 99) {}
    public function ReadPropertyBoolean($n) { global $S; return $S['props'][$n] ?? false; }
    public function ReadPropertyInteger($n) { global $S; return $S['props'][$n] ?? 0; }
    public function ReadPropertyString($n) { global $S; return $S['props'][$n] ?? ''; }
    public function ReadPropertyFloat($n) { global $S; return $S['props'][$n] ?? 0.0; }
    public function ReadAttributeBoolean($n) { global $S; return $S['attrs'][$n] ?? false; }
    public function ReadAttributeString($n) { global $S; return $S['attrs'][$n] ?? ''; }
    public function ReadAttributeInteger($n) { global $S; return $S['attrs'][$n] ?? 0; }
    public function ReadAttributeFloat($n) { global $S; return $S['attrs'][$n] ?? 0.0; }
    public function WriteAttributeInteger($n, $v) { global $S; $S['attrs'][$n] = $v; }
    public function Translate($t) { return $t; }
    public function SendDebug($a, $b, $c) {}
}
function IPS_GetInstance($id) { return ['InstanceStatus' => 102, 'ConnectionID' => 0]; }
function IPS_InstanceExists($id) { return true; }

require __DIR__ . '/../TibberGridReward/module.php';
$m = (new ReflectionClass('TibberGridReward'))->newInstanceWithoutConstructor();

$fails = 0;
function expect(string $label, bool $ok, string $detail = ''): void {
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . " $label" . (($detail !== '' && !$ok) ? "\n        -> $detail" : '') . "\n";
    if (!$ok) { $fails++; }
}
function major(string $v): string { return explode('.', $v)[0]; }

// ---- GetPriceCurve ----
$S['props'] = ['PriceHomeID' => 'home1'];
$S['attrs'] = ['PriceApiTokenSecret' => 'tok', 'PriceCache' => json_encode(['fetchedAt' => time(), 'slots' => [
    ['start' => 1794092400, 'end' => 1794093300, 'price' => 31.42, 'basis' => 'endkunde', 'netzentgelt' => 'enthalten', 'level' => null, 'level_tibber' => 'CHEAP'],
    ['start' => 1794093300, 'end' => 1794094200, 'price' => 33.1, 'basis' => 'endkunde', 'netzentgelt' => 'enthalten', 'level' => null, 'level_tibber' => null],
]])];
$curve = $m->GetPriceCurve();
expect('Preiskurve: zwei Slots geliefert', count($curve) === 2);
expect('Preiskurve: contractType = price in JEDEM Slot', count(array_filter($curve, fn($s) => ($s['contractType'] ?? '') === 'price')) === 2);
expect('Preiskurve: contractVersion 1.2 in jedem Slot, Major unverändert 1', count(array_filter($curve, fn($s) => ($s['contractVersion'] ?? '') === '1.2' && major($s['contractVersion']) === '1')) === 2);
expect('Preiskurve: Kernfelder unverändert (start/end/price/basis/netzentgelt/level)',
    $curve[0]['start'] === 1794092400 && $curve[0]['end'] === 1794093300 && $curve[0]['price'] === 31.42
    && $curve[0]['basis'] === 'endkunde' && $curve[0]['netzentgelt'] === 'enthalten' && array_key_exists('level', $curve[0]) && $curve[0]['level'] === null);
expect('Preiskurve: kein contractType/contractVersion auf oberster Ebene (Liste bleibt iterierbar)', array_keys($curve) === [0, 1]);
expect('Preiskurve: updatedAt (Unix, Abrufzeitpunkt) je Slot, gleicher Wert', count(array_filter($curve, fn($s) => is_int($s['updatedAt'] ?? null) && abs($s['updatedAt'] - time()) < 5)) === 2);
$S['attrs']['PriceCache'] = json_encode(['fetchedAt' => 0, 'slots' => [['start' => 1, 'end' => 2, 'price' => 1.0, 'basis' => 'endkunde', 'netzentgelt' => 'enthalten', 'level' => null]]]);
$S['attrs']['PriceLastTry'] = time(); // kein Nachladen anstoßen
$noStamp = $m->GetPriceCurve();
expect('Preiskurve: ohne Abrufzeitpunkt bleibt das optionale updatedAt weg (kein erfundener Wert)', !array_key_exists('updatedAt', $noStamp[0] ?? []));

// Demo-Override
$S['props']['DemoOverrideEnabled'] = true;
$S['attrs']['DemoPriceCurve'] = json_encode([['start' => 1, 'end' => 2, 'price' => 10.0]]);
$demo = $m->GetPriceCurve();
expect('Preiskurve (Demo-Override): contractType = price, Version gesetzt', ($demo[0]['contractType'] ?? '') === 'price' && ($demo[0]['contractVersion'] ?? '') === '1.2');
$S['props']['DemoOverrideEnabled'] = false;

// Leere Liste ohne Zugang
$S['attrs']['PriceApiTokenSecret'] = '';
expect('Preiskurve ohne Zugang: leere Liste, kein Fehler', $m->GetPriceCurve() === []);

// ---- GetTariffConfig ----
$t = $m->GetTariffConfig();
expect('Tarifkonfiguration: contractType = tariff oben, Version 1.2', ($t['contractType'] ?? '') === 'tariff' && ($t['contractVersion'] ?? '') === '1.2');
expect('Tarifkonfiguration: Felder unverändert vorhanden', isset($t['campaigns'], $t['netzGrundpreisDay'], $t['vat']));

// ---- GetFlexDevices / GetActiveControls ----
$status = ['flexDevices' => [
    ['__typename' => 'GridRewardVehicle', 'vehicleId' => 'v-1', 'shortName' => 'Auto', 'make' => 'Tesla', 'isPluggedIn' => true, 'isSmartChargingEnabled' => true,
        'state' => ['__typename' => 'GridRewardDelivering', 'reason' => 'excess']],
    ['__typename' => 'GridRewardBattery', 'batteryId' => 'b-1', 'shortName' => 'Speicher', 'make' => 'X', 'isSmartModeEnabled' => false,
        'state' => ['__typename' => 'GridRewardAvailable', 'kind' => 'x']],
]];
$S['attrs']['LastRealStatus'] = json_encode($status);
$flex = $m->GetFlexDevices();
expect('Flex-Geräte: zwei Einträge, contractType = flex in jedem, Version 1.1', count($flex) === 2
    && count(array_filter($flex, fn($e) => ($e['contractType'] ?? '') === 'flex' && ($e['contractVersion'] ?? '') === '1.1')) === 2);
expect('Flex-Geräte: Feld type (vehicle|battery) bleibt daneben unverändert', $flex[0]['type'] === 'vehicle' && $flex[1]['type'] === 'battery' && $flex[0]['deviceId'] === 'v-1');
$act = $m->GetActiveControls();
expect('Aktive Steuerung: nur das gerade liefernde Gerät, contractType = flex, Version 2.1 (Major 2 unverändert)', count($act) === 1
    && ($act[0]['contractType'] ?? '') === 'flex' && ($act[0]['contractVersion'] ?? '') === '2.1' && major($act[0]['contractVersion']) === '2', json_encode($act));
expect('Aktive Steuerung: Felder unverändert (managedBy, deviceId als string)', ($act[0]['managedBy'] ?? '') === 'tibber' && $act[0]['deviceId'] === 'v-1' && $act[0]['type'] === 'vehicle');

// ---- reine Funktionen tragen KEINEN contractType ----
$d = $m->DecomposePrice(30.0, 1794092400);
expect('DecomposePrice: bleibt ohne contractType (reine Funktion), Version 1.0', !array_key_exists('contractType', $d) && ($d['contractVersion'] ?? '') === '1.0');
$v = $m->SetVehicleSetting('x', 'online.vehicle.smartCharging.isEnabled', 'true');
expect('SetVehicleSetting: bleibt ohne contractType, Version 1.0', !array_key_exists('contractType', $v) && ($v['contractVersion'] ?? '') === '1.0');

echo "\n" . ($fails === 0 ? 'ALLE PRÜFUNGEN BESTANDEN' : "$fails FEHLER") . "\n";
exit($fails === 0 ? 0 : 1);
