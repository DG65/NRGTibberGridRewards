<?php
declare(strict_types=1);

// Prüfstand "Formular: Aufbau" (SUITE.md 3a) für TibberGridReward und TibberGridRewardTile.
// Lädt das ECHTE module.php beider Module mit minimal gestubbten IPS-Funktionen und prüft das
// ausgelieferte Formular-JSON: Reihenfolge, Aufklapp-Zustand, visible:false, Rückfall bei
// unbekanntem Schlüssel, Reset. Aufruf: php .tools/test-formlayout.php (0 = alles bestanden)

$W = ['props' => [], 'attrs' => [], 'instances' => [], 'lookup' => [], 'pushed' => []];

class IPSModule {
    public $InstanceID = 99;
    public function __construct($id = 99) {}
    public function ReadPropertyInteger($n) { global $W; return $W['props'][$n] ?? 0; }
    public function ReadPropertyString($n) { global $W; return $W['props'][$n] ?? ''; }
    public function ReadPropertyFloat($n) { global $W; return $W['props'][$n] ?? 0.0; }
    public function ReadPropertyBoolean($n) { global $W; return $W['props'][$n] ?? false; }
    public function ReadAttributeBoolean($n) { global $W; return $W['attrs'][$n] ?? false; }
    public function ReadAttributeString($n) { global $W; return $W['attrs'][$n] ?? ''; }
    public function ReadAttributeInteger($n) { global $W; return $W['attrs'][$n] ?? 0; }
    public function Translate($t) { return $t; }
    public function SendDebug($a, $b, $c) {}
    public function UpdateFormField($n, $k, $v) { global $W; $W['pushed'][$n][$k] = $v; }
}
function IPS_GetInstanceListByModuleID($g) { return []; }
function IPS_InstanceExists($id) { return false; }
function IPS_GetObjectIDByIdent($ident, $id) { return false; }

require __DIR__ . '/../TibberGridRewardTile/module.php';
require __DIR__ . '/../TibberGridReward/module.php';

$fails = 0;
function expect(string $label, bool $ok, string $detail = ''): void {
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . " $label" . (($detail !== '' && !$ok) ? "\n        -> $detail" : '') . "\n";
    if (!$ok) { $fails++; }
}
function form(string $class, string $layoutJson): array {
    global $W;
    $W['props'] = ['FormLayout' => $layoutJson];
    $m = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $json = $m->GetConfigurationForm();
    return [json_decode($json, true), $json, $m];
}
/** Namen der Fachpanels auf oberster Ebene in Ausgabereihenfolge. */
function panelOrder(array $form, array $names): array {
    $o = [];
    foreach ($form['elements'] as $e) { if (in_array($e['name'] ?? '', $names, true)) { $o[] = $e['name']; } }
    return $o;
}
function panel(array $form, string $name): ?array {
    foreach ($form['elements'] as $e) { if (($e['name'] ?? '') === $name) { return $e; } }
    return null;
}
function topLevelCaptions(array $form): array {
    return array_map(fn($e) => (string) ($e['caption'] ?? ($e['name'] ?? $e['type'])), $form['elements']);
}
function row(array $rows, string $key): ?array {
    foreach ($rows as $r) { if ($r['Key'] === $key) { return $r; } }
    return null;
}
function layoutRows(array $form): array {
    foreach ($form['elements'] as $e) {
        if (str_starts_with((string) ($e['caption'] ?? ''), '🪟')) {
            foreach ($e['items'] as $i) { if (($i['name'] ?? '') === 'FormLayout') { return $i['values']; } }
        }
    }
    return [];
}

$modules = [
    ['TibberGridReward', ['PanelPrice', 'PanelTariff', 'PanelWallbox', 'PanelRules', 'PanelSim', 'PanelDemo'], 'TIBBERGR'],
    ['TibberGridRewardTile', ['PanelColors', 'PanelSurfaces', 'PanelSim', 'PanelRules', 'PanelFont'], 'TGRTILE'],
];

