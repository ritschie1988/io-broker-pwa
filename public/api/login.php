<?php
// Login-Endpunkt für einen einzigen Zugang
// Passwort aus Umgebungsvariable
header('Content-Type: application/json');

// Passwort aus .env oder Server-Variable
$envPassword = getenv('LOGIN_PASSWORD');
if (!$envPassword) {
    // .env-Datei manuell laden, falls vorhanden
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'LOGIN_PASSWORD=') === 0) {
                $envPassword = trim(explode('=', $line, 2)[1]);
                break;
            }
        }
    }
}
if (!$envPassword) {
    http_response_code(500);
    echo json_encode(['error' => 'Server-Konfiguration fehlt']);
    exit;
}

// POST-Daten lesen
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwort fehlt']);
    exit;
}

// Passwort prüfen
if (hash('sha256', $data['password']) === hash('sha256', $envPassword)) {
    // JWT generieren
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode(['login' => true, 'exp' => time() + 3600]));
    $secret = $envPassword;
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    $jwt = "$header.$payload.$signature";
    echo json_encode(['token' => $jwt]);
    exit;
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Falsches Passwort']);
    exit;
}
