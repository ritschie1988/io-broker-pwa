<?php
/**
 * Doorbell API Bridge - PHP Proxy für Python Service
 * File: /var/www/html/progpfad/io-broker-pwa/public/api/doorbell.php
 * URL: /iobroker/api/doorbell.php?endpoint=status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Konfiguration
$config = [
    'python_service' => [
        'host' => '127.0.0.1',  // Raspberry Pi Python Service
        'port' => 5000,
        'timeout' => 30
    ]
];

/**
 * Error Response senden
 */
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

/**
 * Success Response senden
 */
function sendSuccess($data) {
    echo json_encode($data);
    exit;
}

/**
 * HTTP Request an Python Service weiterleiten
 */
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
    
    // Spezielle Behandlung für stream/live (Bildstream)
    if ($endpoint === 'stream/live' && $httpCode === 200) {
        // HTTP Headers für Bildstream setzen
        header('Content-Type: image/jpeg');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Raw Bilddata ausgeben
        echo $response;
        exit;
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        sendError("Ungültige Antwort vom Python Service", 502);
    }
    
    return $decoded;
}

/**
 * File Download von Python Service
 */
function forwardFileDownload($endpoint) {
    global $config;
    
    $url = "http://{$config['python_service']['host']}:{$config['python_service']['port']}/api/doorbell/{$endpoint}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for files
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
    
    // Set appropriate headers for file download
    if (strpos($contentType, 'application/zip') !== false) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="alarm_images.zip"');
    } elseif (strpos($contentType, 'image/') !== false) {
        header("Content-Type: $contentType");
    } else {
        header('Content-Type: application/octet-stream');
    }
    
    echo $response;
    exit;
}

/**
 * Stream Proxy für Live Video
 */
function proxyStream($endpoint) {
    global $config;
    
    $url = "http://{$config['python_service']['host']}:{$config['python_service']['port']}/api/doorbell/{$endpoint}";
    
    // Set streaming headers
    header('Content-Type: multipart/x-mixed-replace; boundary=frame');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open connection to Python service
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        ob_flush();
        flush();
        return strlen($data);
    });
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for streaming
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024); // Small buffer for real-time streaming
    
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// Main Request Handling
try {
    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    if (empty($endpoint)) {
        sendError('Endpoint parameter fehlt', 400);
    }
    
    // Route different endpoints
    switch ($endpoint) {
        case 'status':
            $result = forwardToPythonService('status');
            sendSuccess($result);
            break;
            
        case 'stream/start':
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            $result = forwardToPythonService('stream/start', 'POST');
            sendSuccess($result);
            break;
            
        case 'stream/stop':
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            $result = forwardToPythonService('stream/stop', 'POST');
            sendSuccess($result);
            break;
            
        case 'stream/live':
            // Proxy live video stream (MJPEG)
            proxyStream('stream/live');
            break;
            
        case 'settings':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input === null) {
                    sendError('Invalid JSON data', 400);
                }
                $result = forwardToPythonService('settings', 'POST', $input);
                sendSuccess($result);
            } else {
                sendError('POST method required', 405);
            }
            break;
            
        case 'test-alarm':
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            $result = forwardToPythonService('test-alarm', 'POST');
            sendSuccess($result);
            break;
            
        case 'alarms':
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            $queryString = "page=$page&limit=$limit";
            $result = forwardToPythonService("alarms?$queryString");
            sendSuccess($result);
            break;
            
        default:
            // Handle dynamic endpoints like alarms/{id}/images, alarms/{id}/download, images/{path}
            if (preg_match('/^alarms\/(\d+)\/images$/', $endpoint, $matches)) {
                $alarmId = $matches[1];
                $result = forwardToPythonService("alarms/$alarmId/images");
                sendSuccess($result);
                
            } elseif (preg_match('/^alarms\/(\d+)\/download$/', $endpoint, $matches)) {
                $alarmId = $matches[1];
                forwardFileDownload("alarms/$alarmId/download");
                
            } elseif (preg_match('/^images\/(.+)$/', $endpoint, $matches)) {
                $imagePath = $matches[1];
                forwardFileDownload("images/$imagePath");
                
            } else {
                sendError("Unknown endpoint: $endpoint", 404);
            }
            break;
    }
    
} catch (Exception $e) {
    sendError("Server Error: " . $e->getMessage(), 500);
}
?>