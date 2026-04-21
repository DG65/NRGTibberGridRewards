<?php

class TibberGridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Tibber API
        $this->RegisterPropertyString("Token", "");
        $this->RegisterPropertyString("GraphQLEndpoint", "https://api.tibber.com/v1-beta/gql");
        $this->RegisterPropertyString("GraphQLQuery", "");
        $this->RegisterPropertyString("ActiveJsonPath", "");

        // Status
        $this->RegisterVariableBoolean(
            "GridRewardActive",
            "Grid Reward aktiv",
            "~Switch",
            0
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterTimer(
            "Update",
            60 * 1000,
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
        $query = trim($this->ReadPropertyString("GraphQLQuery"));
        if ($query === "") {
            return;
        }

        $ch = curl_init($this->ReadPropertyString("GraphQLEndpoint"));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->ReadPropertyString("Token")
            ],
            CURLOPT_POSTFIELDS => json_encode(["query" => $query])
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return;
        }

        $json = json_decode($response, true);
        $active = $this->ExtractJsonPath(
            $json,
            $this->ReadPropertyString("ActiveJsonPath")
        );

        SetValue(
            $this->GetIDForIdent("GridRewardActive"),
            (bool)$active
        );
    }

    private function ExtractJsonPath(array $data, string $path)
    {
        $parts = explode(".", $path);
        foreach ($parts as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)) {
                return false;
            }
            $data = $data[$part];
        }
        return $data;
    }
}
