<?php

declare(strict_types=1);

/**
 * TibberGridRewardTile
 *
 * Eigenständige HTML-SDK-Kachel für die Tile-Visualisierung. Liest die Variablen einer
 * TibberGridReward-Instanz (Quelle) und stellt sie als randlose, frei gestaltbare Status-Kachel dar.
 *
 * Bewusst von der Datenlogik getrennt (Vorbild da8ter): Ein Problem in der Kachel kann die
 * WebSocket-/Datenverbindung der Quell-Instanz nicht beeinträchtigen.
 */
class TibberGridRewardTile extends IPSModule
{
    // GUID des Datenmoduls TibberGridReward (für die Quellen-Auswahl)
    private const SOURCE_MODULE = '{E92F62F4-88A6-4C6E-9F0D-E76C3B1C9A01}';

    // Standardwerte (auch für „Zurücksetzen")
    private const DEF_ACTIVE      = 0x27D07F; // Laden aus Netz (excess)
    private const DEF_CURTAIL     = 0xE8A13A; // Drosselung (shortage)
    private const DEF_AVAILABLE   = 0x2BB3C0;
    private const DEF_UNAVAILABLE = 0x7A8A99;
    // -1 = keine feste Farbe -> Kachel übernimmt das IPS-Theme (transparenter Hintergrund,
    //      Textfarbe automatisch hell/dunkel je nach Theme) – Verhalten wie bei da8ter.
    private const DEF_BACKGROUND  = -1;
    private const DEF_BOX         = -1;
    private const DEF_TEXT        = -1;
    private const DEF_TEXTMUTED   = -1;
    private const DEF_FONT        = 'system';
    private const DEF_SCALE       = 1.0;

    // Formular-Konvention (SUITE.md "Einheitliche Formular-Optik", EMS-Auftrag 14.09.2026) —
    // derselbe Forum-Thread wie das Datenmodul, kein eigener.
    private const FORUM_THREAD_URL = 'https://community.symcon.de/t/modul-tibber-grid-rewards-grid-reward-signal-wallbox-ems-aufbereitung-fuer-ip-symcon/143996';

    // Verbund-Konvention "Über dieses Modul" (SUITE.md Punkt 5) - main trägt seit 2.9.3 den
    // PolyForm-Text (Branch ems-integration entfällt, alle Betas sind Stable).
    private const LICENSE_URL = 'https://github.com/DG65/NRGTibberGridRewards/blob/main/LICENSE';
    private const PAYPAL_URL = 'https://paypal.me/DietmarGureth';

    // Neu-Panel der Kachel (erst seit 2.10.0: erste echte Neuerung an der Kachel selbst; 2.11.0: Zweisprachigkeit).
    private const NEWS_VERSION = '2.11.0';

    // Farbe der 🔗-Zeilen ("automatisch übernommen", SUITE.md "Wert kommt automatisch"); -1 = Standardfarbe.
    private const COLOR_AUTO = 0x2E8B3D;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Formular-Konvention - Referenzimplementierung MeterHub. Das Neu-Panel erscheint nur, wenn es an der
        // Kachel selbst etwas Echtes zu vermelden gibt (keine erfundenen Neuigkeiten), siehe NEWS_VERSION.
        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeBoolean('ForumHintGone', false);
        $this->RegisterAttributeString('SeenNews', '');

        $this->RegisterPropertyInteger('SourceInstance', 0);

        // Statusfarben
        $this->RegisterPropertyInteger('ColorActive', self::DEF_ACTIVE);
        $this->RegisterPropertyInteger('ColorCurtailment', self::DEF_CURTAIL);
        $this->RegisterPropertyInteger('ColorAvailable', self::DEF_AVAILABLE);
        $this->RegisterPropertyInteger('ColorUnavailable', self::DEF_UNAVAILABLE);
        // Flächen-/Textfarben
        $this->RegisterPropertyInteger('ColorBackground', self::DEF_BACKGROUND);
        $this->RegisterPropertyInteger('ColorBox', self::DEF_BOX);
        $this->RegisterPropertyInteger('ColorText', self::DEF_TEXT);
        $this->RegisterPropertyInteger('ColorTextMuted', self::DEF_TEXTMUTED);
        // Schrift
        $this->RegisterPropertyString('FontFamily', self::DEF_FONT);
        $this->RegisterPropertyFloat('FontScale', self::DEF_SCALE);

        // Simulations-Buttons auf der Kachel: standardmäßig AUS, da sie echte RequestAction-Befehle
        // an die konfigurierten EMS-Automationen der Quelle auslösen (siehe Datenmodul).
        $this->RegisterPropertyBoolean('ShowSimControls', false);
        // Regel-Editor (Wenn->Dann) auf der Kachel: standardmäßig AUS (abweichend vom Vorbild
        // StromGedachtTile, dort Standard AN) – hier steuert das Modul reale Aktoren (GoodWe-
        // Speicher/Wallbox), daher bewusst erst nach explizitem Einschalten sichtbar.
        $this->RegisterPropertyBoolean('ShowAutomations', false);
        // Formular: Aufbau (SUITE.md 3a): Liste {Key, Panel, Opened, Closed, Hidden, Pos}
        $this->RegisterPropertyString('FormLayout', '[]');

        // Als HTML-Kachel-Visualisierung anmelden
        $this->SetVisualizationType(1);

        // Abgleich mit der Datenquelle nicht in ApplyChanges, sondern verzögert/nach dem Start
        // (SUITE.md Punkt 27: beim Neuladen von Modulen können die anderen Instanzen noch fehlen).
        $this->RegisterTimer('SourceSync', 0, 'TGRTILE_SourceSync($_IPS[\'TARGET\']);');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    // ---------------------------------------------------------------------
    // Sichere Property-/Attribut-Leser (Fund: Dashboard-Sitzung, 13.09.2026)
    //
    // ReadPropertyXXX() liefert `false` statt des erwarteten Typs, wenn die Instanz gerade neu
    // geladen wird - reale Absturzkette live beobachtet: ColorHex() erhielt `false` als erstes
    // Argument (aus einem ungecasteten ReadPropertyInteger()-Aufruf) -> TypeError, da ColorHex()
    // strikt einen int verlangt.
    // Generischer Wrapper statt Einzelfix an jeder Farb-Konsumstelle, gleiches Muster wie im
    // Datenmodul (siehe dort für die ausführliche Begründung).
    private function ReadPropertyStringSafe(string $Name): string
    {
        return (string) $this->ReadPropertyString($Name);
    }

