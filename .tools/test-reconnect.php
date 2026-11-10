<?php
declare(strict_types=1);

// Prüfstand "Neuverbinden im Fehlerstatus" (SUITE.md Stolperstein 27) für TibberGridReward.
// Lädt das ECHTE module.php mit gestubbten IPS-Funktionen; der WebSocket-Client ist ein Stub mit
// einstellbarem Status. Aufruf: php .tools/test-reconnect.php (0 = alles bestanden)

const IS_ACTIVE = 102;
const IS_INACTIVE = 104;
const IS_EBASE = 200;
const KR_READY = 10103;
const IM_CHANGESTATUS = 10505;
const KL_MESSAGE = 10201;
const KL_WARNING = 10204;
const IPS_KERNELMESSAGE = 10100;
const VM_UPDATE = 10603;

$S = ['props' => [], 'attrs' => [], 'timers' => [], 'log' => [], 'scripts' => [], 'parent' => 9001, 'parentStatus' => 102, 'runlevel' => KR_READY];

class IPSModule {
    public $InstanceID = 99;
    public function __construct($id = 99) {}
    public function ReadPropertyBoolean($n) { global $S; return $S['props'][$n] ?? false; }
    public function ReadPropertyString($n) { global $S; return $S['props'][$n] ?? ''; }
    public function ReadAttributeString($n) { global $S; return $S['attrs'][$n] ?? ''; }
    public function ReadAttributeInteger($n) { global $S; return $S['attrs'][$n] ?? 0; }
    public function WriteAttributeInteger($n, $v) { global $S; $S['attrs'][$n] = $v; }
    public function Translate($t) { return $t; }
    public function SendDebug($a, $b, $c) {}
    public function LogMessage($m, $k) { global $S; $S['log'][] = [$k, $m]; }
    public function SetTimerInterval($n, $ms) { global $S; $S['timers'][$n] = $ms; }
    public function GetTimerInterval($n) { global $S; return $S['timers'][$n] ?? 0; }
    public function SetStatus($c) {}
    public function SendDataToParent($d) {}
}
function IPS_GetInstance($id) { global $S; return $id === 99 ? ['ConnectionID' => $S['parent']] : ['InstanceStatus' => $S['parentStatus']]; }
function IPS_InstanceExists($id) { return true; }
function IPS_GetKernelRunlevel() { global $S; return $S['runlevel']; }
function IPS_RunScriptText($t) { global $S; $S['scripts'][] = $t; }

require __DIR__ . '/../TibberGridReward/module.php';

$m = (new ReflectionClass('TibberGridReward'))->newInstanceWithoutConstructor();
$fails = 0;
function expect(string $label, bool $ok, string $detail = ''): void {
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . " $label" . (($detail !== '' && !$ok) ? "\n        -> $detail" : '') . "\n";
    if (!$ok) { $fails++; }
}
function sink(int $status): void { global $m, $S; $m->MessageSink(0, $S['parent'], IM_CHANGESTATUS, [$status]); }
function logCount(int $kind): int { global $S; return count(array_filter($S['log'], fn($l) => $l[0] === $kind)); }
function reset_(): void { global $S; $S['attrs'] = ['JWT_Exp' => time() + 3600, 'JWT' => 'x']; $S['timers'] = []; $S['log'] = []; $S['scripts'] = []; $S['parentStatus'] = 102; $S['props'] = ['Active' => true]; }

// 1) Abbruch -> Neuversuch in 5 s, einmal protokolliert
reset_();
$S['parentStatus'] = 200; sink(200);
expect('Fehlerstatus 200 -> Neuversuch in 5 s geplant', ($S['timers']['WsReconnect'] ?? 0) === 5000, json_encode($S['timers']));
expect('… genau eine Warnung', logCount(KL_WARNING) === 1);

// 2) wachsender Abstand 10/20, keine weitere Meldung
sink(200);
expect('2. Fehler -> 10 s', ($S['timers']['WsReconnect'] ?? 0) === 10000);
sink(200);
expect('3. Fehler -> 20 s', ($S['timers']['WsReconnect'] ?? 0) === 20000);
expect('weitere Versuche ohne Meldung', logCount(KL_WARNING) === 1);
for ($i = 0; $i < 12; $i++) { sink(200); }
expect('höchstens 5 min', ($S['timers']['WsReconnect'] ?? 0) === 300000);
expect('Zähler zählt mit', ($S['attrs']['WsFehler'] ?? 0) === 15);

