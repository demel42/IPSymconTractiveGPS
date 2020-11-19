<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveGpsDevice extends IPSModule
{
    use TractiveGpsCommonLib;
    use TractiveGpsLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('tracker_id', '');
        $this->RegisterPropertyString('pet_id', '');
        $this->RegisterPropertyString('model_number', '');

        $this->RegisterPropertyInteger('update_interval', '5');

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1];
        $this->CreateVarProfile('TractiveGps.Switch', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations);

        $this->CreateVarProfile('TractiveGps.BatteryLevel', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '');

        $this->CreateVarProfile('TractiveGps.Altitude', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '');
        $this->CreateVarProfile('TractiveGps.Speed', VARIABLETYPE_FLOAT, ' km/h', 0, 0, 0, 0, '');
        $this->CreateVarProfile('TractiveGps.Course', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 0, '');
        $this->CreateVarProfile('TractiveGps.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '');
        $this->CreateVarProfile('TractiveGps.Uncertainty', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '');

        $this->RegisterTimer('UpdateData', 0, 'TractiveGps_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->UpdateData();
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $pet_id = $this->ReadPropertyString('pet_id');
        $model_number = $this->ReadPropertyString('model_number');

        $vpos = 1;

        $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_STRING, '', $vpos++, true);

        $vpos = 10;
        $this->MaintainVariable('LastContact', $this->Translate('Last transmission'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('BatteryLevel', $this->Translate('Battery level'), VARIABLETYPE_INTEGER, 'TractiveGps.BatteryLevel', $vpos++, true);
        $this->MaintainVariable('TemperatureState', $this->Translate('Temperature state'), VARIABLETYPE_STRING, '', $vpos++, true);

        $vpos = 20;
        $this->MaintainVariable('LastPositionMessage', $this->Translate('Last position message'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastLongitude', $this->Translate('Longitude'), VARIABLETYPE_FLOAT, 'TractiveGps.Location', $vpos++, true);
        $this->MaintainVariable('LastLatitude', $this->Translate('Latitude'), VARIABLETYPE_FLOAT, 'TractiveGps.Location', $vpos++, true);
        $this->MaintainVariable('Altitude', $this->Translate('Altitude'), VARIABLETYPE_FLOAT, 'TractiveGps.Altitude', $vpos++, true);
        $this->MaintainVariable('Speed', $this->Translate('Speed'), VARIABLETYPE_FLOAT, 'TractiveGps.Speed', $vpos++, true);
        $this->MaintainVariable('Course', $this->Translate('Course'), VARIABLETYPE_FLOAT, 'TractiveGps.Course', $vpos++, true);
        $this->MaintainVariable('SensorUsed', $this->Translate('Used sensor'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('PositionUncertainty', $this->Translate('Position uncerntainty'), VARIABLETYPE_FLOAT, 'TractiveGps.Uncertainty', $vpos++, true);

        $vpos = 30;
        $this->MaintainVariable('BuzzerActive', $this->Translate('Buzzer'), VARIABLETYPE_BOOLEAN, 'TractiveGps.Switch', $vpos++, true);
        $this->MaintainAction('BuzzerActive', true);
        $vpos = 40;
        $this->MaintainVariable('LightActive', $this->Translate('Light'), VARIABLETYPE_BOOLEAN, 'TractiveGps.Switch', $vpos++, true);
        $this->MaintainAction('LightActive', true);
        $vpos = 50;
        $this->MaintainVariable('LiveTrackingActive', $this->Translate('Live tracking'), VARIABLETYPE_BOOLEAN, 'TractiveGps.Switch', $vpos++, true);
        $this->MaintainAction('LiveTrackingActive', true);

        $vpos = 90;
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastChange', $this->Translate('Last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $summary = $model_number . ' (#' . $tracker_id . ')';
        $this->SetSummary($summary);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }

        if ($tracker_id == '' || $pet_id == '') {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetUpdateInterval(0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval(1);
        }
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
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Instance is disabled'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Tractive GPS Tracker'
        ];

        $items = [];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'tracker_id',
            'caption' => 'Tracker-ID',
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'model_number',
            'caption' => 'Model',
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'pet_id',
            'caption' => 'Pet-ID',
            'enabled' => false
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)',
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Update data every X minutes'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'caption' => 'Minutes'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update data',
            'onClick' => 'TractiveGps_UpdateData($id);'
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ]
            ]
        ];

        return $formActions;
    }

    protected function SetUpdateInterval($sec = null)
    {
        if ($sec == null) {
            $sec = $this->CalcNextInterval();
        }
        if ($sec == null) {
            $min = $this->ReadPropertyInteger('update_interval');
            $sec = $min * 60;
        }
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
        $this->SendDebug(__FUNCTION__, 'sec=' . $sec . ', msec=' . $msec, 0);
    }

    public function UpdateData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            if ($this->GetStatus() == self::$IS_NOLOGIN) {
                $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => pause', 0);
                $this->SetUpdateInterval(0);
            } else {
                $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            }
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $pet_id = $this->ReadPropertyString('pet_id');
        $sendData = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}',
            'Function'   => 'GetUpdateData',
            'tracker_id' => $tracker_id,
            'pet_id'     => $pet_id,
        ];
        $this->SendDebug(__FUNCTION__, 'sendData=' . print_r($sendData, true), 0);
        $receiveData = $this->SendDataToParent(json_encode($sendData));
        $this->SendDebug(__FUNCTION__, 'receiveData=' . print_r($receiveData, true), 0);
        $this->decodeUpdateData($receiveData);

        $this->SetUpdateInterval();
        $this->SetStatus(IS_ACTIVE);
    }

    private function decodeUpdateData($data)
    {
        if ($data == false) {
            $this->SendDebug(__FUNCTION__, 'no data', 0);
            return;
        }

        $jdata = json_decode($data, true);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'malformed data', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $now = time();
        $is_changed = false;

        foreach ($jdata as $elem) {
            $_type = $elem['_type'];
            $this->SendDebug(__FUNCTION__, $_type . ' => ' . print_r($elem, true), 0);
            switch ($_type) {
                case 'device_hw_report':
                    $last_contact = $this->GetArrayElem($elem, 'time', '');
                    $this->SaveValue('LastContact', $last_contact, $is_changed);

                    $battery_level = $this->GetArrayElem($elem, 'battery_level', '');
                    $this->SetValue('BatteryLevel', (int) $battery_level);

                    $temperature_state = $this->GetArrayElem($elem, 'temperature_state', '');
                    $this->SetValue('TemperatureState', $this->Translate($temperature_state));
                    break;
                case 'device_pos_report':
                    $last_pos = $this->GetArrayElem($elem, 'time', '');
                    $this->SaveValue('LastPositionMessage', $last_pos, $is_changed);

                    $lat = $this->GetArrayElem($elem, 'latlong.0', '');
                    $this->SaveValue('LastLatitude', $lat, $is_changed);

                    $lng = $this->GetArrayElem($elem, 'latlong.1', '');
                    $this->SaveValue('LastLongitude', $lng, $is_changed);

                    $altitude = $this->GetArrayElem($elem, 'altitude', '');
                    $this->SaveValue('Altitude', $altitude, $is_changed);

                    $speed = $this->GetArrayElem($elem, 'speed', '');
                    if ($speed != '') {
                        $this->SaveValue('Speed', $speed, $is_changed);
                    }

                    $course = $this->GetArrayElem($elem, 'course', '');
                    if ($course != '') {
                        $this->SaveValue('Course', $course, $is_changed);
                    }

                    $pos_uncertainty = $this->GetArrayElem($elem, 'pos_uncertainty', '');
                    if ($pos_uncertainty != '') {
                        $this->SaveValue('PositionUncertainty', $pos_uncertainty, $is_changed);
                    }

                    $sensor_used = $this->GetArrayElem($elem, 'sensor_used', '');
                    $this->SaveValue('SensorUsed', $sensor_used, $is_changed);
                    break;
                case 'tracker':
                    $state = $this->GetArrayElem($elem, 'state', '');
                    $this->SetValue('State', $this->Translate($state));
                    break;
                case 'tracker_command_state':
                    $_id = $elem['_id'];
                    if (preg_match('?^[^_]*_(.*)$?', $_id, $r)) {
                        $_id = $r[1];
                    }
                    switch ($_id) {
                        case 'buzzer_control':
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $this->SetValue('BuzzerActive', (bool) $pending, $is_changed);
                            $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', id=' . $_id . ', elem=' . print_r($elem, true), 0);
                            break;
                        case 'led_control':
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $this->SetValue('LightActive', (bool) $pending, $is_changed);
                            $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', id=' . $_id . ', elem=' . print_r($elem, true), 0);
                            break;
                        case 'live_tracking':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $this->SetValue('LiveTrackingActive', (bool) $active, $is_changed);
                            $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', id=' . $_id . ', elem=' . print_r($elem, true), 0);
                            break;
                    }
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', elem=' . print_r($elem, true), 0);
                    break;
            }
        }

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $now);
        }

        $operational = $this->GetValue('State') == 'in Betrieb';
        $this->AdjustActions($operational);
    }

    private function AdjustActions($mode)
    {
        $chg = false;

        $chg |= $this->AdjustAction('BuzzerActive', $mode);
        $chg |= $this->AdjustAction('LightActive', $mode);
        $chg |= $this->AdjustAction('LiveTrackingActive', $mode);

        if ($chg) {
            $this->ReloadForm();
        }
    }

    private function SendTrackerCommand($func, $payload)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent instance', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return false;
        }

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $sendData = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}',
            'Function'   => $func,
            'tracker_id' => $tracker_id,
            'payload'    => $payload
        ];
        $this->SendDebug(__FUNCTION__, 'sendData=' . print_r($sendData, true), 0);
        $receiveData = $this->SendDataToParent(json_encode($sendData));
        $this->SendDebug(__FUNCTION__, 'receiveData=' . print_r($receiveData, true), 0);
        return $receiveData;
    }

    private function checkAction($func, $verbose)
    {
        $operational = $this->GetValue('State') == 'in Betrieb';

        $enabled = false;
        switch ($func) {
            case 'SwitchBuzzer':
                if ($operational) {
                    $enabled = true;
                }
                break;
            case 'SwitchLight':
                if ($operational) {
                    $enabled = true;
                }
                break;
            case 'SwitchLiveTracking':
                if ($operational) {
                    $enabled = true;
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported action "' . $func . '"', 0);
                break;
        }

        $this->SendDebug(__FUNCTION__, 'action "' . $func . '" is ' . ($enabled ? 'enabled' : 'disabled'), 0);
        return $enabled;
    }

    private function SwitchBuzzer(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $payload = [
            'mode' => $mode
        ];
        return $this->SendTrackerCommand(__FUNCTION__, $payload);
    }

    private function SwitchLight(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $payload = [
            'mode' => $mode
        ];
        return $this->SendTrackerCommand(__FUNCTION__, $payload);
    }

    private function SwitchLiveTracking(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $payload = [
            'mode' => $mode
        ];
        return $this->SendTrackerCommand(__FUNCTION__, $payload);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($Ident) {
            case 'BuzzerActive':
                $r = $this->SwitchBuzzer((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                $interval = 15;
                $duration = 60;
                break;
            case 'LightActive':
                $r = $this->SwitchLight((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                $interval = 15;
                $duration = 60;
                break;
            case 'LiveTrackingActive':
                $r = $this->SwitchLiveTracking((bool) $Value);
                if ((bool) $Value) {
                    $interval = 5;
                    $j = json_decode($r, true);
                    $duration = isset($j['timeout']) ? $j['timeout'] + 30 : 300;
                } else {
                    $interval = 15;
                    $duration = 60;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
        if ($r != false) {
            $this->SaveUpdateInterval($Ident, $interval, $duration);
            $this->SetUpdateInterval();
        }
    }

    private function SaveUpdateInterval($ident, $interval, $duration)
    {
        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', interval=' . $interval . ', duration=' . $duration, 0);
        $sdata = $this->GetBuffer('UpdateInterval');
        $entryList = json_decode($sdata, true);
        if ($entryList == false) {
            $entryList = [];
        }
        $entryList[$ident] = [
            'interval' => $interval,
            'until'    => time() + $duration
        ];
        $this->SendDebug(__FUNCTION__, 'entryList=' . print_r($entryList, true), 0);
        $this->SetBuffer('UpdateInterval', json_encode($entryList));
    }

    private function CalcNextInterval()
    {
        $sdata = $this->GetBuffer('UpdateInterval');
        $entryList = json_decode($sdata, true);
        if ($entryList == false) {
            $entryList = [];
        }
        $now = time();
        $interval = null;
        $_entryList = [];
        foreach ($entryList as $ident => $entry) {
            $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            if ($entry['until'] < $now) {
                continue;
            }
            $_entryList[$ident] = $entry;
            if ($interval == null || $interval > $entry['interval']) {
                $interval = $entry['interval'];
            }
        }
        $this->SetBuffer('UpdateInterval', json_encode($_entryList));
        $this->SendDebug(__FUNCTION__, 'interval=' . $interval . ', entryList=' . print_r($entryList, true), 0);
        return $interval;
    }

    private function ClearUpdateInterval($ident)
    {
        $this->SendDebug(__FUNCTION__, 'ident=' . $ident, 0);
        $sdata = $this->GetBuffer('UpdateInterval');
        $entryList = json_decode($sdata, true);
        if ($entryList == false) {
            $entryList = [];
        }
        $now = time();
        $_entryList = [];
        foreach ($entryList as $_ident => $entry) {
            if ($_ident == $ident) {
                continue;
            }
            if ($entry['until'] < $now) {
                continue;
            }
            $_entryList[$_ident] = $entry;
        }
        $this->SendDebug(__FUNCTION__, 'entryList=' . print_r($entryList, true), 0);
        $this->SetBuffer('UpdateInterval', json_encode($_entryList));
    }
}
