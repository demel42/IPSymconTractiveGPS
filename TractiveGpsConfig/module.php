<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TractiveGpsConfig extends IPSModule
{
    use TractiveGps\StubsCommonLib;
    use TractiveGpsLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ImportCategoryID'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);
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

        $catID = $this->ReadPropertyInteger('ImportCategoryID');

        $data = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', // an TractiveGpsIO
            'CallerID'   => $this->InstanceID,
            'Function'   => 'GetIndex'
        ];

        $ret = $this->SendDataToParent(json_encode($data));
        $devices = json_decode($ret, true);
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);

        $guid = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}'; // TractiveGpsDevice
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
                        $this->SendDebug(__FUNCTION__, 'device found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                if ($instanceID && IPS_GetInstance($instanceID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    continue;
                }

                $entry = [
                    'instanceID'   => $instanceID,
                    'name'         => $pet_name,
                    'model_number' => $model_number,
                    'tracker_id'   => $tracker_id,
                    'create'       => [
                        'moduleID'      => $guid,
                        'location'      => $this->GetConfiguratorLocation($catID),
                        'info'          => 'Tractive (' . $pet_name . ')',
                        'configuration' => [
                            'tracker_id'   => $tracker_id,
                            'pet_id'       => $pet_id,
                            'model_number' => $model_number,
                        ]
                    ]
                ];
                $entries[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
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

            if (IPS_GetInstance($instID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
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
        $formElements = $this->GetCommonFormElements('Tractive GPS Configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category for devices to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'     => 'Configurator',
            'name'     => 'trackers',
            'caption'  => 'GPS Trackers',
            'rowCount' => count($entries),
            'add'      => false,
            'delete'   => false,
            'sort'     => [
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
            'values'            => $entries,
            'discoveryInterval' => 60 * 60 * 24,
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

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
