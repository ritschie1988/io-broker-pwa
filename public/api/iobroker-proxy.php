<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Parameter aus der URL holen
$endpoint = $_GET['endpoint'] ?? '';
if (!$endpoint) {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Endpoint angegeben']);
    exit;
}


// Ziel-URL zusammenbauen (Workaround: __HASH__ wird zu #)
$endpoint = str_replace('__HASH__', '#', urldecode($endpoint));
$url = "https://10.0.0.15:8087/" . $endpoint;

// Optional: Query-Parameter anhängen
if (!empty($_GET['query'])) {
    $url .= '?' . $_GET['query'];
}

// cURL-Request an ioBroker (ohne Authentifizierung)
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 401) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized from ioBroker', 'body' => $body]);
    exit;
}
if ($http_code >= 400) {
    http_response_code($http_code);
    echo json_encode(['error' => 'ioBroker error', 'body' => $body]);
    exit;
}
echo $body;