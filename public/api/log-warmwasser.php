<?php
// Dieses Skript liest die aktuelle Warmwassertemperatur per ioBroker-API und speichert sie in die SQLite-DB


// Konfiguration
$datapoint = 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben';
$proxyUrl = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/' . urlencode($datapoint);
$dbPath = __DIR__ . '/../../storage/app/warmwasser.sqlite';


// Temperatur von ioBroker über Proxy holen
$data = @file_get_contents($proxyUrl);
if ($data === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Abrufen der Temperatur über Proxy']);
    exit;
}
$json = json_decode($data, true);
if (!isset($json['val'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Ungueltige Antwort vom Proxy/ioBroker']);
    exit;
}
$temperature = floatval($json['val']);

// In SQLite speichern
$db = new SQLite3($dbPath);
$db->exec('CREATE TABLE IF NOT EXISTS temperature_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    value REAL
)');
$stmt = $db->prepare('INSERT INTO temperature_log (value) VALUES (:value)');
$stmt->bindValue(':value', $temperature, SQLITE3_FLOAT);
$stmt->execute();

echo json_encode(['success' => true, 'value' => $temperature]);