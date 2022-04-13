<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TractiveGpsConfig extends IPSModule
{
    use TractiveGps\StubsCommonLib;
    use TractiveGpsLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function SetLocation()
    {
        $catID = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($catID >= 10000 && IPS_ObjectExists($catID)) {
            $tree_position[] = IPS_GetName($catID);
            $parID = IPS_GetObject($catID)['ParentID'];
            while ($parID > 0) {
                if ($parID > 0) {
                    $tree_position[] = IPS_GetName($parID);
                }
                $parID = IPS_GetObject($parID)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        $this->SendDebug(__FUNCTION__, 'tree_position=' . print_r($tree_position, true), 0);
        return $tree_position;
    }

    private function getConfiguratorValues()
    {
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
        }

        $data = ['DataID' => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', 'Function' => 'GetIndex'];
        $ret = $this->SendDataToParent(json_encode($data));
        $devices = json_decode($ret, true);
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);

        $guid = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($devices) && count($devices)) {
            foreach ($devices as $device) {
                $model_number = $device['model_number'];
                $tracker_id = $device['tracker_id'];
                $pet_name = $device['pet_name'];
                $pet_id = $device['pet_id'];

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'tracker_id') == $tracker_id) {
                        $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $entry = [
                    'instanceID'   => $instanceID,
                    'name'         => $pet_name,
                    'model_number' => $model_number,
                    'tracker_id'   => $tracker_id,
                    'create'       => [
                        'moduleID'      => $guid,
                        'location'      => $this->SetLocation(),
                        'info'          => 'Tractive (' . $pet_name . ')',
                        'configuration' => [
                            'tracker_id'   => $tracker_id,
                            'pet_id'       => $pet_id,
                            'model_number' => $model_number,
                        ]
                    ]
                ];
                $entries[] = $entry;
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            $name = IPS_GetName($instID);
            $model_number = IPS_GetProperty($instID, 'model_number');
            $tracker_id = IPS_GetProperty($instID, 'tracker_id');

            $entry = [
                'instanceID'        => $instID,
                'name'              => $name,
                'model_number'      => $model_number,
                'tracker_id'        => $tracker_id,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Tractive GPS Configurator'
        ];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        @$s = $this->CheckConfiguration();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s,
            ];
            $formElements[] = [
                'type'    => 'Label',
            ];
        }

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category for devices to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'    => 'Configurator',
            'name'    => 'trackers',
            'caption' => 'GPS Trackers',

            'rowCount' => count($entries),

            'add'    => false,
            'delete' => false,
            'sort'   => [
                'column'    => 'name',
                'direction' => 'ascending'
            ],
            'columns' => [
                [
                    'caption' => 'Model',
                    'name'    => 'model_number',
                    'width'   => '200px',
                ],
                [
                    'caption' => 'Pet',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Id',
                    'name'    => 'tracker_id',
                    'width'   => '200px'
                ]
            ],
            'values' => $entries,
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = $this->GetInformationForm();
        $formActions[] = $this->GetReferencesForm();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
