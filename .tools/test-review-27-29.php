<?php
declare(strict_types=1);

// Prüfstand Store-Punkte 27 + 29 (SUITE.md):
//  27: Kachel gleicht die Datenquelle nicht in ApplyChanges ab, sondern verzögert (Timer SourceSync)
//      bzw. beim Kernelstart und wiederholt, bis die Quelle da ist.
//  29/19: Das Datenmodul schaltet die Archivierung nie von selbst ein, nur auf den Knopf.
// Lädt das ECHTE module.php beider Module mit gestubbten IPS-Funktionen.
// Aufruf: php .tools/test-review-27-29.php (0 = alles bestanden)

const KR_READY = 10103;
const KR_INIT = 10100;
const IPS_KERNELMESSAGE = 10100;
const VM_UPDATE = 10603;
const KL_MESSAGE = 10201;
const KL_WARNING = 10204;
const IS_EBASE = 200;
const VARIABLETYPE_FLOAT = 2;
const VARIABLETYPE_STRING = 3;

$S = ['props' => [], 'attrs' => [], 'timers' => [], 'buffers' => [], 'msgs' => [], 'status' => null, 'runlevel' => KR_READY,
    'instances' => [], 'list' => [], 'probes' => 0, 'ac' => ['logging' => false, 'set' => 0, 'agg' => 0, 'apply' => 0, 'hasArchive' => true],
    'vars' => [], 'updates' => 0, 'fieldUpdates' => []];

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
    public function Translate($t) { return $t; }
    public function SendDebug($a, $b, $c) {}
    public function SetTimerInterval($n, $ms) { global $S; $S['timers'][$n] = $ms; }
    public function GetTimerInterval($n) { global $S; return $S['timers'][$n] ?? 0; }
    public function SetBuffer($n, $v) { global $S; $S['buffers'][$n] = $v; }
    public function GetBuffer($n) { global $S; return $S['buffers'][$n] ?? ''; }
    public function SetStatus($c) { global $S; $S['status'] = $c; }
    public function SetVisualizationType($t) {}
    public function UpdateVisualizationValue($v) { global $S; $S['updates']++; }
    public function RegisterMessage($id, $m) { global $S; $S['msgs'][$id][$m] = true; }
    public function UnregisterMessage($id, $m) { global $S; unset($S['msgs'][$id][$m]); }
    public function GetMessageList() { global $S; return array_map('array_keys', $S['msgs']); }
    public function RegisterReference($id) {}
    public function UpdateFormField($n, $k, $v) { global $S; $S['fieldUpdates'][$n][$k] = $v; }
    public function MaintainVariable($a, $b, $c, $d, $e, $f) {}
    public function LogMessage($m, $k) {}
    public function ApplyChanges() {}
}
function IPS_GetKernelRunlevel() { global $S; return $S['runlevel']; }
function IPS_InstanceExists($id) { global $S; return $id === 99 || in_array($id, $S['instances'], true); }
function IPS_GetInstanceListByModuleID($g) {
    global $S;
    $S['probes']++;
    return $g === '{43192F0B-135B-4CE7-A0A7-1475603F3060}' ? ($S['ac']['hasArchive'] ? [777] : []) : $S['list'];
}
function IPS_GetObjectIDByIdent($ident, $id) { global $S; return $S['vars'][$id][$ident] ?? false; }
function IPS_GetName($id) { return 'x'; }
function GetValue($v) { return 0; }
function GetValueFormatted($v) { return '0'; }
function IPS_GetInstance($id) { return ['InstanceStatus' => 102, 'ConnectionID' => 0]; }
function AC_GetLoggingStatus($a, $v) { global $S; return $S['ac']['logging']; }
function AC_SetLoggingStatus($a, $v, $on) { global $S; $S['ac']['logging'] = $on; $S['ac']['set']++; }
function AC_SetAggregationType($a, $v, $t) { global $S; $S['ac']['agg']++; }
function IPS_ApplyChanges($id) { global $S; $S['ac']['apply']++; }

require __DIR__ . '/../TibberGridRewardTile/module.php';
require __DIR__ . '/../TibberGridReward/module.php';

$fails = 0;
function expect(string $label, bool $ok, string $detail = ''): void {
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . " $label" . (($detail !== '' && !$ok) ? "\n        -> $detail" : '') . "\n";
    if (!$ok) { $fails++; }
}
function fresh(): void {
    global $S;
    $S['props'] = []; $S['attrs'] = []; $S['timers'] = []; $S['buffers'] = []; $S['msgs'] = []; $S['status'] = null;
    $S['runlevel'] = KR_READY; $S['instances'] = []; $S['list'] = []; $S['probes'] = 0; $S['vars'] = []; $S['updates'] = 0;
    $S['ac'] = ['logging' => false, 'set' => 0, 'agg' => 0, 'apply' => 0, 'hasArchive' => true]; $S['fieldUpdates'] = [];
}
$tile = (new ReflectionClass('TibberGridRewardTile'))->newInstanceWithoutConstructor();
$data = (new ReflectionClass('TibberGridReward'))->newInstanceWithoutConstructor();

