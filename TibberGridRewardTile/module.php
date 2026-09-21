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

    // Verbund-Konvention "Über dieses Modul" (SUITE.md Punkt 5) - <Branch> zeigt auf den Branch,
    // der wirklich den aktuellen PolyForm-Text trägt, NICHT blind main.
    private const LICENSE_URL = 'https://github.com/DG65/NRGTibberGridRewards/blob/ems-integration/LICENSE';
    private const PAYPAL_URL = 'https://paypal.me/DietmarGureth';

    // Farbe der 🔗-Zeilen ("automatisch übernommen", SUITE.md "Wert kommt automatisch"); -1 = Standardfarbe.
    private const COLOR_AUTO = 0x2E8B3D;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Formular-Konvention - Referenzimplementierung MeterHub. Kein News-Panel hier: an der
        // Kachel selbst gab es zuletzt nichts eigenständig Neues zu vermelden (die Neuigkeiten
        // betreffen alle das Datenmodul) - ein News-Panel ohne echten Inhalt wäre erfundene
        // Neuigkeit (siehe "keine eigene Anlage als Norm" - analog: keine erfundenen Neuigkeiten).
        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeBoolean('ForumHintGone', false);

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

        // Als HTML-Kachel-Visualisierung anmelden
        $this->SetVisualizationType(1);
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

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

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
        } else {
            $this->SetStatus(104);
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
            'caption' => '👋  Wozu dieses Modul?',
            'items' => [
                ['type' => 'Label', 'caption' => 'Diese Kachel zeigt den Grid-Rewards-Status einer TibberGridReward-Instanz als eigenständige, frei gestaltbare Status-Kachel im WebFront — Einsatz, verdiente Prämie, Wallbox-Leistung und Energie-Statistik auf einen Blick.'],
                ['type' => 'Label', 'caption' => 'Wähle unten deine Datenquelle (die TibberGridReward-Instanz) — die Farben/Schrift lassen sich darunter frei anpassen. Optional: ein Regel-Editor und Simulations-Schaltflächen zum Testen ohne echten Einsatz.'],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'TGRTILE_AckPurposeIntro($id);'],
            ],
        ];
    }

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    /** Symcon-Forum-Hinweis — einmalig dismissible, kein Versionsbezug, siehe PurposeIntro(). */
    private function ForumHint(): ?array
    {
        if ($this->ReadAttributeBooleanSafe('ForumHintGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'ForumHintPanel', 'expanded' => true,
            'caption' => '💬  Feedback im Symcon-Forum',
            'items' => [
                ['type' => 'Label', 'caption' => 'Rückmeldungen zu diesem Modul sind ausdrücklich willkommen im Community-Thread.'],
                ['type' => 'Button', 'caption' => 'Zum Forums-Thread', 'onClick' => "echo '" . self::FORUM_THREAD_URL . "';", 'link' => true],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'TGRTILE_AckForumHint($id);'],
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
            'caption' => '🧡  Über dieses Modul',
            'items' => [
                ['type' => 'Label', 'caption' => 'Entstanden aus echter Begeisterung für die eigene Anlage — und ein paar durchgetippten Abenden. Trotzdem: Software-Hobby hin oder her, das hier ist geistiges Eigentum und echte Arbeit steckt drin.'],
                ['type' => 'Label', 'caption' => 'Lizenz: PolyForm Noncommercial 1.0.0 — privat und nicht-kommerziell frei nutzbar, für den gewerblichen Einsatz braucht es eine gesonderte Lizenz vom Rechteinhaber.'],
                ['type' => 'Button', 'caption' => 'Lizenztext ansehen', 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => 'Gewerbliche Nutzung oder Fragen zur Lizenz? Einfach melden: dietmar@gureth.eu'],
                ['type' => 'Label', 'caption' => 'Gefällt dir das Modul und du möchtest trotzdem etwas dalassen? Über eine kleine Spende freue ich mich — völlig freiwillig, keine Gegenleistung nötig.'],
                ['type' => 'Button', 'caption' => '☕  Spenden via PayPal', 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
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
                return 'ℹ️ Keine Instanz „Tibber Grid Rewards" (TibberGridReward) gefunden. Solange es keine gibt, zeigt die Kachel „Keine Quelle gewählt" und keine Werte - zuerst die Datenmodul-Instanz anlegen.';
            }
            $names = [];
            foreach ($list as $id) {
                $names[] = '#' . $id . ' „' . IPS_GetName((int) $id) . '"';
            }
            return '⚠️ ' . count($list) . ' TibberGridReward-Instanzen gefunden, aber keine ausgewählt (' . implode(', ', $names) . ') - bitte unten die Datenquelle wählen, sonst zeigt die Kachel keine Werte.';
        }

        $how = ($configured === $src) ? 'manuell gewählt' : 'automatisch erkannt, einzige Instanz';
        $head = '#' . $src . ' „' . IPS_GetName($src) . '" (' . $how . ')';

        $status = (int) (IPS_GetInstance($src)['InstanceStatus'] ?? 0);
        $flex = trim((string) $this->ReadSourceValue($src, 'FlexDevices', ''));
        $devices = ($flex === '') ? 0 : count(preg_split('/\R/', $flex));
        $values = 'Status „' . ((string) $this->ReadSourceValue($src, 'State', '') ?: 'unbekannt') . '"'
            . ', Grid-Reward-Modus ' . $this->FormattedSourceValue($src, 'GridRewardMode')
            . ', ' . $devices . ' Flex-' . ($devices === 1 ? 'Gerät' : 'Geräte')
            . ' (Quelle: Variablen dieser Instanz)';

        if ($configured > 0 && $configured !== $src) {
            return '⚠️ Die gewählte Datenquelle #' . $configured . ' existiert nicht mehr - ersatzweise wird ' . $head . ' verwendet. Übernommen werden: ' . $values . '. Bitte die Auswahl unten leeren oder neu wählen.';
        }
        if ($status !== 102) {
            return '⚠️ Datenquelle ' . $head . ' gefunden, aber die Instanz ist nicht aktiv (Status ' . $status . ') - die Werte können veraltet sein. Zuletzt übernommen: ' . $values . '.';
        }
        return '✅ Datenquelle ' . $head . ' verbunden. Übernommen werden: ' . $values . '.';
    }

    private function FormattedSourceValue(int $instanceID, string $ident): string
    {
        $vid = @IPS_GetObjectIDByIdent($ident, $instanceID);
        if ($vid === false || $vid <= 0) {
            return 'unbekannt';
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
            return ['✏️ Quelle: #' . $configured . ' „' . IPS_GetName($configured) . '" (eigene Auswahl)', false];
        }
        $list = IPS_GetInstanceListByModuleID(self::SOURCE_MODULE);
        if ($configured <= 0 && count($list) === 1) {
            $id = (int) $list[0];
            return ['🔗 Quelle: #' . $id . ' „' . IPS_GetName($id) . '" (automatisch erkannt: einzige Instanz)', true];
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

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->SetFormProp($form['elements'], 'SourceStatus', 'caption', $this->SourceStatusLine());
        [$sourceLine, $hideSource] = $this->SourceFieldState();
        $this->SetFormProp($form['elements'], 'SourceAuto', 'caption', $sourceLine);
        $this->SetFormProp($form['elements'], 'SourceAuto', 'visible', $sourceLine !== '');
        $this->SetFormProp($form['elements'], 'SourceAuto', 'color', $hideSource ? self::COLOR_AUTO : -1);
        $this->SetFormProp($form['elements'], 'SourceInstance', 'visible', !$hideSource);
        $form['elements'] = array_values(array_filter(array_merge(
            [$this->PurposeIntro()],
            $form['elements'],
            [$this->ForumHint(), $this->LicenseHint()]
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
        ]));
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