    private function ReadPropertyIntegerSafe(string $Name): int
    {
        return (int) $this->ReadPropertyInteger($Name);
    }

    private function ReadPropertyFloatSafe(string $Name): float
    {
        return (float) $this->ReadPropertyFloat($Name);
    }

    private function ReadPropertyBooleanSafe(string $Name): bool
    {
        return (bool) $this->ReadPropertyBoolean($Name);
    }

    // Erster Attribut-Lesezugriff in dieser Klasse (Formular-Konvention, 14.09.2026) - gleiches
    // Cast-Muster wie die Property-Safe-Wrapper oben, siehe deren Begründung.
    private function ReadAttributeBooleanSafe(string $Name): bool
    {
        return (bool) $this->ReadAttributeBoolean($Name);
    }

    private function ReadAttributeStringSafe(string $Name): string
    {
        return (string) $this->ReadAttributeString($Name);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        // Die Datenquelle (andere Instanz) wird hier bewusst NICHT angefasst (SUITE.md Punkt 27): beim
        // Neuladen von Modulen kann sie noch nicht geladen sein, die Kachel bliebe dann ohne
        // Abonnement stehen. Der Abgleich läuft kurz danach über den Timer SourceSync bzw. beim
        // Kernelstart (KR_READY) und wiederholt sich, bis die Quelle da ist.
        $this->SetBuffer('SourceTries', '0');
        $this->SetTimerInterval('SourceSync', IPS_GetKernelRunlevel() === KR_READY ? 1000 : 0);
    }

