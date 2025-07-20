<?php
header('Content-Type: application/json');
$dbFile = '/var/www/html/progpfad/io-broker-pwa/data/energy_ww.sqlite';
if (!file_exists($dbFile)) { echo json_encode([]); exit; }
$db = new SQLite3($dbFile);
$month = $_GET['month'] ?? date('Y-m');
$stmt = $db->prepare('SELECT date, value, temp FROM energy WHERE date LIKE :month ORDER BY date ASC');
$stmt->bindValue(':month', $month . '%', SQLITE3_TEXT);
$res = $stmt->execute();
$data = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $data[] = ['date' => $row['date'], 'value' => $row['value'], 'temp' => $row['temp']];
}
echo json_encode($data);