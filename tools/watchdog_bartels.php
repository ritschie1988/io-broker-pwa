<?php
// watchdog_bartels.php
// Prüft Sensordaten-Zeitstempel und schaltet Leselicht bei Timeout

// Konfiguration
$dbPath = '/var/www/html/progpfad/io-broker-pwa/public/api/Von_Bartels_Daten/bartels_data.db';
$proxyUrl = 'http://darkorbithome.ddns.net/iobroker/api/iobroker-proxy.php';
$datenpunkt = 'sonoff.0.WohnzimmerLeselicht.POWER';
$logFile = '/var/www/html/progpfad/io-broker-pwa/data/logs/watchdog_bartels.log';

function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

try {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
} catch (Exception $e) {
    logMsg('DB-Fehler: ' . $e->getMessage());
    exit(1);
}

$res = $db->query('SELECT zeit FROM sensordaten ORDER BY zeit DESC LIMIT 1');
$row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
if (!$row || !isset($row['zeit'])) {
    logMsg('Kein Zeitstempel gefunden');
    exit(1);
}

$lastTime = strtotime($row['zeit']);
$now = time();
if ($now - $lastTime > 1800) { // älter als 30 Minuten
    logMsg('Timeout erkannt, schalte Leselicht aus');
    // Leselicht aus
    $url = $proxyUrl . '?endpoint=setState&query=id=' . urlencode($datenpunkt) . '&value=false';
    file_get_contents($url);
    sleep(120);
    logMsg('Schalte Leselicht wieder ein');
    $url = $proxyUrl . '?endpoint=setState&query=id=' . urlencode($datenpunkt) . '&value=true';
    file_get_contents($url);
} else {
    logMsg('Zeitstempel aktuell, keine Aktion');
}
$db->close();
