<?php
// Tool-Skript: Holt die aktuelle Warmwassertemperatur von ioBroker und speichert sie in die SQLite-DB


// Konfiguration
$apiUrl = 'https://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php?endpoint=get/mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben';
$dbPath = __DIR__ . '/../data/warmwasser.sqlite';

// Temperatur von ioBroker über Proxy holen
$data = @file_get_contents($apiUrl);
if ($data === false) {
    fwrite(STDERR, "Fehler beim Abrufen der Temperatur über Proxy\n");
    exit(1);
}
$json = json_decode($data, true);
if (!isset($json['val'])) {
    fwrite(STDERR, "Ungueltige Antwort vom Proxy: ".$data."\n");
    exit(2);
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

echo "OK: $temperature\n";
