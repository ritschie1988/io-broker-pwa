<?php
// API-Key aus .env lesen
$envFile = __DIR__ . '/../../.env';
$apiKey = '';
if (file_exists($envFile)) {
    $lines = file($envFile);
    foreach ($lines as $line) {
        if (strpos($line, 'OPENAI_API_KEY=') === 0) {
            $apiKey = trim(explode('=', $line, 2)[1]);
            break;
        }
    }
if (!$apiKey) {
    echo json_encode(["status" => "Kein API-Key in .env gefunden!"]);
    exit;
}
$userInput = $_POST['user'] ?? '';

// Definiere Funktion(en)

$functions = [
    [
        "name" => "toggle_light",
        "description" => "Schaltet ein Licht an oder aus",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "room" => ["type" => "string"],
                "state" => ["type" => "string", "enum" => ["on", "off"]]
            ],
            "required" => ["room", "state"]
        ]
    ],
    [
        "name" => "get_temperature",
        "description" => "Liest die aktuelle Temperatur eines Raumes aus",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "room" => ["type" => "string"]
            ],
            "required" => ["room"]
        ]
    ],
    [
        "name" => "set_temperature",
        "description" => "Setzt die Solltemperatur eines Raumes",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "room" => ["type" => "string"],
                "value" => ["type" => "number"]
            ],
            "required" => ["room", "value"]
        ]
    ],
    [
        "name" => "control_ac",
        "description" => "Steuert die Klimaanlage eines Raumes (ein/aus, Solltemperatur, Modus, Turbo, Kombinationen möglich)",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "room" => ["type" => "string"],
                "action" => ["type" => "string", "enum" => ["on", "off", "set_temp", "set_mode", "set_turbo", "set_all"]],
                "mode" => ["type" => "number", "description" => "1=Automatik, 2=Kühlen, 3=Entfeuchten, 4=Heizen, 5=Nur Lüfter"],
                "temperature" => ["type" => "number", "description" => "Solltemperatur in °C"],
                "turbo" => ["type" => "boolean", "description" => "Turbo-Modus an/aus"],
                "value" => ["type" => "number"]
            ],
            "required" => ["room", "action"]
        ]
    ]
];

$data = [
    "model" => "gpt-4-0613",
    "messages" => [
        ["role" => "user", "content" => $userInput]
    ],
    "functions" => $functions,
    "function_call" => "auto"
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(["status" => "Fehler beim API-Request: $curlError"]);
    exit;
}

$decoded = json_decode($response, true);
$call = $decoded['choices'][0]['message']['function_call'] ?? null;


if ($call && $call['name'] === 'toggle_light') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $state = $params['state'] ?? '';
    $call['status'] = "Schaltbefehl für $room ($state) ausgeführt.";
if ($call && $call['name'] === 'get_temperature') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $call['status'] = "Temperaturabfrage für $room.";
    echo json_encode($call);
    exit;
}

}
    echo json_encode($call);
    exit;
}

if ($call && $call['name'] === 'control_ac') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $action = $params['action'] ?? '';
    $mode = isset($params['mode']) ? $params['mode'] : null;
    $temperature = isset($params['temperature']) ? $params['temperature'] : null;
    $turbo = isset($params['turbo']) ? $params['turbo'] : null;
    if ($action === 'set_all') {
        $status = "Klimaanlagen-Befehl für $room: " .
            "eingeschaltet" .
            ($mode !== null ? ", Modus=" . ([1=>'Automatik',2=>'Kühlen',3=>'Entfeuchten',4=>'Heizen',5=>'Nur Lüfter'][$mode] ?? $mode) : '') .
            ($temperature !== null ? ", Temperatur=$temperature°C" : '') .
            ($turbo !== null ? ", Turbo=" . ($turbo ? 'an' : 'aus') : '');
        $call['status'] = $status;
        echo json_encode($call);
        exit;
    } else {
        $status = "Klimaanlagen-Befehl für $room: Aktion=$action";
        if ($mode !== null) {
            $modeText = [1=>'Automatik',2=>'Kühlen',3=>'Entfeuchten',4=>'Heizen',5=>'Nur Lüfter'][$mode] ?? $mode;
            $status .= ", Modus=$modeText";
        }
        if ($temperature !== null) {
            $status .= ", Temperatur=$temperature°C";
        }
        if ($turbo !== null) {
            $status .= ", Turbo=" . ($turbo ? 'an' : 'aus');
        }
        $call['status'] = $status;
        echo json_encode($call);
        exit;
    }
}

$status = $decoded['choices'][0]['message']['content'] ?? '';
echo json_encode(["status" => $status ? $status : "Kein passender Funktionsaufruf erkannt."]);
exit;
?>
