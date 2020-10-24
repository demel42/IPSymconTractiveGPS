<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveGpsIO extends IPSModule
{
    use TractiveGpsCommonLib;
    use TractiveGpsLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterTimer('UpdateData', 0, 'TractiveGps_UpdateData(' . $this->InstanceID . ');');
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

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user == '' || $password == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $this->SetStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetTimerInterval('UpdateData', 1000);
        }
    }

    private function GetFormElements()
    {
        $formElements = [];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Instance is disabled'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Account from https://my.tractive.com'
        ];
        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'user',
            'caption' => 'Username'
        ];
        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'password',
            'caption' => 'Password'
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
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'TractiveGps_TestAccess($id);'
        ];

        return $formActions;
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
        $this->SendDebug(__FUNCTION__, 'min=' . $min . ', msec=' . $msec, 0);
    }

    public function ForwardData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetIndex':
                    $r = $this->GetIndex($ret);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
                }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    public function UpdateData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            if ($this->GetStatus() == self::$IS_NOLOGIN) {
                $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => pause', 0);
                $this->SetTimerInterval('UpdateData', 0);
            } else {
                $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            }
            return;
        }

        $data = [];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $sdata = [
            'DataID' => '{91C54CDA-594C-1D6F-6BD8-57545408677F}',
            'Buffer' => $data,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToChildren(' . print_r($sdata, true) . ')', 0);
        $this->SendDataToChildren(json_encode($sdata));

        $this->SetStatus(IS_ACTIVE);
        $this->SetUpdateInterval();
    }

    private function GetAccessToken()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $token = $this->GetBuffer('AccessToken');
        $jtoken = json_decode($token, true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
        if ($expiration < time()) {
            $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
            $access_token = '';
        }
        if ($access_token == '') {
            $header = [
                'content-type: application/json;charset=UTF-8',
                'accept: application/json, text/plain, */*',
                'x-tractive-client: 5728aa1fc9077f7c32000186',
            ];
            $postdata = [
                'platform_email' => $user,
                'platform_token' => $password,
                'grant_type'     => 'tractive',
            ];

            $data = '';
            $err = '';
            $statuscode = $this->do_HttpRequest('/auth/token', $header, $postdata, 'POST', $data);
            if ($statuscode == 0) {
                $params = json_decode($data, true);
                $this->SendDebug(__FUNCTION__, 'params=' . print_r($params, true), 0);
                if ($params['access_token'] == '') {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = "no 'access_token' in response";
                }
            }

            if ($statuscode) {
                $this->LogMessage('statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
                $this->SendDebug(__FUNCTION__, $err, 0);
                $this->SetStatus($statuscode);
                return false;
            }

            $access_token = $params['access_token'];
            $user_id = $params['user_id'];
            $client_id = $params['client_id'];
            $expires_at = $params['expires_at'];
            $expiration = $expires_at - 60;
            $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
            $jtoken = [
                'user_id'      => $user_id,
                'client_id'    => $client_id,
                'access_token' => $access_token,
                'expiration'   => $expiration,
            ];
            $token = json_encode($jtoken);
            $this->SetBuffer('AccessToken', $token);
            $this->SetStatus(IS_ACTIVE);
        }

        return $token;
    }

    private function do_HttpRequest($func, $header, $postdata, $mode, &$data)
    {
        $url = 'https://graph.tractive.com/3' . $func;

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ': url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
        if ($postdata != false) {
            $this->SendDebug(__FUNCTION__, '    postdata=' . print_r($postdata, true), 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';

        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode == 200 || $httpcode == 204) {
            $data = $cdata;
        } elseif ($httpcode == 401) {
            $statuscode = self::$IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (unauthorized)';
        } elseif ($httpcode >= 500 && $httpcode <= 599) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got http-code ' . $httpcode . ' (server error)';
        } else {
            $statuscode = self::$IS_HTTPERROR;
            $err = 'got http-code ' . $httpcode;
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
        }

        return $statuscode;
    }

    private function GetTracker(&$data)
    {
        $token = $this->GetAccessToken();
        if ($token == false) {
            return false;
        }

        $jtoken = json_decode($token, true);
        $user_id = $jtoken['user_id'];

        $func = '/user/' . $user_id . '/trackers';
        return $this->do_ApiCall($func, false, $data);
    }

    private function GetData4User($func, &$data)
    {
        $token = $this->GetAccessToken();
        if ($token == false) {
            return false;
        }

        $jtoken = json_decode($token, true);
        $user_id = $jtoken['user_id'];

        $func = '/user/' . $user_id . $func;
        return $this->do_ApiCall($func, false, $data);
    }

    private function GetUserData()
    {
        return $this->GetData4User('/', $data);
    }

    private function GetData4Pet($func, $pet_id, &$data)
    {
        $func = '/pet/' . $pet_id . $func;
        return $this->do_ApiCall($func, false, $data);
    }

    private function GetPetData($pet_id, &$data)
    {
        return $this->GetData4Pet('/', $pet_id, $data);
    }

    private function GetData4Tracker($func, $tracker_id, &$data)
    {
        $func = '/tracker/' . $tracker_id . $func;
        return $this->do_ApiCall($func, false, $data);
    }

    private function GetTrackerData($tracker_id, &$data)
    {
        return $this->GetData4Tracker('/', $tracker_id, $data);
    }

    private function GetDataBulk($postdata, &$data)
    {
        $func = '/bulk?schema=flat';
        return $this->do_ApiCall($func, $postdata, $data);
    }

    private function GetUpdateData($tracker_id, $pet_id, &$data)
    {
        $postdata = [
            [
                '_type' => 'device_hw_report',
                '_id'   => $tracker_id
            ],
            [
                '_type' => 'device_pos_report',
                '_id'   => $tracker_id
            ],
            [
                '_type' => 'tracker_command_state',
                '_id'   => $tracker_id . '_buzzer_control',
            ],
            [
                '_type' => 'tracker_command_state',
                '_id'   => $tracker_id . '_led_control',
            ],
            [
                '_type' => 'tracker_command_state',
                '_id'   => $tracker_id . '_live_tracking',
            ],
            [
                '_type' => 'tracker_command_state',
                '_id'   => $tracker_id . '_pos_request',
            ],
        ];
        return $this->GetDataBulk($postdata, $data);
    }

    private function GetGeofences($tracker_id, &$data)
    {
        $r = $this->GetData4Tracker('/geofences', $tracker_id, $data);
        if ($r == false) {
            return $r;
        }
        $jdata = json_decode($r, true);

        $postdata = [
            [
                '_type' => $r['_type'],
                '_id'   => $r['_id']
            ]
        ];
        return $this->GetDataBulk($postdata, $data);
    }

    private function GetPets(&$data)
    {
        return $this->GetData4User('/trackable_objects', $data);
    }

    private function GetTrackers(&$data)
    {
        return $this->GetData4User('/trackers', $data);
    }

    private function GetIndex(&$data)
    {
        $r = $this->GetTrackers($data);
        if ($r == false) {
            return false;
        }
        $trackers = json_decode($data, true);
        $r = $this->GetPets($data);
        if ($r == false) {
            return false;
        }
        $pets = json_decode($data, true);

        $elems = [];
        foreach ($trackers as $tracker) {
            $tracker_id = $tracker['_id'];
            foreach ($pets as $pet) {
                $pet_id = $pet['_id'];
                $r = $this->GetPetData($pet_id, $data);
                if ($r == false) {
                    return false;
                }
                $_pet = json_decode($data, true);
                if ($_pet['device_id'] == $tracker_id) {
                    $r = $this->GetTrackerData($tracker_id, $data);
                    if ($r == false) {
                        return false;
                    }
                    $_tracker = json_decode($data, true);
                    $elem = [];
                    $elem['tracker_id'] = $tracker_id;
                    $elem['model_number'] = $this->GetArrayElem($_tracker, 'model_number', '');
                    $elem['pet_id'] = $pet_id;
                    $elem['pet_name'] = $this->GetArrayElem($_pet, 'details.name', '');
                    $elems[] = $elem;
                }
            }
        }
        $data = json_encode($elems);
        return true;
    }

    private function do_ApiCall($func, $postdata, &$data)
    {
        $token = $this->GetAccessToken();
        if ($token == false) {
            return false;
        }

        $jtoken = json_decode($token, true);
        $user_id = $jtoken['user_id'];
        $access_token = $jtoken['access_token'];

        $header = [
            'content-type: application/json;charset=UTF-8',
            'accept: application/json, text/plain, */*',
            'authorization: Bearer ' . $access_token,
            'x-tractive-client: 5728aa1fc9077f7c32000186',
            'x-tractive-user: ' . $user_id,
        ];

        $mode = $postdata == false ? 'GET' : 'POST';
        $statuscode = $this->do_HttpRequest($func, $header, $postdata, $mode, $data);
        if ($statuscode != 0) {
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    public function TestAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $txt = '';
        $r = $this->GetTrackers($data);
        if ($r == false) {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;
            $trackers = json_decode($data, true);
            $n_trackers = count($trackers);
            $txt .= $n_trackers . ' ' . $this->Translate('registered devices found');
        }
        echo $txt;
    }
}