// ---------------- Punkt 27: Kachel ----------------
fresh();
$tile->ApplyChanges();
expect('27: ApplyChanges fasst keine andere Instanz an (keine Instanzsuche)', $S['probes'] === 0, 'Suchen: ' . $S['probes']);
expect('27: ApplyChanges plant den Abgleich über den Timer (1 s)', ($S['timers']['SourceSync'] ?? 0) === 1000);
expect('27: ApplyChanges setzt selbst keinen Status und registriert nichts', $S['status'] === null && $S['msgs'] === []);

// Quelle ist beim ersten Lauf noch nicht geladen (Neuladen von Modulen) -> Wiederholung
$tile->SourceSync();
expect('27: Quelle fehlt (noch) -> Status 104, Wiederholung in 10 s', $S['status'] === 104 && ($S['timers']['SourceSync'] ?? 0) === 10000);
// Quelle erscheint
$S['list'] = [500]; $S['instances'] = [500]; $S['vars'][500] = ['Delivering' => 601, 'State' => 602, 'GridRewardMode' => 603];
$tile->SourceSync();
expect('27: Quelle da -> Status 102, auf drei Variablen gelauscht, Takt aus', $S['status'] === 102
    && ($S['msgs'][601][VM_UPDATE] ?? false) && ($S['msgs'][602][VM_UPDATE] ?? false) && ($S['msgs'][603][VM_UPDATE] ?? false)
    && ($S['timers']['SourceSync'] ?? 1) === 0, json_encode([$S['status'], $S['timers']]));
$n = count($S['msgs']);
$tile->SourceSync();
expect('27: wiederholter Abgleich doppelt nichts (idempotent)', count($S['msgs']) === $n);

// Wiederholungen sind begrenzt
fresh();
$tile->ApplyChanges();
for ($i = 0; $i < 12; $i++) { $tile->SourceSync(); }
expect('27: höchstens 12 Wiederholungen, dann Takt aus', ($S['timers']['SourceSync'] ?? 1) === 10000);
$tile->SourceSync();
expect('27: … 13. Lauf beendet den Takt', ($S['timers']['SourceSync'] ?? 1) === 0);

// Kernelstart
fresh();
$S['runlevel'] = KR_INIT;
$tile->ApplyChanges();
expect('27: Kernel noch nicht bereit -> kein Timer, kein Zugriff auf andere Instanzen', ($S['timers']['SourceSync'] ?? 0) === 0 && $S['probes'] === 0);
$S['runlevel'] = KR_READY; $S['list'] = [500]; $S['instances'] = [500]; $S['vars'][500] = ['State' => 602];
$tile->MessageSink(0, 0, IPS_KERNELMESSAGE, [KR_READY]);
expect('27: KR_READY -> Abgleich läuft, Quelle abonniert', $S['status'] === 102 && ($S['msgs'][602][VM_UPDATE] ?? false));
$S['status'] = null;
$tile->MessageSink(0, 0, IPS_KERNELMESSAGE, [KR_INIT]);
expect('27: Gegenprobe: andere Kernelnachricht löst nichts aus', $S['status'] === null);

// ---------------- Punkt 29: Datenmodul ----------------
fresh();
$rm = new ReflectionMethod($data, 'ApplyPriceChanges');
$rm->invoke($data);
expect('29: ApplyChanges-Preisteil schaltet die Archivierung NIE von selbst ein', $S['ac']['set'] === 0 && $S['ac']['logging'] === false);
$S['props']['ArchivePrice'] = true;
$rm->invoke($data);
expect('29: … auch nicht mit dem alten Häkchen ArchivePrice=true', $S['ac']['set'] === 0);

$S['vars'][99] = ['CurrentPrice' => 888];
$msg = $data->EnableArchiving();
expect('29: Knopf schaltet die Archivierung ein (Aggregation 0, Archiv angewendet)', $S['ac']['logging'] === true && $S['ac']['set'] === 1 && $S['ac']['agg'] === 1 && $S['ac']['apply'] === 1, $msg);
expect('29: … Rückmeldung sichtbar (Text + Statuszeile im Formular)', str_contains($msg, 'enabled') && isset($S['fieldUpdates']['ArchiveStatus']['caption']));
$msg2 = $data->EnableArchiving();
expect('29: zweiter Klick ändert nichts ("bereits aktiv")', $S['ac']['set'] === 1 && str_contains($msg2, 'already'), $msg2);

fresh();
$msg = $data->EnableArchiving();
expect('29: ohne Preisvariable nur Hinweis, nichts geschaltet', $S['ac']['set'] === 0 && str_contains($msg, 'does not exist'), $msg);
fresh(); $S['vars'][99] = ['CurrentPrice' => 888]; $S['ac']['hasArchive'] = false;
$msg = $data->EnableArchiving();
expect('29: ohne Archive Control nur Hinweis, nichts geschaltet', $S['ac']['set'] === 0 && str_contains($msg, 'No archive control'), $msg);

echo "\n" . ($fails === 0 ? 'ALLE PRÜFUNGEN BESTANDEN' : "$fails FEHLER") . "\n";
exit($fails === 0 ? 0 : 1);
