<?php

class GridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Token", "");
        $this->RegisterPropertyString("GraphQLEndpoint", "https://api.tibber.com/v1-beta/gql");
        $this->RegisterPropertyString("GraphQLQuery", "");
        $this->RegisterPropertyString("ActiveJsonPath", "");

        $this->RegisterVariableBoolean(
            "GridRewardActive",
            "Grid Reward aktiv",
            "~Switch"
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterTimer(
            "Update",
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
        // API‑Logik hier
    }
}
