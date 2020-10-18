<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveIO extends IPSModule
{
    use NetatmoAircareCommonLib;
    use NetatmoAircareLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('User', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterPropertyInteger('UpdateDataInterval', '5');

        $this->RegisterTimer('UpdateData', 0, 'NetatmoAircare_UpdateData(' . $this->InstanceID . ');');
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

        $user = $this->ReadPropertyString('User');
        $password = $this->ReadPropertyString('Password');
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
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');

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
            'name'    => 'User',
            'caption' => 'Username'
        ];
        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'Password',
            'caption' => 'Password'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Update data every X minutes'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'UpdateDataInterval',
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
            'onClick' => 'NetatmoAircare_UpdateData($id);'
        ];

        return $formActions;
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('UpdateDataInterval');
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
                case 'LastData':
                    $ret = $this->GetMultiBuffer('LastData');
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
    }
}
