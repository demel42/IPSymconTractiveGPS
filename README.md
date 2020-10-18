# IPSymconTractive

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


## 2. Voraussetzungen

 - IP-Symcon ab Version 5.3
 - ein Tractive GPS-Tracker
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

`https://github.com/demel42/IPSymconTractive.git`

und mit _OK_ bestätigen. Ggfs. auf anderen Branch wechseln (Modul-Eintrag editieren, _Zweig_ auswählen).

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

#### TractiveIO

In dem Konfigurationsdialog die Zugangsdaten eintragen.

#### TractiveConfig

Dann unter _Konfigurator Instanzen_ analog den Konfigurator _Tractive Konfigurator_ hinzufügen.

Hier werden alle Tractive-Produkte, die mit dem, in der I/O-Instanz angegebenen, Konto verknüpft sind, angeboten; aus denen wählt man ein Produkt aus.

Mit den Schaltflächen _Erstellen_ bzw. _Alle erstellen_ werden das/die gewählte Produkt anlegt.

Der Aufruf des Konfigurators kann jederzeit wiederholt werden.

Die Produkte werden aufgrund der _?????-ID_ identifiziert.

Zu den Geräte-Instanzen werden im Rahmen der Konfiguration Produkttyp-abhängig Variablen angelegt. Zusätzlich kann man in dem Modultyp-spezifischen Konfigurationsdialog weitere Variablen aktivieren.

Die Instanzen können dann in gewohnter Weise im Objektbaum frei positioniert werden.

## 4. Funktionsreferenz

### TractiveIO

`Tractive_UpdateData(int $InstanzID)`
ruft die Daten der Tractive-Produkte ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

## 5. Konfiguration

### TractiveIO

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Zugangsdaten              | string   |              | Benutzername und Passwort von https://my.tractive.com |
|                           |          |              | |
| Aktualisiere Daten ...    | integer  | 5            | Aktualisierungsintervall, Angabe in Minuten |

#### Schaltflächen

| Bezeichnung               | Beschreibung |
| :------------------------ | :----------- |
| Aktualisiere Daten        | führt eine sofortige Aktualisierung durch |

### TractiveConfig

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Kategorie                 | integer  | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden |
| Produkte                  | list     |              | Liste der verfügbaren Produkte |

### TractiveSensor

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft              | Typ      | Standardwert | Beschreibung |
| :----------------------- | :--------| :----------- | :----------- |
|                          |          |              | |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Integer<br>

* Float<br>

## 6. Anhang

GUIDs
- Modul: `{FEE67CA6-D938-284B-181D-20496B7411C2}`
- Instanzen:
  - TractiveIO: `{070C93FD-9D19-D670-2C73-20104B87F034}`
  - TractiveConfig: `{F031A9F9-D196-4852-D287-E46A93256F22}`
  - TractiveSensor: `{F3940032-CC4B-9E69-383A-6FFAD13C5438}`
- Nachrichten:
  - `{076043C4-997E-6AB3-9978-DA212D50A9F5}`: an TractiveIO
  - `{53264646-2842-AA77-59F7-3722D44C2100}`: an TractiveSensor

## 7. Versions-Historie

- 1.0 @ 30.06.2020 18:37 (dev)
  - Initiale Version
