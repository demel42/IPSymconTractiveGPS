<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TractiveGpsIO extends IPSModule
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

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterAttributeString('UpdateInfo', '');
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $user = $this->ReadPropertyString('user');
        if ($user == '') {
            $this->SendDebug(__FUNCTION__, '"user" is needed', 0);
            $r[] = $this->Translate('Username must be specified');
        }

        $password = $this->ReadPropertyString('password');
        if ($password == '') {
            $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
            $r[] = $this->Translate('Password must be specified');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

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

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Tractive GPS I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
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
            'type'    => 'PasswordTextBox',
            'name'    => 'password',
            'caption' => 'Password'
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
            'caption' => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccess", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Clear Token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
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
                case 'GetUpdateData':
                    $tracker_id = $jdata['tracker_id'];
                    $pet_id = $jdata['pet_id'];
                    $this->SendDebug(__FUNCTION__, 'function=' . $jdata['Function'] . ', tracker_id=' . $tracker_id . ', pet_id=' . $pet_id, 0);
                    $r = $this->GetUpdateData($tracker_id, $pet_id, $ret);
                    break;
                case 'SwitchBuzzer':
                    $tracker_id = $jdata['tracker_id'];
                    $mode = (bool) $jdata['payload']['mode'] ? 'on' : 'off';
                    $cmd = 'buzzer_control/' . $mode;
                    $this->SendDebug(__FUNCTION__, 'function=' . $jdata['Function'] . ', tracker_id=' . $tracker_id . ', cmd=' . $cmd, 0);
                    $r = $this->ExecuteTrackerCommand($tracker_id, $cmd, $ret);
                    break;
                case 'SwitchLight':
                    $tracker_id = $jdata['tracker_id'];
                    $mode = (bool) $jdata['payload']['mode'] ? 'on' : 'off';
                    $cmd = 'led_control/' . $mode;
                    $this->SendDebug(__FUNCTION__, 'function=' . $jdata['Function'] . ', tracker_id=' . $tracker_id . ', cmd=' . $cmd, 0);
                    $r = $this->ExecuteTrackerCommand($tracker_id, $cmd, $ret);
                    break;
                case 'SwitchLiveTracking':
                    $tracker_id = $jdata['tracker_id'];
                    $mode = (bool) $jdata['payload']['mode'] ? 'on' : 'off';
                    $cmd = 'live_tracking/' . $mode;
                    $this->SendDebug(__FUNCTION__, 'function=' . $jdata['Function'] . ', tracker_id=' . $tracker_id . ', cmd=' . $cmd, 0);
                    $r = $this->ExecuteTrackerCommand($tracker_id, $cmd, $ret);
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
                $this->MaintainStatus($statuscode);
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
            $this->MaintainStatus(IS_ACTIVE);
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
        } elseif ($httpcode == 403) {
            $jdata = json_decode($cdata, true);
            $statuscode = self::$IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (forbidden)';
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

    private function ExecuteTrackerCommand($tracker_id, $cmd, &$data)
    {
        $func = '/tracker/' . $tracker_id . '/command/' . $cmd;
        return $this->do_ApiCall($func, false, $data);
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
                '_type' => 'tracker',
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
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    private function TestAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $this->PopupMessage($this->GetStatusText());
            return;
        }

        $txt = '';
        $r = $this->GetTrackers($data);
        if ($r == false) {
            $txt .= $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;
            $trackers = json_decode($data, true);
            $n_trackers = count($trackers);
            $txt .= $n_trackers . ' ' . $this->Translate('registered devices found');
        }
        $this->SendDebug(__FUNCTION__, 'txt=' . $txt, 0);
        $this->PopupMessage($txt);
    }

    private function ClearToken()
    {
        $access_token = $this->GetAccessToken();
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->SetBuffer('AccessToken', '');
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'TestAccess':
                $this->TestAccess();
                break;
            case 'ClearToken':
                $this->ClearToken();
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
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
