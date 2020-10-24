<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveGpsConfig extends IPSModule
{
    use TractiveGpsCommonLib;
    use TractiveGpsLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');
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
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    private function SetLocation()
    {
        $category = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($category > 0 && IPS_ObjectExists($category)) {
            $tree_position[] = IPS_GetName($category);
            $parent = IPS_GetObject($category)['ParentID'];
            while ($parent > 0) {
                if ($parent > 0) {
                    $tree_position[] = IPS_GetName($parent);
                }
                $parent = IPS_GetObject($parent)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        return $tree_position;
    }

    private function getConfiguratorValues()
    {
        $entries = [];

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return $entries;
        }

        $SendData = ['DataID' => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', 'Function' => 'GetIndex'];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        if ($data != '') {
            $elems = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'elems=' . print_r($elems, true), 0);

            $guid = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($elems as $elem) {
                $model_number = $elem['model_number'];
                $tracker_id = $elem['tracker_id'];
                $pet_name = $elem['pet_name'];
                $pet_id = $elem['pet_id'];

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
                    'model_number' => $model_number,
                    'name'         => $pet_name,
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

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = [];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'category for devices to be created:'
        ];
        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category'
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

        return $formActions;
    }
}
