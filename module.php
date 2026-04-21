
<?php
class TibberGridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('GraphQLEndpoint', 'https://api.tibber.com/v1-beta/gql');
        $this->RegisterPropertyString('GraphQLQuery', '');
        $this->RegisterPropertyString('ActiveJsonPath', '');

        $this->RegisterVariableBoolean('GridRewardActive', 'Grid Reward aktiv', '~Switch');
        $this->EnableAction('GridRewardActive');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterTimer('Update', 60000, 'TGR_Update($_IPS["TARGET"]);');
    }

    public function Update()
    {
        $query = $this->ReadPropertyString('GraphQLQuery');
        if ($query === '') { return; }

        $ch = curl_init($this->ReadPropertyString('GraphQLEndpoint'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->ReadPropertyString('Token')
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query])
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp === false) { return; }

        $data = json_decode($resp, true);
        $active = $this->ExtractByPath($data, $this->ReadPropertyString('ActiveJsonPath'));
        SetValue($this->GetIDForIdent('GridRewardActive'), (bool)$active);
    }

    private function ExtractByPath($data, $path)
    {
        $parts = explode('.', $path);
        foreach ($parts as $p) {
            if (!isset($data[$p])) return false;
            $data = $data[$p];
        }
        return $data;
    }
}
