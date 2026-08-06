<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TractiveGpsDevice extends IPSModule
{
    use TractiveGps\StubsCommonLib;
    use TractiveGpsLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('tracker_id', '');
        $this->RegisterPropertyString('pet_id', '');
        $this->RegisterPropertyString('model_number', '');

        $this->RegisterPropertyBoolean('save_position', false);
        $this->RegisterPropertyBoolean('save_track_history', false);
        $this->RegisterPropertyInteger('track_history_minutes', 60);

        $this->RegisterPropertyBoolean('with_activity', false);
        $this->RegisterPropertyBoolean('with_sleep', false);
        $this->RegisterPropertyBoolean('with_heart_rate', false);
        $this->RegisterPropertyBoolean('with_respiratory_rate', false);
        $this->RegisterPropertyBoolean('with_bark', false);
        $this->RegisterPropertyBoolean('with_scratch', false);

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));
        $this->RegisterAttributeInteger('TrackHistoryLast', 0);

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateData', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");');

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
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
            $this->SetUpdateInterval(1);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
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

        $with_activity = $this->ReadPropertyBoolean('with_activity');
        $with_sleep = $this->ReadPropertyBoolean('with_sleep');
        $with_heart_rate = $this->ReadPropertyBoolean('with_heart_rate');
        $with_respiratory_rate = $this->ReadPropertyBoolean('with_respiratory_rate');
        $with_bark = $this->ReadPropertyBoolean('with_bark');
        $with_scratch = $this->ReadPropertyBoolean('with_scratch');

        $vpos = 70;
        $this->MaintainVariable('MinutesActive', $this->Translate('Activity'), VARIABLETYPE_INTEGER, 'TractiveGps.Minutes', $vpos++, $with_activity);

        $this->MaintainVariable('MinutesDaySleep', $this->Translate('Daytime sleep'), VARIABLETYPE_INTEGER, 'TractiveGps.Minutes', $vpos++, true);
        $this->MaintainVariable('MinutesNightSleep', $this->Translate('Nighttime sleep'), VARIABLETYPE_INTEGER, 'TractiveGps.Minutes', $vpos++, true);
        $this->MaintainVariable('MinutesCalm', $this->Translate('Calm phase'), VARIABLETYPE_INTEGER, 'TractiveGps.Minutes', $vpos++, true);

        /*
        $this->MaintainVariable('RestingHeartRate', $this->Translate('Rest heart rate'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('RestingRespiratoryRate', $this->Translate('Rest respiratory rate'), VARIABLETYPE_STRING, '', $vpos++, true);
         */

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
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

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
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'save position to (logged) variable \'Position\''
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'save_position',
                    'caption' => 'save position'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'save_track_history',
                    'caption' => 'add track history to the archive of \'Position\''
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'track_history_minutes',
                    'minimum' => 1,
                    'suffix'  => 'Minutes',
                    'caption' => '... period of the track history'
                ],

                [
                    'type'    => 'Label',
                ],

                [
                    'type'    => 'Label',
                    'caption' => 'Health overview',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_activity',
                    'caption' => '... activity monitoring',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_sleep',
                    'caption' => '... sleep monitoring',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_heart_rate',
                    'caption' => '... heart rate',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_respiratory_rate',
                    'caption' => '... respiratory rate',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_bark',
                    'caption' => '... bark behaviour',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_scratch',
                    'caption' => '... scratch behaviour',
                ],
            ],
            'caption' => 'Data import settings',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
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
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval($sec = null)
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

    private function UpdateData()
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
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return;
        }

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $pet_id = $this->ReadPropertyString('pet_id');
        $sendData = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', // an TractiveGpsIO
            'CallerID'   => $this->InstanceID,
            'Function'   => 'GetDeviceData',
            'tracker_id' => $tracker_id,
            'pet_id'     => $pet_id,
        ];
        $this->SendDebug(__FUNCTION__, 'sendData=' . print_r($sendData, true), 0);
        $receiveData = $this->SendDataToParent(json_encode($sendData));
        $this->SendDebug(__FUNCTION__, 'receiveData=' . print_r($receiveData, true), 0);
        $this->decodeDeviceData($receiveData);

        if ($this->ReadPropertyBoolean('save_position') && $this->ReadPropertyBoolean('save_track_history')) {
            $this->UpdateTrackHistory();
        }

        $with_pet_health = false;
        $with_pet_health |= $this->ReadPropertyBoolean('with_activity');
        $with_pet_health |= $this->ReadPropertyBoolean('with_sleep');
        $with_pet_health |= $this->ReadPropertyBoolean('with_heart_rate');
        $with_pet_health |= $this->ReadPropertyBoolean('with_respiratory_rate');
        $with_pet_health |= $this->ReadPropertyBoolean('with_bark');
        $with_pet_health |= $this->ReadPropertyBoolean('with_scratch');
        if ($with_pet_health) {
            $sendData = [
                'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', // an TractiveGpsIO
                'CallerID'   => $this->InstanceID,
                'Function'   => 'GetPetHealth',
                'pet_id'     => $pet_id,
            ];
            $this->SendDebug(__FUNCTION__, 'sendData=' . print_r($sendData, true), 0);
            $receiveData = $this->SendDataToParent(json_encode($sendData));
            $this->SendDebug(__FUNCTION__, 'receiveData=' . print_r($receiveData, true), 0);
            $this->decodePetHealth($receiveData);
        }

        $this->SetUpdateInterval();
        $this->MaintainStatus(IS_ACTIVE);
    }

    private function decodeDeviceData($data)
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
                    $this->SaveValue('BatteryLevel', (int) $battery_level, $is_changed);

                    $temperature_state = $this->GetArrayElem($elem, 'temperature_state', '');
                    $this->SaveValue('TemperatureState', $this->Translate($temperature_state), $is_changed);
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
                    $this->SaveValue('SensorUsed', $this->Translate($sensor_used), $is_changed);
                    break;
                case 'tracker':
                    $state = $this->GetArrayElem($elem, 'state', '');
                    $this->SaveValue('State', $this->Translate($state), $is_changed);
                    break;
                case 'tracker_command_state':
                    $_id = $elem['_id'];
                    if (preg_match('?^[^_]*_(.*)$?', $_id, $r)) {
                        $_id = $r[1];
                    }
                    switch ($_id) {
                        case 'buzzer_control':
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $this->SaveValue('BuzzerActive', (bool) $pending, $is_changed);
                            $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', id=' . $_id . ', elem=' . print_r($elem, true), 0);
                            break;
                        case 'led_control':
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $this->SaveValue('LightActive', (bool) $pending, $is_changed);
                            $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', id=' . $_id . ', elem=' . print_r($elem, true), 0);
                            break;
                        case 'live_tracking':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $this->SaveValue('LiveTrackingActive', (bool) $active, $is_changed);
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

    private function decodePetHealth($data)
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

        $with_activity = $this->ReadPropertyBoolean('with_activity');
        $with_sleep = $this->ReadPropertyBoolean('with_sleep');
        $with_heart_rate = $this->ReadPropertyBoolean('with_heart_rate');
        $with_respiratory_rate = $this->ReadPropertyBoolean('with_respiratory_rate');
        $with_bark = $this->ReadPropertyBoolean('with_bark');
        $with_scratch = $this->ReadPropertyBoolean('with_scratch');

        $fnd = false;
        if ($with_activity) {
            $activity = $this->GetArrayElem($jdata, 'activity', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'activity=' . print_r($activity, true), 0);

                $minutesActive = $this->GetArrayElem($activity, 'minutesActive', 0);
                $this->SetValue('MinutesActive', $minutesActive);
                $this->SendDebug(__FUNCTION__, ' ... MinutesActive (activity.minutesActive)=' . $minutesActive, 0);
            }
        }
        if ($with_sleep) {
            $sleep = $this->GetArrayElem($jdata, 'sleep', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'sleep=' . print_r($sleep, true), 0);

                $minutesDaySleep = $this->GetArrayElem($sleep, 'minutesDaySleep', 0);
                $this->SetValue('MinutesDaySleep', $minutesDaySleep);
                $this->SendDebug(__FUNCTION__, ' ... MinutesDaySleep (sleep.minutesDaySleep)=' . $minutesDaySleep, 0);

                $minutesNightSleep = $this->GetArrayElem($sleep, 'minutesNightSleep', 0);
                $this->SetValue('MinutesNightSleep', $minutesNightSleep);
                $this->SendDebug(__FUNCTION__, ' ... MinutesNightSleep (sleep.minutesNightSleep)=' . $minutesNightSleep, 0);

                $minutesCalm = $this->GetArrayElem($sleep, 'minutesCalm', 0);
                $this->SetValue('MinutesCalm', $minutesCalm);
                $this->SendDebug(__FUNCTION__, ' ... MinutesCalm (sleep.minutesCalm)=' . $minutesActive, 0);
            }
        }
        if ($with_heart_rate) {
            $restingHeartRate = (array) $this->GetArrayElem($jdata, 'restingHeartRate', [], $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'restingHeartRate=' . print_r($restingHeartRate, true), 0);
                // restingHeartRate.status
            }
        }
        if ($with_respiratory_rate) {
            $restingRespiratoryRate = (array) $this->GetArrayElem($jdata, 'restingRespiratoryRate', [], $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'restingRespiratoryRate=' . print_r($restingRespiratoryRate, true), 0);

                // restingRespiratoryRate.status
            }
        }
        if ($with_bark) {
            $bark = (array) $this->GetArrayElem($jdata, 'bark', [], $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'bark=' . print_r($bark, true), 0);
            }
        }
        if ($with_scratch) {
            $scratch = (array) $this->GetArrayElem($jdata, 'scratch', [], $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'scratch=' . print_r($scratch, true), 0);
            }
        }
    }

    private function UpdateTrackHistory()
    {
        $varID = @$this->GetIDForIdent('Position');
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable Position', 0);
            return;
        }

        $archivIDs = (array) IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}'); // Archive Control
        if (count($archivIDs) == 0) {
            $this->SendDebug(__FUNCTION__, 'no archive control instance found', 0);
            return;
        }
        $archiveID = $archivIDs[0];

        if (AC_GetLoggingStatus($archiveID, $varID) == false) {
            $this->SendDebug(__FUNCTION__, 'logging for variable Position is not enabled => skip track history', 0);
            return;
        }

        $now = time();
        $minutes = $this->ReadPropertyInteger('track_history_minutes');
        if ($minutes < 1) {
            $minutes = 1;
        }
        $time_from = $now - ($minutes * 60);
        $lastTs = $this->ReadAttributeInteger('TrackHistoryLast');
        if ($lastTs > $time_from) {
            $time_from = $lastTs;
        }

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $sendData = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', // an TractiveGpsIO
            'CallerID'   => $this->InstanceID,
            'Function'   => 'GetTrackerHistory',
            'tracker_id' => $tracker_id,
            'time_from'  => $time_from,
            'time_to'    => $now,
        ];
        $this->SendDebug(__FUNCTION__, 'sendData=' . print_r($sendData, true), 0);
        $receiveData = $this->SendDataToParent(json_encode($sendData));
        $this->SendDebug(__FUNCTION__, 'receiveData=' . print_r($receiveData, true), 0);
        if ($receiveData == false) {
            return;
        }

        $jdata = json_decode($receiveData, true);
        if (!is_array($jdata)) {
            $this->SendDebug(__FUNCTION__, 'malformed data', 0);
            return;
        }

        $values = [];
        $maxTs = $lastTs;
        foreach ($jdata as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            foreach ($segment as $point) {
                if (!is_array($point)) {
                    continue;
                }
                $time = (int) $this->GetArrayElem($point, 'time', 0);
                if ($time <= 0 || $time <= $lastTs) {
                    continue;
                }

                $lat = $this->GetArrayElem($point, 'latlong.0', '');
                $lng = $this->GetArrayElem($point, 'latlong.1', '');
                if ($lat === '' || $lng === '') {
                    continue;
                }

                $altitude = $this->GetArrayElem($point, 'altitude', '');
                if ($altitude === '') {
                    $altitude = $this->GetArrayElem($point, 'alt', 0);
                }

                $pos = json_encode([
                    'latitude'  => (float) $this->format_float($lat, 6),
                    'longitude' => (float) $this->format_float($lng, 6),
                    'altitude'  => (float) $altitude,
                ]);
                $values[] = [
                    'TimeStamp' => $time,
                    'Value'     => $pos,
                ];
                if ($time > $maxTs) {
                    $maxTs = $time;
                }
            }
        }

        if (count($values) == 0) {
            $this->SendDebug(__FUNCTION__, 'no new track positions to add', 0);
            return;
        }

        usort($values, function ($a, $b) {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });

        // adding a value with an already logged timestamp causes an error
        $existingTs = [];
        $loggedValues = @AC_GetLoggedValues($archiveID, $varID, $values[0]['TimeStamp'], $maxTs, 0);
        if (is_array($loggedValues)) {
            foreach ($loggedValues as $loggedValue) {
                $existingTs[$loggedValue['TimeStamp']] = true;
            }
        }
        $n = count($values);
        $values = array_values(array_filter($values, function ($value) use (&$existingTs) {
            if (isset($existingTs[$value['TimeStamp']])) {
                return false;
            }
            $existingTs[$value['TimeStamp']] = true;
            return true;
        }));
        if ($n != count($values)) {
            $this->SendDebug(__FUNCTION__, 'skipped ' . ($n - count($values)) . ' position(s) with duplicate timestamp', 0);
        }

        if (count($values) > 0) {
            $this->SendDebug(__FUNCTION__, 'add ' . count($values) . ' position(s) to archive of variable Position', 0);
            AC_AddLoggedValues($archiveID, $varID, $values);
            AC_ReAggregateVariable($archiveID, $varID);
        }

        $this->WriteAttributeInteger('TrackHistoryLast', $maxTs);
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
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return false;
        }

        $tracker_id = $this->ReadPropertyString('tracker_id');
        $sendData = [
            'DataID'     => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}', // an TractiveGpsIO
            'CallerID'   => $this->InstanceID,
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

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateData':
                $this->UpdateData();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
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
