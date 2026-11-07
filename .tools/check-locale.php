<?php

declare(strict_types=1);

/**
 * Prüfstand Zweisprachigkeit (SUITE.md Punkt 9, Store-Checkliste 25-29).
 *
 * Je Modul (TibberGridReward, TibberGridRewardTile) wird geprüft:
 *   1. Jeder Text in form.json (caption, Auswahlwerte) und jeder Translate('…')-Aufruf sowie jede
 *      'caption' => '…' in module.php hat einen Eintrag in locale.json (Englisch -> Deutsch).
 *   2. Die Quelltexte sind Englisch: kein Umlaut, keine deutschen Stoppwörter.
 *   3. Jeder Eintrag in locale.json wird tatsächlich gebraucht (sonst veraltet - nur Warnung).
 *   4. Platzhalter (%s, %d) stimmen in Quelle und Übersetzung überein.
 *   5. Die Übersetzung enthält kein "IP-Symcon" (Symcon-Namensregel).
 *
 * Aufruf: php .tools/check-locale.php   (Rückgabewert 0 = sauber, 1 = Fehler)
 */

$root = dirname(__DIR__);
$modules = ['TibberGridReward', 'TibberGridRewardTile'];
$germanWords = '/[äöüÄÖÜß]|\b(und|oder|nicht|keine?n?|der|die|das|dem|den|ein|eine|wird|werden|wurde|ist|sind|mit|für|von|zum|zur|nach|bei|auf|aus|noch|wenn|dann|über|gibt|bitte|erst|wähle|gewählt)\b/u';
$neutral = ['Arial', 'Verdana', 'Tahoma', 'Trebuchet MS'];
$errors = 0;
$warnings = 0;

/** Entfernt die Escapes eines PHP-Zeichenketten-Literals in einfachen Anführungszeichen. */
function unescapeSingle(string $s): string
{
    return str_replace(["\\'", '\\\\'], ["'", '\\'], $s);
}

/** Alle Zeichenketten aus form.json, die der Benutzer sieht (caption, Auswahl-Beschriftungen, Status). */
function formTexts($node, array &$out): void
{
    if (!is_array($node)) {
        return;
    }
    foreach ($node as $k => $v) {
        if (($k === 'caption' || $k === 'label') && is_string($v) && trim($v) !== '') {
            $out[$v] = true;
        } elseif (is_array($v)) {
            formTexts($v, $out);
        }
    }
}

foreach ($modules as $m) {
    echo "== $m\n";
    $locale = json_decode((string) file_get_contents("$root/$m/locale.json"), true);
    $de = $locale['translations']['de'] ?? null;
    if (!is_array($de)) {
        echo "  FEHLER: locale.json ohne translations.de\n";
        $errors++;
        continue;
    }
    $needed = [];   // Text => Herkunft

    $form = json_decode((string) file_get_contents("$root/$m/form.json"), true);
    $ft = [];
    formTexts($form, $ft);
    foreach (array_keys($ft) as $t) {
        // sprachneutral: Versionszeile und Schriftnamen brauchen keine Übersetzung
        if (preg_match('/^Version [\d.]+$/', $t) || in_array($t, $neutral, true)) {
            continue;
        }
        $needed[$t] = 'form.json';
    }

    $src = (string) file_get_contents("$root/$m/module.php");
    $tokens = token_get_all($src);
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING || $t[1][0] !== "'") {
            continue;
        }
        $text = unescapeSingle(substr($t[1], 1, -1));
        // vorheriges nicht-leeres Token: "(" nach Translate  ODER  "=>" nach 'caption'
        $j = $i - 1;
        while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j--;
        }
        $prev = $tokens[$j] ?? null;
        $isTranslate = false;
        if ($prev === '(') {
            $k = $j - 1;
            while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                $k--;
            }
            $isTranslate = is_array($tokens[$k] ?? null) && $tokens[$k][0] === T_STRING && $tokens[$k][1] === 'Translate';
        }
        $isCaption = false;
        if (is_array($prev) && $prev[0] === T_DOUBLE_ARROW) {
            $k = $j - 1;
            while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                $k--;
            }
            $isCaption = is_array($tokens[$k] ?? null) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING
                && in_array($tokens[$k][1], ["'caption'", "'label'"], true);
        }
        if ($isTranslate || $isCaption) {
            if (trim($text) !== '' && preg_match('/\p{L}{2}/u', $text)) {
                $needed[$text] = "module.php:{$t[2]}";
            }
        }
    }

    // 1. + 2. + 4.
    foreach ($needed as $text => $where) {
        if (!array_key_exists($text, $de)) {
            echo "  FEHLT in locale.json ($where): " . mb_substr($text, 0, 100) . "\n";
            $errors++;
        }
        // Eigennamen (Netzbetreiber) sind kein Deutsch im Sinne der Prüfung
        if (preg_match($germanWords, str_replace('Überlandwerk', 'Uberlandwerk', $text))) {
            echo "  QUELLE NICHT ENGLISCH? ($where): " . mb_substr($text, 0, 100) . "\n";
            $errors++;
        }
    }
    foreach ($de as $en => $tr) {
        preg_match_all('/%(?:\d+\$)?[sd]/', $en, $a);
        preg_match_all('/%(?:\d+\$)?[sd]/', $tr, $b);
        sort($a[0]);
        sort($b[0]);
        if ($a[0] !== $b[0]) {
            echo "  PLATZHALTER verschieden: " . mb_substr($en, 0, 80) . "\n";
            $errors++;
        }
        if (stripos($tr, 'IP-Symcon') !== false || stripos($en, 'IP-Symcon') !== false) {
            echo "  'IP-Symcon' statt 'Symcon': " . mb_substr($en, 0, 80) . "\n";
            $errors++;
        }
        if ($tr === '') {
            echo "  LEERE Übersetzung: " . mb_substr($en, 0, 80) . "\n";
            $errors++;
        }
        // 3.
        if (!isset($needed[$en])) {
            echo "  ungenutzt (Warnung): " . mb_substr($en, 0, 80) . "\n";
            $warnings++;
        }
    }
    echo '  ' . count($needed) . ' Texte im Code, ' . count($de) . " Einträge in locale.json\n";
}

echo ($errors === 0 ? "OK" : "FEHLER: $errors") . ", $warnings Warnung(en)\n";
exit($errors === 0 ? 0 : 1);
