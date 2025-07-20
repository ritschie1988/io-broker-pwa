<?php
// filepath: /var/www/html/progpfad/io-broker-pwa/public/api/energy-today-wz.php

header('Content-Type: application/json');

$dbFile = '/var/www/html/progpfad/io-broker-pwa/data/energy_wz_hourly.sqlite';
if (!file_exists($dbFile)) {
    echo json_encode([]);
    exit;
}
$db = new SQLite3($dbFile);

$date = $_GET['date'] ?? date('Y-m-d');
$stmt = $db->prepare('SELECT hour, value FROM energy_hourly WHERE date = :date ORDER BY hour ASC');
$stmt->bindValue(':date', $date, SQLITE3_TEXT);
$res = $stmt->execute();

$data = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $data[] = [
        'hour' => $row['hour'],
        'value' => $row['value']
    ];
}
echo json_encode($data);