    /**
     * Abgleich mit der Datenquelle: Quelle bestimmen, auf deren Variablen lauschen, Status setzen und
     * die Kachel aktualisieren. Läuft über den Timer SourceSync (kurz nach ApplyChanges) und beim
     * Kernelstart; findet sich die Quelle (noch) nicht, wird bis zu 12-mal im Abstand von 10 s
     * wiederholt (Neuladen von Modulen), danach gilt „keine Quelle“, bis zum nächsten Übernehmen.
     */
    public function SourceSync(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !IPS_InstanceExists($this->InstanceID)) {
            return;
        }
        // Bisherige VM_UPDATE-Registrierungen lösen
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $msg) {
                if ($msg === VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        // Auf Änderungen der Quell-Variablen lauschen, damit die Kachel sich aktualisiert
        $src = $this->ResolveSource();
        if ($src > 0 && IPS_InstanceExists($src)) {
            $watch = ['Delivering', 'State', 'GridRewardMode', 'RewardCurrentMonth', 'RewardAllTime',
                'Currency', 'WallboxPowerTotal', 'GridRewardEnergyEvent', 'GridRewardEnergyToday',
                'GridRewardEnergyMonth', 'GridRewardEnergyTotal', 'FlexDevices'];
            foreach ($watch as $ident) {
                $vid = @IPS_GetObjectIDByIdent($ident, $src);
                if ($vid !== false && $vid > 0) {
                    $this->RegisterReference($vid);
                    $this->RegisterMessage($vid, VM_UPDATE);
                }
            }
            $this->SetStatus(102);
            $this->SetTimerInterval('SourceSync', 0);
        } else {
            $this->SetStatus(104);
            $tries = (int) $this->GetBuffer('SourceTries') + 1;
            $this->SetBuffer('SourceTries', (string) $tries);
            $this->SetTimerInterval('SourceSync', $tries <= 12 ? 10000 : 0);
        }

        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Zweite Sicherheitsebene zu den ReadPropertyXXXSafe()-Wrappern oben: die konkret
        // beobachtete Absturzursache (ColorHex() erhielt `false`) trat bei VM_UPDATE während
        // eines Modul-Reloads auf, als "InstanceInterface is not available" im Log stand.
        if (IPS_GetKernelRunlevel() !== KR_READY || !IPS_InstanceExists($this->InstanceID)) {
            return;
        }
        try {
            if ($Message === VM_UPDATE) {
                $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
            } elseif ($Message === IPS_KERNELMESSAGE && ($Data[0] ?? null) === KR_READY) {
                $this->SourceSync();
            }
        } catch (Throwable $e) {
            $this->SendDebug(__FUNCTION__, 'Ausnahme bei Message ' . $Message . ': ' . $e->getMessage(), 0);
        }
    }

    /**
     * Formular-Konvention (SUITE.md "Einheitliche Formular-Optik", EMS-Auftrag 14.09.2026,
     * Referenzimplementierung MeterHub) - steht ganz vorn, einmalig dismissible.
     */
    private function PurposeIntro(): ?array
    {
        if ($this->ReadAttributeBooleanSafe('PurposeIntroGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'PurposeIntroPanel', 'expanded' => true,
            'caption' => '👋  What is this module for?',
            'items' => [
                ['type' => 'Label', 'caption' => 'This tile shows the Grid Rewards status of a TibberGridReward instance as a standalone, freely designable status tile in the WebFront — event, earned reward, wallbox power and energy statistics at a glance.'],
                ['type' => 'Label', 'caption' => 'Choose your data source (the TibberGridReward instance) below — the colors/font can be adapted freely underneath. Optional: a rule editor and simulation buttons for testing without a real event.'],
                ['type' => 'Button', 'caption' => 'Understood – do not show again', 'onClick' => 'TGRTILE_AckPurposeIntro($id);'],
            ],
        ];
    }

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    /** Aufgeklappt und pro Version einmalig bestätigbar - Formular-Konvention, siehe PurposeIntro(). */
    private function NewsBanner(): ?array
    {
        if ($this->ReadAttributeStringSafe('SeenNews') === self::NEWS_VERSION) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'NewsPanel', 'expanded' => true,
            'caption' => '🆕  New in this version',
            'items' => [
                ['type' => 'Label', 'caption' => '• 🌍 New: the tile now speaks German and English — the configuration form and the tile texts follow the Symcon language.'],
                ['type' => 'Label', 'caption' => '• 🪟 New: choose the form layout yourself - in the panel “🪟 Form: layout” you set for each section whether it is expanded, collapsed or hidden when the form opens and at which position it appears.'],
                ['type' => 'Label', 'caption' => '• 🔗 The data source now shows live which instance was detected; if exactly one is found, the selection is no longer needed.'],
                ['type' => 'Button', 'caption' => 'Understood – do not show again', 'onClick' => 'TGRTILE_AckNews($id);'],
            ],
        ];
    }

    public function AckNews(): void
    {
        $this->WriteAttributeString('SeenNews', self::NEWS_VERSION);
        $this->UpdateFormField('NewsPanel', 'visible', false);
    }

    /** Symcon-Forum-Hinweis — einmalig dismissible, kein Versionsbezug, siehe PurposeIntro(). */
    private function ForumHint(): ?array
    {
        if ($this->ReadAttributeBooleanSafe('ForumHintGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'ForumHintPanel', 'expanded' => true,
            'caption' => '💬  Feedback in the Symcon forum',
            'items' => [
                ['type' => 'Label', 'caption' => 'Feedback on this module is explicitly welcome in the community thread.'],
                ['type' => 'Button', 'caption' => 'To the forum thread', 'onClick' => "echo '" . self::FORUM_THREAD_URL . "';", 'link' => true],
                ['type' => 'Button', 'caption' => 'Understood – do not show again', 'onClick' => 'TGRTILE_AckForumHint($id);'],
            ],
        ];
    }

    public function AckForumHint(): void
    {
        $this->WriteAttributeBoolean('ForumHintGone', true);
        $this->UpdateFormField('ForumHintPanel', 'visible', false);
    }

    /**
     * Verbund-Konvention "Über dieses Modul" (SUITE.md Punkt 5) - Wortlaut verbundweit identisch
     * ("Variante A"), bewusst NICHT dismissible (Lizenz ist kein einmaliger Hinweis).
     */
    private function LicenseHint(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '🧡  About this module',
            'items' => [
                ['type' => 'Label', 'caption' => 'Born out of genuine enthusiasm for my own installation — and a few evenings of typing. Still: hobby software or not, this is intellectual property and real work went into it.'],
                ['type' => 'Label', 'caption' => 'License: PolyForm Noncommercial 1.0.0 — free for private and non-commercial use; commercial use needs a separate license from the rights holder.'],
                ['type' => 'Button', 'caption' => 'View license text', 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => 'Commercial use or questions about the license? Just get in touch: dietmar@gureth.eu'],
                ['type' => 'Label', 'caption' => 'Do you like the module and still want to leave something? I would be happy about a small donation — completely voluntary, nothing owed in return.'],
                ['type' => 'Button', 'caption' => '☕  Donate via PayPal', 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
            ],
        ];
    }

    /**
     * Live berechnete Statuszeile zur automatischen Datenquellen-Erkennung (SUITE.md "Verbund-
     * Verbindungen im Formular sichtbar machen"): ✅ verbunden (mit den übernommenen Werten und ihrer
     * Quelle), ⚠️ verbunden, aber Instanz nicht aktiv / mehrere Instanzen ohne Auswahl, ℹ️ nichts
     * gefunden (und was dann gilt).
     */
    private function SourceStatusLine(): string
    {
        $configured = $this->ReadPropertyIntegerSafe('SourceInstance');
        $list = IPS_GetInstanceListByModuleID(self::SOURCE_MODULE);
        $src = $this->ResolveSource();

        if ($src <= 0 || !IPS_InstanceExists($src)) {
            if (count($list) === 0) {
                return $this->Translate('ℹ️ No instance “Tibber Grid Rewards” (TibberGridReward) found. As long as there is none, the tile shows “No source selected” and no values - create the data module instance first.');
            }
            $names = [];
            foreach ($list as $id) {
                $names[] = '#' . $id . ' “' . IPS_GetName((int) $id) . '”';
            }
            return sprintf($this->Translate('⚠️ %d TibberGridReward instances found, but none selected (%s) - please choose the data source below, otherwise the tile shows no values.'), count($list), implode(', ', $names));
        }

        $how = ($configured === $src) ? $this->Translate('selected manually') : $this->Translate('detected automatically, only instance');
        $head = '#' . $src . ' “' . IPS_GetName($src) . '” (' . $how . ')';

        $status = (int) (IPS_GetInstance($src)['InstanceStatus'] ?? 0);
        $flex = trim((string) $this->ReadSourceValue($src, 'FlexDevices', ''));
        $devices = ($flex === '') ? 0 : count(preg_split('/\R/', $flex));
        $devicesText = ($devices === 1)
            ? sprintf($this->Translate('%d flex device'), $devices)
            : sprintf($this->Translate('%d flex devices'), $devices);
        $values = sprintf(
            $this->Translate('Status “%s”, grid reward mode %s, %s (source: variables of this instance)'),
            (string) $this->ReadSourceValue($src, 'State', '') ?: $this->Translate('unknown'),
            $this->FormattedSourceValue($src, 'GridRewardMode'),
            $devicesText
        );

        if ($configured > 0 && $configured !== $src) {
            return sprintf($this->Translate('⚠️ The selected data source #%d no longer exists - %s is used instead. Taken over: %s. Please clear the selection below or choose again.'), $configured, $head, $values);
        }
        if ($status !== 102) {
            return sprintf($this->Translate('⚠️ Data source %s found, but the instance is not active (status %d) - the values may be outdated. Last taken over: %s.'), $head, $status, $values);
        }
        return sprintf($this->Translate('✅ Data source %s connected. Taken over: %s.'), $head, $values);
    }

    private function FormattedSourceValue(int $instanceID, string $ident): string
    {
        $vid = @IPS_GetObjectIDByIdent($ident, $instanceID);
        if ($vid === false || $vid <= 0) {
            return $this->Translate('unknown');
        }
        return (string) GetValueFormatted($vid);
    }

    /**
     * Zustand des Quellen-Felds als [Zeile, Auswahlfeld ausblenden?] (SUITE.md "Wert kommt
     * automatisch"): 🔗 genau eine Instanz erkannt und keine gewählt -> Auswahl weg, Zeile mit Quelle;
     * ✏️ eigene Auswahl -> Feld bleibt; sonst keine Zeile (⚠️/ℹ️ steht in der Statuszeile darüber, das
     * Auswahlfeld bleibt sichtbar). Der erkannte Wert wird NIE in die Property geschrieben.
     */
    private function SourceFieldState(): array
    {
        $configured = $this->ReadPropertyIntegerSafe('SourceInstance');
        if ($configured > 0 && IPS_InstanceExists($configured)) {
            return [sprintf($this->Translate('✏️ Source: #%d “%s” (own selection)'), $configured, IPS_GetName($configured)), false];
        }
        $list = IPS_GetInstanceListByModuleID(self::SOURCE_MODULE);
        if ($configured <= 0 && count($list) === 1) {
            $id = (int) $list[0];
            return [sprintf($this->Translate('🔗 Source: #%d “%s” (detected automatically: only instance)'), $id, IPS_GetName($id)), true];
        }
        return ['', false];
    }

    /** Setzt eine Eigenschaft eines benannten Formularelements, sucht rekursiv durch alle "items". */
    private function SetFormProp(array &$elements, string $name, string $key, $value): bool
    {
        foreach ($elements as &$el) {
            if (!is_array($el)) {
                continue;
            }
            if (($el['name'] ?? '') === $name) {
                $el[$key] = $value;
                return true;
            }
            if (isset($el['items']) && is_array($el['items']) && $this->SetFormProp($el['items'], $name, $key, $value)) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------------
    // Formular: Aufbau (SUITE.md 3a) - je Fachpanel aufgeklappt/zugeklappt/ausgeblendet + Position.
    // Stabile Schlüssel sind die "name"-Felder der Fachpanels in form.json (nicht die Caption).
    // ---------------------------------------------------------------------

    /** Name der Fachpanels in Standardreihenfolge (wie in form.json). */
    private const FORM_PANELS = ['PanelColors', 'PanelSurfaces', 'PanelSim', 'PanelRules', 'PanelFont'];

    /**
     * Reihenfolge und Aufklapp-Zustand der Fachpanels aus der Einstellung "FormLayout". Unbekannte oder
     * fehlende Einträge fallen auf Standard und Standardposition zurück.
     *
     * @param string[] $keys Schlüssel der vorhandenen Fachpanels in Standardreihenfolge
     * @return array key => ['open' => 'std'|'open'|'closed'|'hidden', 'pos' => int, 'idx' => int], in anzuwendender Reihenfolge
     */
    private function FormLayout(array $keys): array
    {
        $saved = json_decode($this->ReadPropertyStringSafe('FormLayout'), true);
        $by = [];
        foreach (is_array($saved) ? $saved : [] as $row) {
            if (is_array($row) && isset($row['Key'])) {
                $by[(string) $row['Key']] = $row;
            }
        }
        $out = [];
        foreach ($keys as $i => $k) {
            $r = $by[$k] ?? [];
            // Vorrang bei Widerspruch: ausgeblendet vor zugeklappt vor aufgeklappt, kein Haken = Standard
            $open = !empty($r['Hidden']) ? 'hidden' : (!empty($r['Closed']) ? 'closed' : (!empty($r['Opened']) ? 'open' : 'std'));
            $out[$k] = [
                'open' => $open,
                'pos'  => isset($r['Pos']) ? max(1, min(99, (int) $r['Pos'])) : $i + 1,
                'idx'  => $i,
            ];
        }
        uasort($out, function ($a, $b) { return [$a['pos'], $a['idx']] <=> [$b['pos'], $b['idx']]; });
        return $out;
    }

    /** Beschriftung ohne Klammerzusatz, z. B. "💶 Preiskurve (offizielle Tibber-API)" -> "💶 Preiskurve". */
    private function FormPanelName(string $caption): string
    {
        return trim((string) preg_replace('/\s*\(.*$/u', '', $caption));
    }

    /**
     * Wendet den gewählten Aufbau auf die Fachpanels an. Die Panels tauschen nur untereinander die Plätze,
     * freistehende Felder dazwischen bleiben, wo sie sind. Ausgeblendet heißt visible=false, nie weglassen
     * (die Werte bleiben erhalten und wirken weiter).
     *
     * @return array{0:array,1:array} [Elemente, Panel "Formular: Aufbau"]
     */
    private function ApplyFormLayout(array $elements): array
    {
        $slots = [];
        $panels = [];
        foreach ($elements as $i => $el) {
            if (is_array($el) && ($el['type'] ?? '') === 'ExpansionPanel' && in_array($el['name'] ?? '', self::FORM_PANELS, true)) {
                $slots[] = $i;
                $panels[$el['name']] = $el;
            }
        }
        $layout = $this->FormLayout(array_keys($panels));
        $order = array_keys($layout);
        foreach ($slots as $j => $slot) {
            $key = $order[$j];
            $panel = $panels[$key];
            $state = $layout[$key]['open'];
            if ($state === 'open') {
                $panel['expanded'] = true;
            } elseif ($state === 'closed') {
                $panel['expanded'] = false;
            } elseif ($state === 'hidden') {
                $panel['visible'] = false;
            }
            $elements[$slot] = $panel;
        }
        return [$elements, $this->LayoutPanel($layout, $panels)];
    }

    /** Wählbarer Aufbau des Formulars: je Fachpanel Aufklapp-Zustand beim Öffnen und Position. */
    private function LayoutPanel(array $layout, array $panels): array
    {
        $rows = [];
        $hidden = [];
        foreach ($layout as $key => $l) {
            $name = $this->FormPanelName((string) ($panels[$key]['caption'] ?? $key));
            $rows[] = ['Key' => $key, 'Panel' => $name, 'Opened' => $l['open'] === 'open', 'Closed' => $l['open'] === 'closed',
                'Hidden' => $l['open'] === 'hidden', 'Pos' => $l['pos']];
            if ($l['open'] === 'hidden') {
                $hidden[] = trim((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $name));
            }
        }
        $hiddenLine = $hidden
            ? [['type' => 'Label', 'caption' => sprintf($this->Translate('🙈 Hidden: %s — to show again, remove the tick under “Hidden”. Hidden sections keep working, only the display is missing.'), implode(', ', $hidden))]]
            : [];
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => $this->Translate('🪟  Form: layout') . ($hidden ? ' ' . sprintf($this->Translate('(%d hidden)'), count($hidden)) : ''),
            'items' => array_merge($hiddenLine, [
                ['type' => 'Label', 'caption' => 'Here you decide which sections are expanded when the form opens and in which order they appear. Symcon does not remember expanding and collapsing by itself, so this setting applies every time the form opens. It takes effect after “Apply” the next time the form is opened.'],
                ['type' => 'Label', 'caption' => 'No tick means “Default”: as the module intends it. Anyone who chooses “Collapsed” will not see new content there right away either. The position is a number; with equal numbers the default order is kept. “Hidden” removes a section from the display without it losing anything: its settings are kept and keep working (meant for sections you rarely need). To show it again, remove the tick under “Hidden” or click “Reset to default”. If several ticks are set, “Hidden” takes precedence over “Collapsed”, which takes precedence over “Expanded”. The sections at the top (purpose, news, documentation) and at the bottom (feedback, about this module) stay where they are and cannot be hidden.'],
                [
                    'type' => 'List', 'name' => 'FormLayout', 'caption' => 'Sections', 'rowCount' => count($rows), 'add' => false, 'delete' => false,
                    'loadValuesFromConfiguration' => false,
                    'values' => $rows,
                    'columns' => [
                        ['caption' => 'Section', 'name' => 'Panel', 'width' => 'auto', 'add' => ''],
                        ['caption' => 'Key', 'name' => 'Key', 'width' => '0px', 'visible' => false, 'add' => '', 'save' => true],
                        ['caption' => 'Expanded', 'name' => 'Opened', 'width' => '110px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                        ['caption' => 'Collapsed', 'name' => 'Closed', 'width' => '110px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                        ['caption' => '🙈 Hidden', 'name' => 'Hidden', 'width' => '130px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                        ['caption' => 'Position', 'name' => 'Pos', 'width' => '100px', 'add' => 1, 'edit' => ['type' => 'NumberSpinner', 'minimum' => 1, 'maximum' => 99]],
                    ],
                ],
                ['type' => 'Button', 'caption' => '↩️ Reset to default', 'onClick' => 'echo TGRTILE_ResetFormLayout($id);'],
            ]),
        ];
    }

    /** Setzt Aufklapp-Zustand und Reihenfolge der Fachpanels im offenen Formular auf den Standard (gilt nach „Übernehmen“). */
    public function ResetFormLayout(): string
    {
        $form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true);
        $rows = [];
        $i = 0;
        foreach ($form['elements'] ?? [] as $el) {
            if (is_array($el) && ($el['type'] ?? '') === 'ExpansionPanel' && in_array($el['name'] ?? '', self::FORM_PANELS, true)) {
                $rows[] = ['Key' => $el['name'], 'Panel' => $this->FormPanelName((string) ($el['caption'] ?? $el['name'])),
                    'Opened' => false, 'Closed' => false, 'Hidden' => false, 'Pos' => ++$i];
            }
        }
        $this->UpdateFormField('FormLayout', 'values', json_encode($rows, JSON_UNESCAPED_UNICODE));
        return $this->Translate('ℹ️ Reset to default. Click “Apply” below to save.');
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->SetFormProp($form['elements'], 'SourceStatus', 'caption', $this->SourceStatusLine());
        [$sourceLine, $hideSource] = $this->SourceFieldState();
        $this->SetFormProp($form['elements'], 'SourceAuto', 'caption', $sourceLine);
        $this->SetFormProp($form['elements'], 'SourceAuto', 'visible', $sourceLine !== '');
        $this->SetFormProp($form['elements'], 'SourceAuto', 'color', $hideSource ? self::COLOR_AUTO : -1);
        $this->SetFormProp($form['elements'], 'SourceInstance', 'visible', !$hideSource);
        // Fachpanels nach dem gewählten Aufbau (SUITE.md 3a), Panel "Formular: Aufbau" davor.
        [$fachElements, $layoutPanel] = $this->ApplyFormLayout($form['elements']);
        $form['elements'] = array_values(array_filter(array_merge(
            [$this->PurposeIntro(), $this->NewsBanner()],
            $fachElements,
            [$layoutPanel, $this->ForumHint(), $this->LicenseHint()]
        )));
        return json_encode($form);
    }

    /**
     * Button-Aktion: alle Farben und Schrifteinstellungen auf Standard setzen. Über UpdateFormField
     * werden die Werte nur im offenen Formular gesetzt – der Benutzer prüft sie und bestätigt selbst
     * per „Änderungen übernehmen".
     */
    public function ResetStyle(): void
    {
        $this->UpdateFormField('ColorActive', 'value', self::DEF_ACTIVE);
        $this->UpdateFormField('ColorCurtailment', 'value', self::DEF_CURTAIL);
        $this->UpdateFormField('ColorAvailable', 'value', self::DEF_AVAILABLE);
        $this->UpdateFormField('ColorUnavailable', 'value', self::DEF_UNAVAILABLE);
        $this->UpdateFormField('ColorBackground', 'value', self::DEF_BACKGROUND);
        $this->UpdateFormField('ColorBox', 'value', self::DEF_BOX);
        $this->UpdateFormField('ColorText', 'value', self::DEF_TEXT);
        $this->UpdateFormField('ColorTextMuted', 'value', self::DEF_TEXTMUTED);
        $this->UpdateFormField('FontFamily', 'value', self::DEF_FONT);
        $this->UpdateFormField('FontScale', 'value', self::DEF_SCALE);
    }

    public function GetVisualizationTile()
    {
        $module = file_get_contents(__DIR__ . '/module.html');
        // handleMessage() ist erst im HTML definiert -> initialen Aufruf ans Ende hängen.
        $module .= '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        return $module;
    }

    /**
     * Wird durch requestAction() aus der Kachel (module.html) ausgelöst. Zwei Gruppen von Idents,
     * jede über ihre eigene Sichtbarkeits-Einstellung geschützt: die Simulations-Buttons
     * (ShowSimControls) und der Wenn->Dann-Regel-Editor (ShowAutomations). Reicht die Befehle an die
     * verbundene Datenquelle weiter – dort läuft exakt derselbe Code wie bei einem echten
     * Tibber-Ereignis bzw. wie beim Bearbeiten im Instanzformular.
     */
    public function RequestAction($Ident, $Value)
    {
        $src = $this->ResolveSource();
        if ($src <= 0 || !IPS_InstanceExists($src)) {
            $this->SendDebug(__FUNCTION__, 'Keine Datenquelle verbunden', 0);
            return;
        }

        if (in_array($Ident, ['Simulate', 'ResetSimulation'], true)) {
            if (!$this->ReadPropertyBooleanSafe('ShowSimControls')) {
                return;
            }
            switch ($Ident) {
                case 'Simulate':
                    if (in_array($Value, ['available', 'excess', 'shortage'], true)) {
                        @TIBBERGR_Simulate($src, (string) $Value);
                    }
                    break;
                case 'ResetSimulation':
                    @TIBBERGR_ResetSimulation($src);
                    break;
            }
            return;
        }

        if (in_array($Ident, ['rule', 'ruleEditor', 'targetOpts', 'condOpts', 'ruleSave', 'ruleDelete'], true)) {
            if (!$this->ReadPropertyBooleanSafe('ShowAutomations')) {
                return;
            }
            switch ($Ident) {
                case 'rule':
                    $data = json_decode((string) $Value, true);
                    if (is_array($data) && isset($data['i'])) {
                        @TIBBERGR_SetDataActionActive($src, (int) $data['i'], (bool) ($data['on'] ?? false));
                        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                    }
                    break;
                case 'ruleEditor':
                    $editor = json_decode((string) @TIBBERGR_GetDataActionEditor($src), true);
                    $this->UpdateVisualizationValue(json_encode(['editor' => is_array($editor) ? $editor : ['sources' => [], 'targets' => []]]));
                    break;
                case 'targetOpts':
                    $vid = (int) $Value;
                    $opts = json_decode((string) @TIBBERGR_GetTargetValueOptions($src, $vid), true);
                    $this->UpdateVisualizationValue(json_encode(['targetOpts' => ['vid' => $vid, 'options' => is_array($opts) ? $opts : []]]));
                    break;
                case 'condOpts':
                    // Profilwerte des gewählten Wenn-Datenpunkts (z. B. GridRewardMode) für den
                    // Vergleichswert-Dropdown im Regel-Editor; leer = freie Eingabe
                    $source = (string) $Value;
                    $vid = $source !== '' ? @IPS_GetObjectIDByIdent($source, $src) : false;
                    $opts = ($vid !== false && $vid > 0) ? json_decode((string) @TIBBERGR_GetTargetValueOptions($src, $vid), true) : [];
                    $this->UpdateVisualizationValue(json_encode(['condOpts' => ['source' => $source, 'options' => is_array($opts) ? $opts : []]]));
                    break;
                case 'ruleSave':
                    $data = json_decode((string) $Value, true);
                    if (is_array($data) && isset($data['rule'])) {
                        @TIBBERGR_SetDataAction($src, (int) ($data['i'] ?? -1), json_encode($data['rule']));
                        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                    }
                    break;
                case 'ruleDelete':
                    @TIBBERGR_DeleteDataAction($src, (int) $Value);
                    $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                    break;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Datenaufbereitung
    // ---------------------------------------------------------------------

    private function GetFullUpdateMessage(): string
    {
        $cActive = $this->ColorHex($this->ReadPropertyIntegerSafe('ColorActive'), '#27d07f');
        $cCurtail = $this->ColorHex($this->ReadPropertyIntegerSafe('ColorCurtailment'), '#e8a13a');
        $cAvail = $this->ColorHex($this->ReadPropertyIntegerSafe('ColorAvailable'), '#2bb3c0');
        $cUnavail = $this->ColorHex($this->ReadPropertyIntegerSafe('ColorUnavailable'), '#7a8a99');

        // Leerer String = nicht gesetzt -> die Kachel nutzt den Theme-Default aus dem CSS.
        $style = [
            'bg'        => $this->ColorOrEmpty($this->ReadPropertyIntegerSafe('ColorBackground')),
            'box'       => $this->ColorOrEmpty($this->ReadPropertyIntegerSafe('ColorBox')),
            'text'      => $this->ColorOrEmpty($this->ReadPropertyIntegerSafe('ColorText')),
            'textmuted' => $this->ColorOrEmpty($this->ReadPropertyIntegerSafe('ColorTextMuted')),
            'font'      => $this->FontStack($this->ReadPropertyStringSafe('FontFamily')),
            'scale'     => $this->FontScaleValue(),
        ];

        $bandColors = ['active' => $cActive, 'curtail' => $cCurtail, 'avail' => $cAvail, 'unavail' => $cUnavail];

        $src = $this->ResolveSource();
        if ($src <= 0 || !IPS_InstanceExists($src)) {
            return json_encode(array_merge($style, [
                'stateLabel' => $this->Translate('No source selected'),
                'cls'        => 'off',
                'accent'     => $cUnavail,
                'month'      => '–',
                'total'      => '–',
                'monthLabel' => $this->Translate('This month'),
                'totalLabel' => $this->Translate('Total'),
                'band'       => $this->BuildBand(0, $bandColors),
                'emptyLabel' => $this->Translate('No flex devices'),
                'devices'    => [],
                'sim'        => false, // ohne Quelle keine Simulations-Buttons
                'rules'      => null,
                'ui'         => $this->UiTexts(),
            ]));
        }

        $stateText = (string) $this->ReadSourceValue($src, 'State', '');
        $mode = (int) $this->ReadSourceValue($src, 'GridRewardMode', 0);

        // Statusakzent + Pulsieren aus dem Modus (2 = Laden grün, 3 = Drosselung bernstein)
        switch ($mode) {
            case 2:
                $cls = 'live';
                $accent = $cActive;
                break;
            case 3:
                $cls = 'live';
                $accent = $cCurtail;
                break;
            case 1:
                $cls = 'off';
                $accent = $cAvail;
                break;
            default:
                $cls = 'off';
                $accent = ($stateText === $this->Translate('Available')) ? $cAvail : $cUnavail;
        }

        $cur = $this->CurrencySymbol((string) $this->ReadSourceValue($src, 'Currency', ''));
        $month = $this->FormatMoney((float) $this->ReadSourceValue($src, 'RewardCurrentMonth', 0), $cur);
        $total = $this->FormatMoney((float) $this->ReadSourceValue($src, 'RewardAllTime', 0), $cur);

        // Wallbox-Gesamtleistung (nur wenn die Quelle die Variable hat)
        $wallbox = '';
        $wbVid = @IPS_GetObjectIDByIdent('WallboxPowerTotal', $src);
        if ($wbVid !== false && $wbVid > 0) {
            $wallbox = $this->FormatPower((float) GetValue($wbVid));
        }

        // Grid-Reward-Energie (Einsatz / heute / Monat / gesamt)
        $energy = [];
        foreach ([
            ['GridRewardEnergyEvent', $this->Translate('Event')],
            ['GridRewardEnergyToday', $this->Translate('Today')],
            ['GridRewardEnergyMonth', $this->Translate('Month')],
            ['GridRewardEnergyTotal', $this->Translate('Total')],
        ] as $pair) {
            $eid = @IPS_GetObjectIDByIdent($pair[0], $src);
            if ($eid !== false && $eid > 0) {
                $energy[] = ['label' => $pair[1], 'val' => $this->FormatKwh((float) GetValue($eid))];
            }
        }

        $devices = $this->ParseDevices((string) $this->ReadSourceValue($src, 'FlexDevices', ''), $cActive, $cAvail, $cUnavail);

        return json_encode(array_merge($style, [
            'stateLabel'   => $stateText !== '' ? $stateText : $this->Translate('No data yet'),
            'cls'          => $cls,
            'accent'       => $accent,
            'month'        => $month,
            'total'        => $total,
            'monthLabel'   => $this->Translate('This month'),
            'totalLabel'   => $this->Translate('Total'),
            'band'         => $this->BuildBand($mode, $bandColors),
            'wallbox'      => $wallbox,
            'wallboxLabel' => $this->Translate('Wallboxes'),
            'energy'       => $energy,
            'energyLabel'  => $this->Translate('Grid reward energy'),
            'emptyLabel'   => $this->Translate('No flex devices'),
            'devices'      => $devices,
            'sim'          => $this->ReadPropertyBooleanSafe('ShowSimControls'),
            'simAvailable' => $this->Translate('Available'),
            'simExcess'    => $this->Translate('Simulate charging (excess)'),
            'simShortage'  => $this->Translate('Simulate curtailment (shortage)'),
            'simReset'     => $this->Translate('Back to real status'),
            'rules'        => $this->ReadSourceRules($src),
            'ui'           => $this->UiTexts(),
        ]));
    }

    /**
     * Anzeigetexte der Kachel-Oberfläche (Regelliste und -editor) in der Symcon-Sprache. Translate()
     * liest bei jedem Aufruf die locale.json - deshalb wird die fertige Tabelle gepuffert und nur neu
     * gebaut, wenn sich die Sprache ändert (erkannt an der Übersetzung eines Prüfwerts).
     */
    private function UiTexts(): array
    {
        $probe = $this->Translate('Cancel');
        $cache = json_decode((string) $this->GetBuffer('UiTexts'), true);
        if (is_array($cache) && ($cache['probe'] ?? null) === $probe && is_array($cache['texts'] ?? null)) {
            return $cache['texts'];
        }
        $texts = [
            'edTitleEdit' => $this->Translate('Edit rule'),
            'edTitleNew' => $this->Translate('New rule'),
            'edIf' => $this->Translate('If (several conditions are combined with AND)'),
            'edAddCond' => $this->Translate('+ Condition (AND)'),
            'edThen' => $this->Translate('Then (up to 6 actions at the same time)'),
            'edAddAct' => $this->Translate('+ Action'),
            'edHint' => $this->Translate('Switchable variables (with an action) are offered. Other targets can be chosen in the instance form. The placeholder “WALLBOX” as a value is continuously replaced by the wallbox power currently required.'),
            'edCancel' => $this->Translate('Cancel'),
            'edSave' => $this->Translate('Save'),
            'rulesHeader' => $this->Translate('Automations'),
            'ruleNew' => $this->Translate('+ New rule'),
            'ruleDelete' => $this->Translate('Delete rule'),
            'ruleSure' => $this->Translate('Sure?'),
            'ruleDisable' => $this->Translate('Disable rule: '),
            'ruleEnable' => $this->Translate('Enable rule: '),
            'opTrue' => $this->Translate('turns ON (true)'),
            'opFalse' => $this->Translate('turns OFF (false)'),
            'opEq' => $this->Translate('= (equal)'),
            'opNe' => $this->Translate('≠ (not equal)'),
            'opGt' => $this->Translate('> (greater)'),
            'opGe' => $this->Translate('≥ (greater or equal)'),
            'opLt' => $this->Translate('< (less)'),
            'opLe' => $this->Translate('≤ (less or equal)'),
            'opChange' => $this->Translate('changes'),
            'condRemove' => $this->Translate('Remove condition'),
            'cmpPlaceholder' => $this->Translate('Comparison value'),
            'actRemove' => $this->Translate('Remove action'),
            'targetFilter' => $this->Translate('Search target variable (name or path) …'),
            'actOn' => $this->Translate('Switch on'),
            'actOff' => $this->Translate('Switch off'),
            'actToggle' => $this->Translate('Toggle'),
            'actValue' => $this->Translate('Set value'),
            'valPlaceholder' => $this->Translate('Value (e.g. WALLBOX)'),
            'tagIf' => $this->Translate('IF'),
            'tagAnd' => $this->Translate('AND'),
            'tagAction' => $this->Translate('ACTION'),
        ];
        $this->SetBuffer('UiTexts', json_encode(['probe' => $probe, 'texts' => $texts], JSON_UNESCAPED_UNICODE));
        return $texts;
    }

    /**
     * Wenn->Dann-Regeln der Quelle für die Kachel ([{i,text,active,rule}] oder null, wenn
     * Automationen in der Kachel ausgeblendet sind).
     */
    private function ReadSourceRules(int $instanceID): ?array
    {
        if (!$this->ReadPropertyBooleanSafe('ShowAutomations')) {
            return null;
        }
        $json = @TIBBERGR_GetDataActions($instanceID);
        $rules = is_string($json) ? json_decode($json, true) : null;
        return is_array($rules) ? $rules : null;
    }

    /**
     * Dauerhaftes Modus-Band: bei Modus 0 ausgegraut, sonst farbig je Richtung.
     * @param array{active:string,curtail:string,avail:string,unavail:string} $c
     */
    private function BuildBand(int $mode, array $c): array
    {
        // Icon-Pfade (viewBox 0 0 24 24): Blitz, Pfeil runter, Minus
        $bolt = 'M13 2 4 14h6l-1 8 9-12h-6z';
        $down = 'M11 4h2v9h3l-4 5-4-5h3z';
        $dash = 'M5 11h14v2H5z';

        switch ($mode) {
            case 1:
                return ['label' => $this->Translate('Car charging · from grid'), 'color' => $c['avail'],
                    'bg' => $this->Rgba($c['avail'], 0.16), 'icon' => $bolt];
            case 2:
                return ['label' => $this->Translate('Charge from grid'), 'color' => $c['active'],
                    'bg' => $this->Rgba($c['active'], 0.16), 'icon' => $bolt];
            case 3:
                return ['label' => $this->Translate('Curtailment'), 'color' => $c['curtail'],
                    'bg' => $this->Rgba($c['curtail'], 0.18), 'icon' => $down];
            default:
                return ['label' => $this->Translate('No event'), 'color' => '#8a96a4',
                    'bg' => 'rgba(127,135,145,.10)', 'icon' => $dash];
        }
    }

    private function Rgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(127,135,145,' . $alpha . ')';
        }
        return 'rgba(' . hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ','
            . hexdec(substr($hex, 4, 2)) . ',' . $alpha . ')';
    }

    private function FormatKwh(float $kwh): string
    {
        return number_format($kwh, 2, ',', '.') . ' kWh';
    }

    private function FormatPower(float $w): string
    {
        if (abs($w) >= 1000) {
            return number_format($w / 1000, 2, ',', '.') . ' kW';
        }
        return number_format($w, 0, ',', '.') . ' W';
    }

    /**
     * Zerlegt die FlexDevices-Textzeilen in Name, Meta (Typ + Zusatzinfos) und Farbpunkt.
     */
    private function ParseDevices(string $flexText, string $cActive, string $cAvail, string $cUnavail): array
    {
        $devices = [];
        $labelDelivering = $this->Translate('Delivering');
        $labelUnavailable = $this->Translate('Unavailable');
        $labelAvailable = $this->Translate('Available');

        foreach (explode("\n", $flexText) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('·', $line));
            $head = array_shift($parts); // "Name (Typ)"

            $name = $head;
            $type = '';
            if (preg_match('/^(.*?)\s*\(([^)]*)\)/u', $head, $m)) {
                $name = trim($m[1]);
                $type = trim($m[2]);
            }

            // Farbe nach Gerätestatus (Unavailable vor Available prüfen – Teilstring!)
            if (mb_strpos($line, $labelDelivering) !== false) {
                $color = $cActive;
            } elseif (mb_strpos($line, $labelUnavailable) !== false) {
                $color = $cUnavail;
            } elseif ($labelAvailable !== '' && mb_strpos($line, $labelAvailable) !== false) {
                $color = $cAvail;
            } else {
                $color = $cUnavail;
            }

            // Zusatzinfos: alle Segmente außer dem Status (der steckt im Farbpunkt)
            $extras = [];
            foreach ($parts as $p) {
                if ($p === $labelDelivering || $p === $labelUnavailable || $p === $labelAvailable) {
                    continue;
                }
                $extras[] = $p;
            }
            $metaParts = [];
            if ($type !== '') {
                $metaParts[] = $type;
            }
            foreach ($extras as $e) {
                $metaParts[] = $e;
            }

            $devices[] = [
                'name'  => $name,
                'meta'  => implode(' · ', $metaParts),
                'color' => $color,
            ];
        }
        return $devices;
    }

    /**
     * Ermittelt die Quell-Instanz: bevorzugt die manuell gewählte, sonst – wenn es im System genau
     * eine TibberGridReward-Instanz gibt – automatisch diese.
     */
    private function ResolveSource(): int
    {
        $configured = $this->ReadPropertyIntegerSafe('SourceInstance');
        if ($configured > 0 && IPS_InstanceExists($configured)) {
            return $configured;
        }
        $list = IPS_GetInstanceListByModuleID(self::SOURCE_MODULE);
        $this->SendDebug(__FUNCTION__, 'SourceInstance=' . $configured . ' · gefundene TibberGridReward-Instanzen: ' . count($list) . ' [' . implode(', ', $list) . ']', 0);
        if (count($list) === 1) {
            return (int) $list[0];
        }
        return 0;
    }

    private function ReadSourceValue(int $instanceID, string $ident, $default)
    {
        $vid = @IPS_GetObjectIDByIdent($ident, $instanceID);
        if ($vid === false || $vid <= 0) {
            return $default;
        }
        return GetValue($vid);
    }

    private function FontStack(string $key): string
    {
        switch ($key) {
            case 'arial':     return 'Arial, Helvetica, sans-serif';
            case 'verdana':   return 'Verdana, Geneva, sans-serif';
            case 'tahoma':    return 'Tahoma, Geneva, sans-serif';
            case 'trebuchet': return '"Trebuchet MS", Helvetica, sans-serif';
            case 'georgia':   return 'Georgia, "Times New Roman", serif';
            case 'courier':   return '"Courier New", Courier, monospace';
            case 'system':
            default:          return "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        }
    }

    private function FontScaleValue(): float
    {
        $v = $this->ReadPropertyFloatSafe('FontScale');
        if ($v < 0.5) {
            $v = 0.5;
        }
        if ($v > 2.5) {
            $v = 2.5;
        }
        return $v;
    }

    private function ColorHex(int $value, string $fallback): string
    {
        if ($value < 0) { // SelectColor: -1 = keine Farbe
            return $fallback;
        }
        return sprintf('#%06X', $value & 0xFFFFFF);
    }

    private function ColorOrEmpty(int $value): string
    {
        return $value < 0 ? '' : sprintf('#%06X', $value & 0xFFFFFF);
    }

    private function FormatMoney(float $value, string $currency): string
    {
        return number_format($value, 2, ',', '.') . ' ' . $currency;
    }

    private function CurrencySymbol(string $code): string
    {
        switch (strtoupper($code)) {
            case 'EUR': return '€';
            case 'SEK':
            case 'NOK':
            case 'DKK': return 'kr';
            case 'GBP': return '£';
            case 'USD': return '$';
            case '': return '€';
            default: return $code;
        }
    }
}
