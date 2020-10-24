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

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        $tracker_id = $this->ReadPropertyString('tracker_id');
        $model_number = $this->ReadPropertyString('model_number');

        parent::ApplyChanges();

        $summary = $model_number . ' (#' . $tracker_id . ')';
        $this->SetSummary($summary);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
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
            'caption' => 'Tracker-ID'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'model_number',
            'caption' => 'Model'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'pet_id',
            'caption' => 'Pet-ID'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        return $formActions;
    }
    public function Send()
    {
        $this->SendDataToParent(json_encode(['DataID' => '{94B20D14-415B-1E19-8EA4-839F948B6CBE}']));
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
    }

    private function decodeUpdateData($data)
    {
        $jdata = json_decode($data, true);
        /*
            TestAccess | bulk=Array
            (
                [0] => Array
                    (
                        [time] => 1603466887
                        [hw_status] =>
                        [battery_level] => 91
                        [temperature_state] => NORMAL
                        [clip_mounted_state] =>
                        [_id] => YBPWTKPP
                        [_type] => device_hw_report
                        [_version] => 68c8c1600
                        [report_id] => 5f92f68713204b00061c8c86
                    )

                [1] => Array
                    (
                        [time] => 1603466830
                        [time_rcvd] => 1603466886
                        [sensor_used] => GPS
                        [pos_status] => Array
                            (
                            )

                        [latlong] => Array
                            (
                                [0] => 51,4610366
                                [1] => 7,15845
                            )

                        [speed] => 1,4
                        [course] => 4
                        [pos_uncertainty] => 39
                        [_id] => YBPWTKPP
                        [_type] => device_pos_report
                        [_version] => 96c8c1600
                        [altitude] => 92
                        [report_id] => 5f92f68613204b00061c8c69
                        [nearby_user_id] =>
                    )

                [2] => Array
                    (
                        [_id] => YBPWTKPP_buzzer_control
                        [_version] => 815939b76
                        [_type] => tracker_command_state
                        [active] =>
                        [started_at] =>
                        [timeout] => 300
                        [remaining] => 0
                        [pending] =>
                    )

                [3] => Array
                    (
                        [_id] => YBPWTKPP_led_control
                        [_version] => 815939b76
                        [_type] => tracker_command_state
                        [active] =>
                        [started_at] =>
                        [timeout] => 300
                        [remaining] => 0
                        [pending] =>
                    )

                [4] => Array
                    (
                        [_id] => YBPWTKPP_live_tracking
                        [_version] => 2330347b9
                        [_type] => tracker_command_state
                        [active] =>
                        [started_at] =>
                        [timeout] => 300
                        [remaining] => 0
                        [pending] =>
                        [reconnecting] =>
                    )

                [5] => Array
                    (
                        [_id] => YBPWTKPP_pos_request
                        [_version] => 90af4ab15
                        [_type] => tracker_command_state
                        [active] =>
                        [started_at] =>
                        [timeout] => 30
                        [remaining] => 0
                        [pending] =>
                    )

            )

         */
        foreach ($jdata as $elem) {
            $_type = $elem['_type'];
            switch ($_type) {
                case 'device_hw_report':
                    $time = $this->GetArrayElem($elem, 'time', '');
                    $hw_status = $this->GetArrayElem($elem, 'hw_status', '');
                    $battery_level = $this->GetArrayElem($elem, 'battery_level', '');
                    $temperature_state = $this->GetArrayElem($elem, 'temperature_state', '');
                    $clip_mounted_state = $this->GetArrayElem($elem, 'clip_mounted_state', '');
                    $this->SendDebug(__FUNCTION__, $_type . ' => time=' . $time . ', hw_status=' . $hw_status . ', battery_level=' . $battery_level . ', temperature_state=' . $temperature_state . ', clip_mounted_state=' . $clip_mounted_state, 0);
                    break;
                case 'device_pos_report':
                    $time = $this->GetArrayElem($elem, 'time', '');
                    $sensor_used = $this->GetArrayElem($elem, 'sensor_used', '');
                    $lat = $this->GetArrayElem($elem, 'latlong.0', '');
                    $lng = $this->GetArrayElem($elem, 'latlong.1', '');
                    $speed = $this->GetArrayElem($elem, 'speed', '');
                    $altitude = $this->GetArrayElem($elem, 'altitude', '');
                    $pos_uncertainty = $this->GetArrayElem($elem, 'pos_uncertainty', '');
                    $this->SendDebug(__FUNCTION__, $_type . ' => time=' . $time . ', sensor_used=' . $sensor_used . ', lat=' . $lat . ', lng=' . $lng . ', speed=' . $speed . ', altitude=' . $altitude . ', pos_uncertainty=' . $pos_uncertainty, 0);
                    break;
                case 'tracker_command_state':
                    $_id = $elem['_id'];
                    if (preg_match('?^[^_]*_(.*)$?', $_id, $r)) {
                        $_id = $r[1];
                    }
                    switch ($_id) {
                        case 'buzzer_control':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $started_at = $this->GetArrayElem($elem, 'started_at', '');
                            $remaining = $this->GetArrayElem($elem, 'remaining', '');
                            $this->SendDebug(__FUNCTION__, $_type . '::' . $_id . ' => active=' . $active . ', pending=' . $pending . ', started_at=' . $started_at . ', remaining=' . $remaining, 0);
                            break;
                        case 'led_control':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $started_at = $this->GetArrayElem($elem, 'started_at', '');
                            $remaining = $this->GetArrayElem($elem, 'remaining', '');
                            $this->SendDebug(__FUNCTION__, $_type . '::' . $_id . ' => active=' . $active . ', pending=' . $pending . ', started_at=' . $started_at . ', remaining=' . $remaining, 0);
                            break;
                        case 'live_tracking':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $started_at = $this->GetArrayElem($elem, 'started_at', '');
                            $remaining = $this->GetArrayElem($elem, 'remaining', '');
                            $this->SendDebug(__FUNCTION__, $_type . '::' . $_id . ' => active=' . $active . ', pending=' . $pending . ', started_at=' . $started_at . ', remaining=' . $remaining, 0);
                            break;
                        case 'pos_request':
                            $active = $this->GetArrayElem($elem, 'active', '');
                            $pending = $this->GetArrayElem($elem, 'pending', '');
                            $started_at = $this->GetArrayElem($elem, 'started_at', '');
                            $remaining = $this->GetArrayElem($elem, 'remaining', '');
                            $this->SendDebug(__FUNCTION__, $_type . '::' . $_id . ' => active=' . $active . ', pending=' . $pending . ', started_at=' . $started_at . ', remaining=' . $remaining, 0);
                            break;
                    }
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'type=' . $_type . ', elem=' . print_r($elem, true), 0);
                    break;
            }
        }
    }
}
