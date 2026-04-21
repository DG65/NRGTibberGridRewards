
<?php
class GridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Token", "");
        $this->RegisterVariableBoolean(
            "GridRewardActive",
            "Grid Reward aktiv",
            "~Switch"
        );
    }
}
