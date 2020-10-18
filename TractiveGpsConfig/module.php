<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class TractiveGpsConfig extends IPSModule
{
    use TractiveGpsCommonLib;
    use TractiveGpsLocalLib;
}