foreach ($modules as [$class, $default, $prefix]) {
    echo "== $class ==\n";
    $a = $default[0]; $b = $default[1]; $c = $default[2]; $last = $default[count($default) - 1];

    // 1) Standard: Reihenfolge wie in form.json, nichts versteckt
    [$f, $json] = form($class, '[]');
    expect('Standard: Reihenfolge wie in form.json', panelOrder($f, $default) === $default, implode(',', panelOrder($f, $default)));
    $allVisible = true;
    foreach ($default as $n) { if ((panel($f, $n)['visible'] ?? true) === false) { $allVisible = false; } }
    expect('Standard: kein Fachpanel ausgeblendet', $allVisible);
    $caps = topLevelCaptions($f);
    $layoutIdx = null; $forumIdx = null; $lastFachIdx = -1;
    foreach ($caps as $i => $cap) {
        if (str_starts_with($cap, '🪟')) { $layoutIdx = $i; }
        if (str_starts_with($cap, '💬')) { $forumIdx = $i; }
    }
    foreach ($f['elements'] as $i => $e) { if (in_array($e['name'] ?? '', $default, true)) { $lastFachIdx = $i; } }
    expect('Aufbau-Panel nach den Fachpanels und vor dem Forum-Hinweis', $layoutIdx !== null && $layoutIdx > $lastFachIdx && $forumIdx !== null && $layoutIdx < $forumIdx, json_encode([$layoutIdx, $lastFachIdx, $forumIdx]));
    $layoutPanel = $f['elements'][$layoutIdx];
    expect('Aufbau-Panel standardmäßig eingeklappt', ($layoutPanel['expanded'] ?? null) === false);
    $docIdx = null; $firstFachIdx = null;
    foreach ($f['elements'] as $i => $e) {
        if (str_starts_with((string) ($e['caption'] ?? ''), '📖')) { $docIdx = $i; }
        if ($firstFachIdx === null && in_array($e['name'] ?? '', $default, true)) { $firstFachIdx = $i; }
    }
    expect('Doku-Panel vorhanden, steht vor den Fachpanels und ist nicht wählbar', $docIdx !== null && $docIdx < $firstFachIdx
        && !in_array($f['elements'][$docIdx]['name'] ?? '', $default, true) && !in_array('📖', array_map(fn($r) => mb_substr($r['Panel'], 0, 1), layoutRows($f)), true));
    $rows = layoutRows($f);
    expect('Liste enthält alle Fachpanels, Doku nicht', count($rows) === count($default) && array_column($rows, 'Key') === $default, json_encode(array_column($rows, 'Key')));
    expect('Schlüsselspalte unsichtbar und gespeichert', (function ($layoutPanel) {
        foreach ($layoutPanel['items'] as $i) { if (($i['name'] ?? '') === 'FormLayout') { foreach ($i['columns'] as $col) { if ($col['name'] === 'Key') { return ($col['save'] ?? false) === true && ($col['visible'] ?? true) === false; } } } }
        return false;
    })($layoutPanel));

    // 2) Position: letztes Panel nach vorn (Pos 1), erstes bekommt 50
    $lay = json_encode([['Key' => $last, 'Pos' => 1], ['Key' => $a, 'Pos' => 50]]);
    [$f] = form($class, $lay);
    $order = panelOrder($f, $default);
    expect('Position: ' . $last . ' (Pos 1) steht vor allen anderen', $order[0] === $last, implode(',', $order));
    expect('Position: ' . $a . ' (Pos 50) steht ganz hinten', $order[count($order) - 1] === $a, implode(',', $order));
    expect('Position: alle Panels weiterhin genau einmal vorhanden', count($order) === count($default) && count(array_unique($order)) === count($default));

    // 3) Aufklapp-Zustand
    $lay = json_encode([['Key' => $a, 'Opened' => true], ['Key' => $b, 'Closed' => true]]);
    [$f] = form($class, $lay);
    expect('Aufgeklappt: expanded=true', (panel($f, $a)['expanded'] ?? null) === true);
    expect('Zugeklappt: expanded=false', (panel($f, $b)['expanded'] ?? null) === false);
    expect('Standard (kein Haken): expanded unverändert', array_key_exists('expanded', panel($f, $c) ?? []) === array_key_exists('expanded', (function () use ($class, $c) { [$g] = form($class, '[]'); return panel($g, $c); })()));

    // 4) Ausgeblendet = visible:false, Panel bleibt im Formular samt Inhalt, Zahl in der Caption
    $lay = json_encode([['Key' => $b, 'Hidden' => true], ['Key' => $c, 'Hidden' => true]]);
    [$f, $json] = form($class, $lay);
    $pb = panel($f, $b);
    expect('Ausgeblendet: Panel vorhanden mit visible=false (nicht weggelassen)', $pb !== null && ($pb['visible'] ?? true) === false);
    expect('Ausgeblendet: Inhalt (items) bleibt erhalten', $pb !== null && !empty($pb['items']));
    $caps = topLevelCaptions($f);
    $hit = array_values(array_filter($caps, fn($x) => str_starts_with($x, '🪟')));
    expect('Caption nennt die Zahl ausgeblendeter Bereiche', count($hit) === 1 && str_contains($hit[0], '(2 ausgeblendet)'), json_encode($hit));
    $firstItem = $f['elements'][array_search($hit[0], $caps, true)]['items'][0]['caption'] ?? '';
    expect('Erste Zeile im Panel nennt die ausgeblendeten Bereiche', str_starts_with($firstItem, '🙈 Ausgeblendet:'), $firstItem);
    expect('Ausgeblendet gewinnt gegen Aufgeklappt/Zugeklappt', (function () use ($class, $a) {
        [$g] = form($class, json_encode([['Key' => $a, 'Opened' => true, 'Closed' => true, 'Hidden' => true]]));
        return (panel($g, $a)['visible'] ?? true) === false;
    })());
    expect('Zugeklappt gewinnt gegen Aufgeklappt', (function () use ($class, $a) {
        [$g] = form($class, json_encode([['Key' => $a, 'Opened' => true, 'Closed' => true]]));
        return (panel($g, $a)['expanded'] ?? null) === false;
    })());

    // 5) Rückfall: unbekannter Schlüssel, Müll im Property, Pos außerhalb des Bereichs
    [$f] = form($class, json_encode([['Key' => 'GibtEsNicht', 'Hidden' => true, 'Pos' => 1]]));
    expect('Unbekannter Schlüssel: ignoriert, Standard bleibt', panelOrder($f, $default) === $default);
    [$f] = form($class, 'das ist kein JSON');
    expect('Kaputtes JSON: Standard, kein Absturz', panelOrder($f, $default) === $default);
    [$f] = form($class, json_encode([['Key' => $a, 'Pos' => 500], ['Key' => $b, 'Pos' => -5]]));
    $rows = layoutRows($f);
    expect('Position wird auf 1..99 begrenzt', row($rows, $a)['Pos'] === 99 && row($rows, $b)['Pos'] === 1, json_encode([row($rows, $a)['Pos'], row($rows, $b)['Pos']]));

    // 6) Reihenfolge der Positions-Gleichstände: stabil über Ursprungsindex
    [$f] = form($class, json_encode([['Key' => $b, 'Pos' => 1], ['Key' => $a, 'Pos' => 1]]));
    $order = panelOrder($f, $default);
    expect('Gleiche Position: Standardreihenfolge entscheidet', array_search($a, $order, true) < array_search($b, $order, true), implode(',', $order));

    // 7) Reset: öffentliche Methode setzt die Liste per UpdateFormField, gibt Klartext zurück, schreibt keine Property
    $GLOBALS['W']['pushed'] = [];
    $m = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $text = $m->ResetFormLayout();
    $pushed = json_decode((string) ($GLOBALS['W']['pushed']['FormLayout']['values'] ?? ''), true);
    expect('Reset: Klartext mit Hinweis auf Übernehmen', str_contains($text, 'Übernehmen'), $text);
    expect('Reset: Liste enthält alle Fachpanels in Standardreihenfolge ohne Haken', is_array($pushed) && array_column($pushed, 'Key') === $default
        && !array_filter($pushed, fn($r) => $r['Opened'] || $r['Closed'] || $r['Hidden']) && array_column($pushed, 'Pos') === range(1, count($default)));
    expect('Reset: Knopf im Panel ruft ' . $prefix . '_ResetFormLayout', str_contains($json, $prefix . '_ResetFormLayout'));

    // 8) Neues Neu-Panel-Eintrag vorhanden
    [$f, $json] = form($class, '[]');
    $W['attrs'] = [];
    [$f, $json] = form($class, '[]');
    expect('Neu-Panel erwähnt „Formular-Aufbau“', str_contains(json_encode($f, JSON_UNESCAPED_UNICODE), 'Formular-Aufbau selbst wählen'));
    expect('Pflicht-Panels bleiben: Forum und Über dieses Modul unten', (function ($f) {
        $caps = topLevelCaptions($f); $n = count($caps);
        return str_starts_with($caps[$n - 2], '💬') && str_starts_with($caps[$n - 1], '🧡');
    })($f), implode(' | ', topLevelCaptions($f)));
}

// 9) Datenmodul: freistehende Felder zwischen den Fachpanels bleiben, wo sie sind (nur Panels tauschen Plätze)
[$f] = form('TibberGridReward', '[]');
$typesDefault = array_map(fn($e) => ($e['name'] ?? '') !== '' && str_starts_with((string) $e['name'], 'Panel') ? 'P' : ($e['type'] ?? '?'), $f['elements']);
[$f2] = form('TibberGridReward', json_encode([['Key' => 'PanelDemo', 'Pos' => 1]]));
$typesMoved = array_map(fn($e) => ($e['name'] ?? '') !== '' && str_starts_with((string) $e['name'], 'Panel') ? 'P' : ($e['type'] ?? '?'), $f2['elements']);
expect('Datenmodul: Felder zwischen den Panels bleiben an ihrem Platz (Struktur unverändert)', $typesDefault === $typesMoved, implode(',', $typesDefault) . ' / ' . implode(',', $typesMoved));

echo "\n" . ($fails === 0 ? 'ALLE PRÜFUNGEN BESTANDEN' : "$fails FEHLER") . "\n";
exit($fails === 0 ? 0 : 1);
