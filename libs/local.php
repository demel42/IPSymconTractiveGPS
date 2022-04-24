<?php

declare(strict_types=1);

trait TractiveGpsLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NODEVICE = IS_EBASE + 14;
    public static $IS_DEVICEMISSІNG = IS_EBASE + 15;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NODEVICE, 'icon' => 'error', 'caption' => 'Instance is inactive (no device)'];
        $formStatus[] = ['code' => self::$IS_DEVICEMISSІNG, 'icon' => 'error', 'caption' => 'Instance is inactive (device missing)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

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

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('TractiveGps.Switch', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('TractiveGps.BatteryLevel', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '', [], $reInstall);

        $this->CreateVarProfile('TractiveGps.Altitude', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Speed', VARIABLETYPE_FLOAT, ' km/h', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Course', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '', [], $reInstall);
        $this->CreateVarProfile('TractiveGps.Uncertainty', VARIABLETYPE_FLOAT, ' m', 0, 0, 0, 0, '', [], $reInstall);
    }
}
