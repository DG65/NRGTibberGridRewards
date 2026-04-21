<?php

class GridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterVariableBoolean(
            "Active",
            "Grid Reward aktiv",
            "~Switch",
            0
        );

        // alle 60 Sekunden prüfen
        $this->RegisterTimer(
            "UpdateTimer",
            60000,
            'IPS_RequestAction(' . $this->InstanceID . ', "Update", 0);'
        );
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === "Update") {
            $this->Update();
        }
    }

    public function Update()
    {
        $current = GetValue($this->GetIDForIdent("Active"));
        SetValue($this->GetIDForIdent("Active"), !$current);
    }
}
