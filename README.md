# IPSymconTractiveGps

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Integration der GPS-Tracker von ([Tractive](https://tractive.com/de)). Abruf der verfügbaren Informationen wie Ladezustand, Position etc und auslösen der Funktionen sie LiveTracking etc.

## 2. Voraussetzungen

 - IP-Symcon ab Version 6.0
 - ein Tractive GPS-Ortungsgerät
 - den Benutzer-Account, der bei der Anmeldung der Geräte bei Tractive erzeugt wird (https://my.tractive.com)

## 3. Installation

### a. Laden des Moduls

**Installieren über den Module-Store**

Die Webconsole von IP-Symcon mit `http://\<IP-Symcon IP\>:3777/console/` öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore klicken

Im Suchfeld nun Tractive eingeben, das Modul auswählen und auf Installieren drücken.

**Installieren über die Modules-Instanz**

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconTractiveGps.git`

und mit _OK_ bestätigen. Ggfs. auf anderen Branch wechseln (Modul-Eintrag editieren, _Zweig_ auswählen).

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

#### TractiveGpsIO

In dem Konfigurationsdialog die Zugangsdaten eintragen.

#### TractiveGpsConfig

Dann unter _Konfigurator Instanzen_ analog den Konfigurator _Tractive GPS Konfigurator_ hinzufügen.

Hier werden alle Tractive-Ortungsgeräte, die mit dem, in der I/O-Instanz angegebenen, Konto verknüpft sind, angeboten; aus denen wählt man ein Produkt aus.

Mit den Schaltflächen _Erstellen_ bzw. _Alle erstellen_ werden das/die gewählte Produkt anlegt.

Der Aufruf des Konfigurators kann jederzeit wiederholt werden.

Die Produkte werden aufgrund der _Tracker-ID_ identifiziert.

Zu den Geräte-Instanzen werden im Rahmen der Konfiguration Variablen angelegt. Zusätzlich kann man in dem Modultyp-spezifischen Konfigurationsdialog weitere Variablen aktivieren.

Die Instanzen können dann in gewohnter Weise im Objektbaum frei positioniert werden.

## 4. Funktionsreferenz

### TractiveGpsIO

`TractiveGps_UpdateData(int $InstanzID)`
ruft die Daten des Tractive-Ortungsgeräte ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

## 5. Konfiguration

### TractiveGpsIO

#### Properties

| Eigenschaft               | Typ     | Standardwert | Beschreibung |
| :------------------------ | :------ | :----------- | :----------- |
| Zugangsdaten              | string  |              | Benutzername und Passwort von https://my.tractive.com |

#### Schaltflächen

| Bezeichnung    | Beschreibung |
| :------------- | :----------- |
| Zugang prüfen  | Prüfung der Zugangsdaten |

### TractiveGpsConfig

#### Properties

| Eigenschaft               | Typ     | Standardwert | Beschreibung |
| :------------------------ | :------ | :----------- | :----------- |
| Kategorie                 | integer | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden _[1]_ |
| Produkte                  | list    |              | Liste der verfügbaren Produkte |

_[1]_: nur bis IPS-Version 7 vorhanden, danach ist eine Einstellmöglichkeit Bestandteil des Standard-Konfigurators

### TractiveGpsDevice

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft              | Typ     | Standardwert | Beschreibung |
| :----------------------- | :------ | :----------- | :----------- |
| Tracker-ID               | string  |              | von TractiveGpsConfig vorgegeben |
| Modell                   | string  |              | von TractiveGpsConfig vorgegeben |
| Haustier-ID              | string  |              | von TractiveGpsConfig vorgegeben |
|                          |         |              | |
| Position speichern       | boolean | nein         | Positionsangaben in einer Variable speichern |
|                          |         |              | |
| Aktualisiere Daten ...   | integer | 5            | Aktualisierungsintervall, Angabe in Minuten |

in _Position_ wird die akuelle Positon gespeichert; es werden Longitude und Latitude sowie Altitude als json-encodeded String abgelegt. Wenn die Variable protokolliert wird, können damit längerfristig die Weg des Trackers dargestellt werden.
Beispiel-Script siehe [docs/Position2GoogelMaps.php](docs/Position2GoogelMaps.php).

#### Schaltflächen

| Bezeichnung               | Beschreibung |
| :------------------------ | :----------- |
| Aktualisiere Daten        | führt eine sofortige Aktualisierung durch |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
TractiveGps.Switch

* Integer<br>
TractiveGps.BatteryLevel

* Float<br>
TractiveGps.Altitude,
TractiveGps.Course,
TractiveGps.Location,
TractiveGps.Speed,
TractiveGps.Uncertainty

## 6. Anhang

GUIDs
- Modul: `{85B63C47-3DA6-7B2F-6472-537D9CA24360}`
- Instanzen:
  - TractiveGpsIO: `{0661D1B3-4375-1B37-7D59-1592111C8F8D}`
  - TractiveGpsConfig: `{3A82CAAC-44E5-E310-4F90-AD375E2B5627}`
  - TractiveGpsDevice: `{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}`
- Nachrichten:
  - `{91C54CDA-594C-1D6F-6BD8-57545408677F}`: an TractiveGpsIO
  - `{94B20D14-415B-1E19-8EA4-839F948B6CBE}`: an TractiveGpsConfig, TractiveGpsDevice

## 7. Versions-Historie

- 1.12 @ 25.01.2024 14:41
  - Neu: Schalter, um Daten zu API-Aufrufen zu sammeln
    Die API-Aufruf-Daten stehen nun in einem Medienobjekt zur Verfügung
  - update submodule CommonStubs

- 1.11 @ 10.12.2023 15:09
  - Neu: ab IPS-Version 7 ist im Konfigurator die Angabe einer Import-Kategorie integriert, daher entfällt die bisher vorhandene separate Einstellmöglichkeit

- 1.10 @ 03.11.2023 11:06
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - Fix: die Statistik der ApiCalls wird nicht mehr nach uri sondern nur noch host+cmd differenziert
  - update submodule CommonStubs

- 1.9 @ 05.07.2023 11:56
  - Vorbereitung auf IPS 7 / PHP 8.2
  - Neu: Schalter, um die Meldung eines inaktiven Gateway zu steuern
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 1.8.1 @ 21.12.2022 09:25
  - Fix: "Zugang prüfen" funktioniert wieder
  - update submodule CommonStubs

- 1.8 @ 30.11.2022 17:26
  - Neu: Führen einer Statistik der API-Calls im IO-Modul, Anzeige als Popup im Experten-Bereich
  - Neu: Verwendung der Option 'discoveryInterval' im Konfigurator (ab 6.3) zur Reduzierung der API-Calls: nur noch ein Discovery/Tag
  - update submodule CommonStubs

- 1.7 @ 19.10.2022 09:26
  - Fix: MessageSink() angepasst, um Warnungen aufgrund zu langer Laufzeit von KR_READY zu vermeiden
  - update submodule CommonStubs

- 1.6.3 @ 12.10.2022 14:44
  - Konfigurator betrachtet nun nur noch Geräte, die entweder noch nicht angelegt wurden oder mit dem gleichen I/O verbunden sind
  - update submodule CommonStubs

- 1.6.2 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 1.6.1 @ 16.08.2022 10:10
  - update submodule CommonStubs
    Fix: in den Konfiguratoren war es nicht möglich, eine Instanz direkt unter dem Wurzelverzeichnis "IP-Symcon" zu erzeugen

- 1.6 @ 08.07.2022 10:12
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun private und ggfs nur noch via IPS_RequestAction() erreichbar
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert
  - Fix: Übersetzung ergänzt
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 1.5.4 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 1.5.3 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - SetLocation() -> GetConfiguratorLocation()
  - weitere Absicherung ungültiger ID's

- 1.5.2 @ 29.04.2022 09:48
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)

- 1.5.1 @ 26.04.2022 12:16
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE
  - IPS-Version ist nun minimal 6.0

- 1.5 @ 25.04.2022 16:39
  - Implememtierung einer Update-Logik
  - Übersetzung vervollständigt
  - Aktualisierung von submodule CommonStubs
  - diverse interne Änderungen

- 1.4.2 @ 16.04.2022 12:19
  - potentieller Namenskonflikt behoben (trait CommonStubs)
  - Aktualisierung von submodule CommonStubs

- 1.4.1 @ 12.04.2022 18:00
  - Fix zu 1.4: fehlende Prüfung auf ungültige Konfiguration

- 1.4 @ 11.04.2022 12:22
  - Konfigurator zeigt nun auch Instanzen an, die nicht mehr zu den vorhandenen Geräten passen
  - optionale Speicherung der Position-Angaben in einer Variablen (vereinfacht das Zeichnen von Karten)
  - Ausgabe der Instanz-Timer unter "Referenzen"

- 1.3 @ 16.02.2022 10:57
  - Anpassungen an IPS 6.2 (Prüfung auf ungültige ID's)

- 1.2 @ 14.07.2021 18:27
  - Schalter "Instanz ist deaktiviert" umbenannt in "Instanz deaktivieren"

- 1.1 @ 28.12.2020 11:38
  - IO-Instanz: Funktion zum Löschen des AccessToken
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt

- 1.0 @ 19.11.2020 15:01
  - Initiale Version
