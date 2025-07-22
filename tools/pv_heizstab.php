<?php
// pv_heizstab.php
// Heizstabsteuerung nach PV-Überschuss, Temperatur und Solarstatus

// Konfiguration
$dbPath = '/var/www/html/progpfad/io-broker-pwa/data/Von_Bartels_Daten/bartels_data.db';
$proxyUrl = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php';
$logFile = '/var/www/html/progpfad/io-broker-pwa/data/logs/pv_heizstab.log';
$settingsFile = '/var/www/html/progpfad/io-broker-pwa/data/pv_heizstab_settings.json';

// Settings laden
$pvThreshold = 3500;
$tempMin = 50;
$tempMax = 60;
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (is_array($settings)) {
        $pvThreshold = isset($settings['pvThreshold']) ? (int)$settings['pvThreshold'] : 3500;
        $tempMin = isset($settings['tempMin']) ? (int)$settings['tempMin'] : 50;
        $tempMax = isset($settings['tempMax']) ? (int)$settings['tempMax'] : 60;
    }
}

// Datenpunkte
$dp_power = '0_userdata.0.Photovoltaik.Huawei.Meter.Active_power';
$dp_temp = 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben';
$dp_override = '0_userdata.0.OverrideWarmwasser';
$dp_heizstab = 'shelly.0.shellyplus1pm#80646fe2dfd4#1.Relay0.Switch';

function logMsg($msg) {
    global $logFile;
    // Nur die letzten 99 Zeilen behalten
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($lines, -99); // maximal 99 alte Einträge
    } else {
        $lines = [];
    }
    $lines[] = date('Y-m-d H:i:s') . ' ' . $msg;
    file_put_contents($logFile, implode("\n", $lines) . "\n");
}

// Helper: ioBroker-Datenpunkt lesen
function getDP($id) {
    global $proxyUrl, $logFile;
    $url = $proxyUrl . '?endpoint=get/' . urlencode($id);
    $resp = @file_get_contents($url);
    $data = json_decode($resp, true);
    if ($data === null) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " Fehler beim JSON-Decode für $id: " . $resp . "\n", FILE_APPEND);
    }
    return $data['val'] ?? null;
}
// Helper: ioBroker-Datenpunkt schreiben
function setDP($id, $value) {
    global $proxyUrl;
    $url = $proxyUrl . '?endpoint=set/' . urlencode($id) . '?value=' . ($value ? 'true' : 'false');
    @file_get_contents($url);
}

// Override prüfen
$override = getDP($dp_override);
if ($override) {
    logMsg('Override aktiv, Script beendet.');
    exit(0);
}

// PV-Überschuss und Temperatur holen
$power = getDP($dp_power);
$temp = getDP($dp_temp);
if ($power === null || $temp === null) {
    logMsg('Fehler beim Lesen der Datenpunkte: PV=' . var_export($power, true) . ', Temp=' . var_export($temp, true));
    exit(1);
}

// relay1 aus DB holen
try {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $res = $db->query('SELECT relay1 FROM sensordaten ORDER BY zeit DESC LIMIT 1');
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    $relay1 = $row ? $row['relay1'] : null;
    $db->close();
} catch (Exception $e) {
    logMsg('DB-Fehler: ' . $e->getMessage());
    exit(1);
}

// Steuerlogik
if ($relay1 === '1' || $relay1 === 1) {
    setDP($dp_heizstab, false);
    logMsg('Solar aktiv, Heizstab bleibt aus');
    exit(0);
}

// PV-Überschuss vorhanden und Temp < tempMax
if ($power >= $pvThreshold && $temp < $tempMax) {
    setDP($dp_heizstab, true);
    logMsg('Heizstab EIN: PV=' . $power . 'W, Temp=' . $temp . '°C');
    exit(0);
}

// Kein PV-Überschuss, Temperatur < tempMin: Heizstab bis tempMax einschalten
if ($power < $pvThreshold && $temp < $tempMin) {
    setDP($dp_heizstab, true);
    logMsg('Notbetrieb: Heizstab EIN (keine Sonne, Temp < ' . $tempMin . '°C): PV=' . $power . 'W, Temp=' . $temp . '°C');
    exit(0);
}
// Wenn im Notbetrieb Temp >= tempMax, Heizstab wieder ausschalten
if ($power < $pvThreshold && $temp >= $tempMax) {
    setDP($dp_heizstab, false);
    logMsg('Notbetrieb: Heizstab AUS (Temp >= ' . $tempMax . '°C): PV=' . $power . 'W, Temp=' . $temp . '°C');
    exit(0);
}

// Standardfall: Heizstab aus
setDP($dp_heizstab, false);
logMsg('Heizstab AUS: PV=' . $power . 'W, Temp=' . $temp . '°C');
