<?php
// Konfiguration

$dbPath = __DIR__ . '/../../data/pv_values.sqlite';
// API-Base dynamisch bestimmen (wie bei anderen Broker-Abfragen)
$apiBase = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/';

// Zu loggende Datenpunkte
$datapoints = [
    'einspeisung' => '0_userdata.0.Photovoltaik.Huawei.Meter.Positive_active_electricity',
    'verbrauch'   => '0_userdata.0.Photovoltaik.Huawei.Meter.Reverse_active_power',
    'produktion'  => '0_userdata.0.Photovoltaik.Huawei.Inverter1.Accumulated_energy_yield',
];

// SQLite DB anlegen/öffnen
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS pv_values (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp INTEGER NOT NULL,
        einspeisung REAL,
        verbrauch REAL,
        produktion REAL
    )');
} catch (Exception $e) {
    die('DB-Fehler: ' . $e->getMessage());
}

// Werte abfragen
$values = [];
foreach ($datapoints as $key => $dp) {
    $url = $apiBase . urlencode($dp);
    // Proxy-Aufruf wie im Repo: relativer Pfad, nicht direkt auf Port 8087
    $json = @file_get_contents($url);
    if ($json === false) {
        $values[$key] = null;
        continue;
    }
    $data = json_decode($json, true);
    $values[$key] = isset($data['val']) ? floatval($data['val']) : null;
}

// Debug: Werte protokollieren
file_put_contents('/tmp/pv_log_debug.txt', print_r($values, true), FILE_APPEND);

// Prüfen ob alle Werte vorhanden sind
if (in_array(null, $values, true)) {
    // Initialfehler: Nicht alle Werte verfügbar, kein Eintrag
    exit(0);
}

// In DB schreiben
$stmt = $db->prepare('INSERT INTO pv_values (timestamp, einspeisung, verbrauch, produktion) VALUES (?, ?, ?, ?)');
$stmt->execute([
    time(),
    $values['einspeisung'],
    $values['verbrauch'],
    $values['produktion'],
]);

// Optional: Alte Daten löschen (z. B. älter als 2 Jahre)
$db->exec('DELETE FROM pv_values WHERE timestamp < ' . (time() - 60*60*24*730));

// Erfolg
exit(0);
