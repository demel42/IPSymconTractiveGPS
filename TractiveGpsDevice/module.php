<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TractiveGpsDevice extends IPSModule
{
    use TractiveGps\StubsCommonLib;
    use TractiveGpsLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('tracker_id', '');
        $this->RegisterPropertyString('pet_id', '');
        $this->RegisterPropertyString('model_number', '');

        $this->RegisterPropertyBoolean('save_position', false);

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateData', 0, $this->GetModulePrefix() . '_UpdateData(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');
    }

    private function CheckConfiguration()
    {
        $r = [];

        $tracker_id = $this->ReadPropertyString('tracker_id');
        if ($tracker_id == '') {
            $this->SendDebug(__FUNCTION__, '"tracker_id" is needed', 0);
            $r[] = $this->Translate('Tracker-ID must be specified');
        }

        $pet_id = $this->ReadPropertyString('pet_id');
        if ($pet_id == '') {
            $this->SendDebug(__FUNCTION__, '"pet_id" is needed', 0);
            $r[] = $this->Translate('Pet-ID must be specified');
        }

        return $r;
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->UpdateData();
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = [];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $save_position = $this->ReadPropertyBoolean('save_position');

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
        $this->MaintainVariable('Position', $this->Translate('Position'), VARIABLETYPE_STRING, '', $vpos++, $save_position);

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $model_number = $this->ReadPropertyString('model_number');
        $summary = $model_number . ' (#' . $tracker_id . ')';
        $this->SetSummary($summary);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->SetStatus(self::$IS_DEACTIVATED);
            return;
        }

        $this->SetStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval(1);
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Tractive GPS Tracker');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'tracker_id',
                    'caption' => 'Tracker-ID',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'model_number',
                    'caption' => 'Model',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'pet_id',
                    'caption' => 'Pet-ID',
                    'enabled' => false
                ],
            ],
            'caption' => 'Basic configuration (don\'t change)',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'save position to (logged) variable \'Position\''
        ];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'save_position',
            'caption' => 'save position'
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

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update data',
            'onClick' => $this->GetModulePrefix() . '_UpdateData($id);'
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => $this->GetModulePrefix() . '_InstallVarProfiles($id, true);'
                ],
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

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
        $this->MaintainTimer('UpdateData', $msec);
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

                    $save_position = $this->ReadPropertyBoolean('save_position');
                    if ($save_position) {
                        $pos = json_encode([
                            'latitude'  => (float) $this->format_float($lat, 6),
                            'longitude' => (float) $this->format_float($lng, 6),
                            'altitude'  => (float) $altitude,
                        ]);
                        if ($this->GetValue('Position') != $pos) {
                            $this->SetValue('Position', $pos);
                            $this->SendDebug(__FUNCTION__, 'changed Position=' . $pos, 0);
                        }
                    }

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

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($ident) {
            case 'BuzzerActive':
                $r = $this->SwitchBuzzer((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                $interval = 15;
                $duration = 60;
                break;
            case 'LightActive':
                $r = $this->SwitchLight((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                $interval = 15;
                $duration = 60;
                break;
            case 'LiveTrackingActive':
                $r = $this->SwitchLiveTracking((bool) $value);
                if ((bool) $value) {
                    $interval = 5;
                    $j = json_decode($r, true);
                    $duration = isset($j['timeout']) ? $j['timeout'] + 30 : 300;
                } else {
                    $interval = 15;
                    $duration = 60;
                }
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r != false) {
            $this->SaveUpdateInterval($ident, $interval, $duration);
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
