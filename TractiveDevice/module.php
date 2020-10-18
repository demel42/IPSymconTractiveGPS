<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveDevice extends IPSModule
{
    use TractiveCommonLib;
    use TractiveLocalLib;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{0661D1B3-4375-1B37-7D59-1592111C8F8D}');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
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
}
