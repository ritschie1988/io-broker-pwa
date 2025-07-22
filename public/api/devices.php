
<?php
// API für Geräteverwaltung: GET = Liste, POST = Gerät hinzufügen
// Die Daten werden in devices.json als JSON gespeichert

header('Content-Type: application/json');
$jsonFile = __DIR__ . '/../devices.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($jsonFile)) {
        echo json_encode([]);
        exit;
    }
    $json = file_get_contents($jsonFile);
    echo $json;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'], $input['room'], $input['name'], $input['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }
    $json = file_exists($jsonFile) ? file_get_contents($jsonFile) : '{}';
    $data = json_decode($json, true);
    if (!isset($data[$input['room']])) {
        $data[$input['room']] = [];
    }
    $newDev = [
        'id' => $input['id'],
        'name' => $input['name'],
        'type' => $input['type'],
        'synonyms' => isset($input['synonyms']) ? $input['synonyms'] : [],
        'actions' => isset($input['actions']) ? $input['actions'] : []
    ];
    if ($input['type'] === 'switch') {
        $newDev['onValue'] = $input['onValue'] ?? '';
        $newDev['offValue'] = $input['offValue'] ?? '';
    }
    if ($input['type'] === 'sensor' || $input['type'] === 'target') {
        $newDev['unit'] = $input['unit'] ?? '';
    }
    $data[$input['room']][] = $newDev;
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'], $input['room'], $input['name'], $input['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }
    $json = file_exists($jsonFile) ? file_get_contents($jsonFile) : '{}';
    $data = json_decode($json, true);
    // Suchen und ersetzen
    $found = false;
    foreach ($data as $room => &$devs) {
        foreach ($devs as $idx => $dev) {
            if ($dev['id'] === $input['id'] && $room === $input['room']) {
                $devs[$idx] = [
                    'id' => $input['id'],
                    'name' => $input['name'],
                    'type' => $input['type'],
                    'synonyms' => isset($input['synonyms']) ? $input['synonyms'] : [],
                    'actions' => isset($input['actions']) ? $input['actions'] : []
                ];
                if ($input['type'] === 'switch') {
                    $devs[$idx]['onValue'] = $input['onValue'] ?? '';
                    $devs[$idx]['offValue'] = $input['offValue'] ?? '';
                }
                if ($input['type'] === 'sensor' || $input['type'] === 'target') {
                    $devs[$idx]['unit'] = $input['unit'] ?? '';
                }
                $found = true;
                break;
            }
        }
        if ($found) break;
    }
    if ($found) {
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Gerät nicht gefunden']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'], $input['room'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }
    $json = file_exists($jsonFile) ? file_get_contents($jsonFile) : '{}';
    $data = json_decode($json, true);
    if (!isset($data[$input['room']])) {
        http_response_code(404);
        echo json_encode(['error' => 'Raum nicht gefunden']);
        exit;
    }
    $before = count($data[$input['room']]);
    $data[$input['room']] = array_values(array_filter($data[$input['room']], function($dev) use ($input) {
        return $dev['id'] !== $input['id'];
    }));
    $after = count($data[$input['room']]);
    if ($before === $after) {
        http_response_code(404);
        echo json_encode(['error' => 'Gerät nicht gefunden']);
        exit;
    }
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}