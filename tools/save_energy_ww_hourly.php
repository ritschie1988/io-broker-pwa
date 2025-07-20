<?php
$dbFile = '/var/www/html/progpfad/io-broker-pwa/data/energy_ww_hourly.sqlite';
@mkdir(dirname($dbFile), 0775, true);
$db = new SQLite3($dbFile);
$db->exec('CREATE TABLE IF NOT EXISTS energy_hourly (date TEXT, hour INTEGER, value REAL, temp REAL, PRIMARY KEY(date, hour))');

function getVal($id) {
    $url = "https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/" . urlencode($id);
    $data = @file_get_contents($url);
    if ($data === false) return null;
    $json = json_decode($data, true);
    return $json['val'] ?? null;
}

$energy = getVal('alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Energy');
$temp = getVal('alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.temperatureC');

if ($energy !== null && $temp !== null) {
    $date = date('Y-m-d');
    $hour = (int)date('G');
    // Letzten Wert holen
    $res = $db->querySingle("SELECT value FROM energy_hourly WHERE date = '$date' AND hour < $hour ORDER BY hour DESC LIMIT 1");
    $last = $res !== null ? floatval($res) : $energy;
    $diff = $energy - $last;
    if ($diff < 0) $diff = 0;
    $verbrauch_kwh = $diff / 1000; // Wh → kWh
    $stmt = $db->prepare('INSERT OR REPLACE INTO energy_hourly (date, hour, value, temp) VALUES (:date, :hour, :value, :temp)');
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $stmt->bindValue(':hour', $hour, SQLITE3_INTEGER);
    $stmt->bindValue(':value', $verbrauch_kwh, SQLITE3_FLOAT);
    $stmt->bindValue(':temp', $temp, SQLITE3_FLOAT);
    $stmt->execute();
}