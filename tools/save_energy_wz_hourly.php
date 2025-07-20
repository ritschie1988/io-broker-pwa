<?php
// filepath: /var/www/html/progpfad/io-broker-pwa/tools/save_energy_wz_hourly.php

$dbFile = '/var/www/html/progpfad/io-broker-pwa/data/energy_wz_hourly.sqlite';
@mkdir(dirname($dbFile), 0775, true);
$db = new SQLite3($dbFile);
$db->exec('CREATE TABLE IF NOT EXISTS energy_hourly (date TEXT, hour INTEGER, value REAL, PRIMARY KEY(date, hour))');

// Wert von ioBroker holen
$url = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/sonoff.0.PowerWZ.ENERGY_Today';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

 $data = json_decode($response, true);
$value = $data['val'] ?? null;

    if ($value !== null) {
    $date = date('Y-m-d');
    $hour = (int)date('G');
    $stmt = $db->prepare('INSERT OR REPLACE INTO energy_hourly (date, hour, value) VALUES (:date, :hour, :value)');
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $stmt->bindValue(':hour', $hour, SQLITE3_INTEGER);
    $stmt->bindValue(':value', $value, SQLITE3_FLOAT);
    $stmt->execute();
}