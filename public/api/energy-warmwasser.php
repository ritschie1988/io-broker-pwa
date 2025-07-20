<?php
// Gibt die Warmwassertemperatur der letzten 7 Tage als JSON für das Frontend zurück
header('Content-Type: application/json');

$db = new SQLite3(__DIR__ . '/../../data/warmwasser.sqlite');

// Tabelle anlegen, falls nicht vorhanden
$db->exec('CREATE TABLE IF NOT EXISTS temperature_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    value REAL
)');

// Anzahl Tage (default 2)
$days = isset($_GET['days']) ? intval($_GET['days']) : 2;


// Hole die letzten $days Tage, gruppiert nach Stunde (Durchschnitt pro Stunde)
$stmt = $db->prepare('
    SELECT strftime("%Y-%m-%d %H:00", timestamp) as date, ROUND(AVG(value),1) as value
    FROM temperature_log
    WHERE timestamp >= datetime("now", :days)
    GROUP BY strftime("%Y-%m-%d %H", timestamp)
    ORDER BY date ASC
');
$stmt->bindValue(':days', '-' . $days . ' days', SQLITE3_TEXT);
$res = $stmt->execute();

$data = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $data[] = [
        'date' => $row['date'],
        'value' => floatval($row['value'])
    ];
}

echo json_encode($data);