// 3) Takt: Client im Fehlerstatus -> aus, dann Konfiguration mit Active=true; Rückfalltakt
$S['scripts'] = [];
$m->WsReconnect();
$sc = implode("\n", $S['scripts']);
expect('Takt schaltet den Client im Fehlerstatus aus', str_contains($sc, 'IPS_SetProperty(9001, "Active", false)'), $sc);
expect('… und mit frischer Konfiguration (Active=true, Zugangsschlüssel) wieder an', str_contains($sc, 'IPS_SetConfiguration(9001,') && str_contains($sc, '"Active":true') && str_contains($sc, 'Bearer x'), $sc);
expect('… Rückfalltakt bleibt gesetzt', ($S['timers']['WsReconnect'] ?? 0) === 300000);

// 4) Wieder verbunden -> Zähler zurück, einmal "wiederhergestellt", Takt aus, Wächter armiert
$S['parentStatus'] = 102;
sink(IS_ACTIVE);
expect('verbunden -> Takt aus, Zähler zurück', ($S['timers']['WsReconnect'] ?? 1) === 0 && ($S['attrs']['WsFehler'] ?? 1) === 0);
expect('… Wiederherstellung einmal gemeldet (nach 15 Versuchen)', logCount(KL_MESSAGE) === 1 && str_contains(end($S['log'])[1], 'restored after 15'), json_encode($S['log']));
$S['scripts'] = [];
$m->WsReconnect();
expect('Takt bei gesundem Client tut nichts', $S['scripts'] === [] && ($S['timers']['WsReconnect'] ?? 1) === 0);
expect('Wächter nach Verbindungsaufbau scharf (auch ohne "next")', ($S['timers']['StartWatchdog'] ?? 0) === 300000, json_encode($S['timers']));

// 5) Start im Fehlerstatus: ApplyChanges plant selbst; KR_READY ebenso
reset_();
$S['parentStatus'] = 200;
$r = new ReflectionMethod($m, 'ParentInError');
expect('ParentInError erkennt Fehlerstatus', $r->invoke($m) === true);
$m->MessageSink(0, 0, KR_READY, []);
expect('KR_READY im Fehlerstatus -> Versuch geplant', ($S['timers']['WsReconnect'] ?? 0) === 5000);

// 6) Inaktiv/aus: nichts planen
reset_();
$S['props']['Active'] = false; $S['parentStatus'] = 200; sink(200);
expect('Modul auf Inaktiv -> kein Neuversuch', ($S['timers']['WsReconnect'] ?? 0) === 0 && logCount(KL_WARNING) === 0);
reset_();
$S['parentStatus'] = 104; sink(IS_INACTIVE);
expect('Parent inaktiv (104) -> Takt und Zähler zurückgesetzt', ($S['timers']['WsReconnect'] ?? 1) === 0 && ($S['attrs']['WsFehler'] ?? 1) === 0);

// 7) Wächter: gescheitertes Neuverbinden aus der Relogin-Sequenz setzt ihn wieder scharf; kein Zähler-Abbruch mehr
reset_();
for ($i = 0; $i < 12; $i++) {
    $S['timers']['ReloginSequence'] = 0;
    $m->ReloginSequence();                       // 1. Aufruf: Wartezeit
    $wait = $S['timers']['ReloginSequence'];
    $m->ReloginSequence();                       // 2. Aufruf: neu verbinden
}
expect('Relogin-Sequenz: Wartezeit 60-120 s', $wait >= 60000 && $wait <= 120000, (string) $wait);
expect('Relogin-Sequenz: Wächter nach dem Neuverbinden scharf (kein Aufgeben nach n Runden)', ($S['timers']['StartWatchdog'] ?? 0) === 300000 && ($S['timers']['ReloginSequence'] ?? 1) === 0, json_encode($S['timers']));

// 8) Gegenprobe: kein Neuversuch bei 'normalen' Statuswechseln
reset_();
$S['parentStatus'] = 102; sink(102);
expect('Gegenprobe: IS_ACTIVE allein löst keine Warnung aus', logCount(KL_WARNING) === 0 && ($S['timers']['WsReconnect'] ?? 0) === 0);

echo "\n" . ($fails === 0 ? 'ALLE PRÜFUNGEN BESTANDEN' : "$fails FEHLER") . "\n";
exit($fails === 0 ? 0 : 1);
