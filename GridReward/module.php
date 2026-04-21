
<?php
class GridReward extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterVariableBoolean(
            'Active',
            'Grid Reward aktiv',
            '~Switch'
        );
    }
}
