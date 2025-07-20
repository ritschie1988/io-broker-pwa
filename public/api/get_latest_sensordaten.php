<?php
$db = new SQLite3('/var/www/html/progpfad/io-broker-pwa/public/api/Von_Bartels_Daten/bartels_data.db');
$result = $db->query('SELECT * FROM sensordaten ORDER BY id DESC LIMIT 1');
$row = $result->fetchArray(SQLITE3_ASSOC);
header('Content-Type: application/json');
echo json_encode($row);
?>
