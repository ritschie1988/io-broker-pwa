<?php
$dbPath = __DIR__ . '/../../data/pv_values.sqlite';
// Zeitzone explizit auf Österreich setzen
date_default_timezone_set('Europe/Vienna');
header('Content-Type: application/json');


$type = $_GET['type'] ?? 'day';
$day = $_GET['day'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB-Fehler']);
    exit;
}

// Hilfsfunktion: Ersten und letzten Wert im Zeitraum holen
function getFirstLast($db, $startTs, $endTs) {
    $first = $db->query('SELECT * FROM pv_values WHERE timestamp >= ' . intval($startTs) . ' AND timestamp <= ' . intval($endTs) . ' ORDER BY timestamp ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $last  = $db->query('SELECT * FROM pv_values WHERE timestamp >= ' . intval($startTs) . ' AND timestamp <= ' . intval($endTs) . ' ORDER BY timestamp DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return [$first, $last];
}


$startTs = $endTs = 0;
if ($type === 'day') {
    $startTs = strtotime($day . ' 00:00:00');
    $endTs   = strtotime($day . ' 23:59:59');
} elseif ($type === 'month') {
    $startTs = strtotime($year . '-' . $month . '-01 00:00:00');
    $endTs   = strtotime($year . '-' . $month . '-' . date('t', strtotime($year.'-'.$month.'-01')) . ' 23:59:59');
} elseif ($type === 'year') {
    $startTs = strtotime($year . '-01-01 00:00:00');
    $endTs   = strtotime($year . '-12-31 23:59:59');
} elseif ($type === 'total') {
    $firstRow = $db->query('SELECT * FROM pv_values ORDER BY timestamp ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $lastRow  = $db->query('SELECT * FROM pv_values ORDER BY timestamp DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$firstRow || !$lastRow) {
        echo json_encode(['error' => 'Keine Daten']);
        exit;
    }
    $result = [
        'produktion' => $lastRow['produktion'] - $firstRow['produktion'],
        'einspeisung' => $lastRow['einspeisung'] - $firstRow['einspeisung'],
        'verbrauch'   => $lastRow['verbrauch'] - $firstRow['verbrauch'],
        'start' => $firstRow['timestamp'],
        'end'   => $lastRow['timestamp'],
    ];
    echo json_encode($result);
    exit;
}

// Debug: Zeitbereich loggen
file_put_contents('/tmp/pv_api_debug.txt', print_r([
    'startTs' => $startTs,
    'endTs' => $endTs,
    'start' => date('c', $startTs),
    'end' => date('c', $endTs),
    'now' => date('c'),
    'timezone' => date_default_timezone_get()
], true), FILE_APPEND);

list($first, $last) = getFirstLast($db, $startTs, $endTs);
if (!$first || !$last) {
    echo json_encode(['error' => 'Keine Daten im Zeitraum']);
    exit;
}
$result = [
    'produktion' => $last['produktion'] - $first['produktion'],
    'einspeisung' => $last['einspeisung'] - $first['einspeisung'],
    'verbrauch'   => $last['verbrauch'] - $first['verbrauch'],
    'start' => $first['timestamp'],
    'end'   => $last['timestamp'],
];
echo json_encode($result);
exit;

file_put_contents('/tmp/pv_api_debug.txt', print_r([$startTs, $endTs, date('c', $startTs), date('c', $endTs)], true), FILE_APPEND);
