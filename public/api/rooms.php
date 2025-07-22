<?php
// Speichert und liest die Räume-Konfiguration als JSON
// Endpunkt: /public/api/rooms.php

$DATA_FILE = __DIR__ . '/../../data/rooms.json';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($DATA_FILE)) {
        echo file_get_contents($DATA_FILE);
    } else {
        echo json_encode([]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }
    file_put_contents($DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
