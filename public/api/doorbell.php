<?php
/**
 * Doorbell API Bridge - PHP Proxy für ESP32 Deep Sleep Person Detection
 * File: /var/www/html/progpfad/io-broker-pwa/public/api/doorbell.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$config = [
    'python_service' => [
        'host' => '127.0.0.1',
        'port' => 5000,
        'timeout' => 30
    ]
];

function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

function sendSuccess($data) {
    echo json_encode($data);
    exit;
}

function forwardToPythonService($endpoint, $method = 'GET', $data = null) {
    global $config;
    
    $url = "http://{$config['python_service']['host']}:{$config['python_service']['port']}/api/doorbell/{$endpoint}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['python_service']['timeout']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        sendError("Python Service nicht erreichbar: $error", 503);
    }
    
    if ($httpCode >= 400) {
        http_response_code($httpCode);
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        sendError("Ungültige Antwort vom Python Service", 502);
    }
    
    return $decoded;
}

function forwardFileDownload($endpoint) {
    global $config;
    
    $url = "http://{$config['python_service']['host']}:{$config['python_service']['port']}/api/doorbell/{$endpoint}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        sendError("Download fehlgeschlagen: $error", 503);
    }
    
    if ($httpCode !== 200) {
        sendError("Download nicht möglich", $httpCode);
    }
    
    if (strpos($contentType, 'image/') !== false) {
        header("Content-Type: $contentType");
        header('Cache-Control: public, max-age=3600');
    } else {
        header('Content-Type: application/octet-stream');
    }
    
    echo $response;
    exit;
}

try {
    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    if (empty($endpoint)) {
        sendError('Endpoint parameter fehlt', 400);
    }
    
    switch ($endpoint) {
        case 'status':
            $result = forwardToPythonService('status');
            sendSuccess($result);
            break;
            
        case 'person-check':
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                sendError('Invalid JSON data', 400);
            }
            $result = forwardToPythonService('person-check', 'POST', $input);
            sendSuccess($result);
            break;
            
        case 'detections':
            $limit = $_GET['limit'] ?? 50;
            $result = forwardToPythonService("detections?limit=$limit");
            sendSuccess($result);
            break;
            
        case 'live-image':
            forwardFileDownload('live-image');
            break;
            
        default:
            // Dynamic endpoints
            if (preg_match('/^detections\/(\d+)\/images$/', $endpoint, $matches)) {
                $detection_id = $matches[1];
                $result = forwardToPythonService("detections/$detection_id/images");
                sendSuccess($result);
                
            } elseif (preg_match('/^images\/(.+)$/', $endpoint, $matches)) {
                $image_path = $matches[1];
                forwardFileDownload("images/$image_path");
                
            } else {
                sendError("Unknown endpoint: $endpoint", 404);
            }
            break;
    }
    
} catch (Exception $e) {
    sendError("Server Error: " . $e->getMessage(), 500);
}
?>