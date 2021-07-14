# IPSymconTractiveGps

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
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

 - IP-Symcon ab Version 5.3
 - ein Tractive GPS-Ortungsgerät
 - den Benutzer-Account, der bei der Anmeldung der Geräte bei Tractive erzeugt wird (https://my.tractive.com)

## 3. Installation

### a. Laden des Moduls

**Installieren über den Module-Store**

Die Webconsole von IP-Symcon mit http://<IP-Symcon IP>:3777/console/ öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore (IP-Symcon > 5.1) klicken

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

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Zugangsdaten              | string   |              | Benutzername und Passwort von https://my.tractive.com |

#### Schaltflächen

| Bezeichnung    | Beschreibung |
| :------------- | :----------- |
| Zugang prüfen  | Prüfung der Zugangsdaten |

### TractiveGpsConfig

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Kategorie                 | integer  | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden |
| Produkte                  | list     |              | Liste der verfügbaren Produkte |

### TractiveGpsDevice

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft              | Typ      | Standardwert | Beschreibung |
| :----------------------- | :--------| :----------- | :----------- |
| Tracker-ID               | string   |              | von TractiveGpsConfig vorgegeben |
| Modell                   | string   |              | von TractiveGpsConfig vorgegeben |
| Haustier-ID              | string   |              | von TractiveGpsConfig vorgegeben |
|                          |          |              | |
| Aktualisiere Daten ...   | integer  | 5            | Aktualisierungsintervall, Angabe in Minuten |

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
TractiveGps.Altitude, TractiveGps.Course, TractiveGps.Location, TractiveGps.Speed, TractiveGps.Uncertainty

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

- 1.2 @ 14.07.2021 18:27
  - Schalter "Instanz ist deaktiviert" umbenannt in "Instanz deaktivieren"

- 1.1 @ 28.12.2020 11:38
  - IO-Instanz: Funktion zum Löschen des AccessToken
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt

- 1.0 @ 19.11.2020 15:01
  - Initiale Version
