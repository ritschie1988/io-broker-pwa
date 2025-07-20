<?php
$dbFile = '/var/www/html/progpfad/io-broker-pwa/data/energy_bad.sqlite';
@mkdir(dirname($dbFile), 0775, true);
$db = new SQLite3($dbFile);
$db->exec('CREATE TABLE IF NOT EXISTS energy (date TEXT PRIMARY KEY, value REAL)');

$url = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/sonoff.0.PowerBad.ENERGY_Today';
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
    $stmt = $db->prepare('INSERT OR REPLACE INTO energy (date, value) VALUES (:date, :value)');
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $stmt->bindValue(':value', $value, SQLITE3_FLOAT);
    $stmt->execute();
}