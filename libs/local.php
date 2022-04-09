<?php

declare(strict_types=1);

trait TractiveGpsLocalLib
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;
    public static $IS_UNAUTHORIZED = IS_EBASE + 2;
    public static $IS_SERVERERROR = IS_EBASE + 3;
    public static $IS_HTTPERROR = IS_EBASE + 4;
    public static $IS_INVALIDDATA = IS_EBASE + 5;
    public static $IS_NODEVICE = IS_EBASE + 6;
    public static $IS_DEVICEMISSІNG = IS_EBASE + 7;

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function GetFormStatus()
    {
        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid config)'];
        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NODEVICE, 'icon' => 'error', 'caption' => 'Instance is inactive (no device)'];
        $formStatus[] = ['code' => self::$IS_DEVICEMISSІNG, 'icon' => 'error', 'caption' => 'Instance is inactive (device missing)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1];
        $this->CreateVarProfile('TractiveGps.Switch', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('TractiveGps.BatteryLevel', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '', [], $reInstall);

        $this->CreateVarProfile('TractiveGps.Altitude', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Speed', VARIABLETYPE_FLOAT, ' km/h', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Course', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Uncertainty', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '', [], $reInstall);
    }
}
