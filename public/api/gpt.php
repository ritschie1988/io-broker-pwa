<?php
// Hilfsfunktion: Finde Gerät anhand Raum, Action und Label (Name/Synonym)
function findDeviceByAction($devices, $room, $action, $label = null) {
    $room = strtolower($room);
    $action = strtolower($action);
    $labelLower = $label ? strtolower($label) : null;
    if (!isset($devices[$room])) return null;
    foreach ($devices[$room] as $dev) {
        // Action muss im actions-Array vorkommen
        if (isset($dev['actions']) && in_array($action, array_map('strtolower', $dev['actions']))) {
            // Label-Match: Name oder Synonym enthält Label (oder kein Label angegeben)
            $nameMatch = $labelLower && isset($dev['name']) ? (stripos($dev['name'], $labelLower) !== false || stripos($labelLower, strtolower($dev['name'])) !== false) : true;
            $synMatch = false;
            if ($labelLower && isset($dev['synonyms'])) {
                foreach ($dev['synonyms'] as $syn) {
                    if (stripos($syn, $labelLower) !== false || stripos($labelLower, strtolower($syn)) !== false) {
                        $synMatch = true;
                        break;
                    }
                }
            }
            if ($nameMatch || $synMatch) {
                return $dev;
            }
        }
    }
    return null;
}
// API-Key aus .env lesen

$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile);
    foreach ($lines as $line) {
        if (strpos($line, 'OPENAI_API_KEY=') === 0) {
            $apiKey = trim(explode('=', $line, 2)[1]);
            break;
        }
    }
}
if (!$apiKey) {
    echo json_encode(["status" => "Kein API-Key in .env gefunden!"]);
    exit;
}
$userInput = $_POST['user'] ?? '';

// devices.json laden
$devicesFile = __DIR__ . '/../devices.json';
$devices = file_exists($devicesFile) ? json_decode(file_get_contents($devicesFile), true) : [];

// Hilfsfunktion: Licht/Switch/Sensor/Target im Raum finden
function findDevice($devices, $room, $type, $label = null) {
    $room = strtolower($room);
    foreach ($devices as $r => $devs) {
        if (strtolower($r) !== $room) continue;
        foreach ($devs as $dev) {
            if ($dev['type'] === $type) {
                if ($label && strlen($label) > 0) {
                    $labelLower = strtolower($label);
                    if (isset($dev['name']) && (strtolower($dev['name']) === $labelLower || stripos($dev['name'], $labelLower) !== false || stripos($labelLower, strtolower($dev['name'])) !== false)) return $dev;
                    if (isset($dev['synonyms'])) {
                        foreach ($dev['synonyms'] as $syn) {
                            $synLower = strtolower($syn);
                            if ($synLower === $labelLower || stripos($synLower, $labelLower) !== false || stripos($labelLower, $synLower) !== false) return $dev;
                        }
                    }
                } else {
                    return $dev;
                }
            }
        }
    }
    return null;
}

function parseMultiLightCommands($input, $devices) {
    $pattern = '/(?:schalte|mach|stelle)\s+(das|die)?\s*([\wäöüß]+)?\s*(fernsehlicht|nachtlicht|hauptlicht|licht|lampe|ambiente|kochlicht)?\s*(im|in)?\s*([\wäöüß]+)?\s*(an|aus)/iu';
    preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);
    $results = [];
    foreach ($matches as $m) {
        // Label aus den Gruppen extrahieren
        $label = '';
        if (!empty($m[2]) && !empty($m[3])) {
            $label = trim($m[2] . ' ' . $m[3]);
        } elseif (!empty($m[3])) {
            $label = trim($m[3]);
        } elseif (!empty($m[2])) {
            $label = trim($m[2]);
        }
        $room = isset($m[5]) ? strtolower($m[5]) : '';
        $state = $m[6] ?? '';
        $debug = [];
        $debug[] = "[DEBUG] Regex-Match: label='$label', room='$room', state='$state'";
        if ($room && $state) {
            // Suche alle Switches im Raum und prüfe Name/Synonym
            $dev = null;
            if (isset($devices[$room])) {
                $debug[] = "[DEBUG] Raum gefunden in devices.json: '$room'";
                // Zuerst exakte Matches sammeln
                $exactDev = null;
                foreach ($devices[$room] as $d) {
                    if ($d['type'] === 'switch') {
                        $labelLower = strtolower($label);
                        $debug[] = "[DEBUG] Vergleiche mit Gerät: name='{$d['name']}', synonyms='" . implode(",", $d['synonyms'] ?? []) . "'";
                        if (isset($d['name']) && strtolower($d['name']) === $labelLower) {
                            $debug[] = "[DEBUG] Exakter Name-Match: '{$d['name']}'";
                            $exactDev = $d;
                            break;
                        }
                        if (isset($d['synonyms'])) {
                            foreach ($d['synonyms'] as $syn) {
                                $synLower = strtolower($syn);
                                if ($synLower === $labelLower) {
                                    $debug[] = "[DEBUG] Exakter Synonym-Match: '$syn'";
                                    $exactDev = $d;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                if ($exactDev) {
                    $dev = $exactDev;
                } else {
                    // Falls kein exakter Treffer, dann 'enthält'-Vergleich
                    foreach ($devices[$room] as $d) {
                        if ($d['type'] === 'switch') {
                            $labelLower = strtolower($label);
                            if (isset($d['name']) && (stripos($d['name'], $labelLower) !== false || stripos($labelLower, strtolower($d['name'])) !== false)) {
                                $debug[] = "[DEBUG] Name-Teil-Match: '{$d['name']}'";
                                $dev = $d;
                                break;
                            }
                            if (isset($d['synonyms'])) {
                                foreach ($d['synonyms'] as $syn) {
                                    $synLower = strtolower($syn);
                                    if (stripos($synLower, $labelLower) !== false || stripos($labelLower, $synLower) !== false) {
                                        $debug[] = "[DEBUG] Synonym-Teil-Match: '$syn'";
                                        $dev = $d;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $debug[] = "[DEBUG] Raum nicht in devices.json gefunden: '$room'";
            }
            if ($dev) {
                $results[] = [
                    'id' => $dev['id'],
                    'room' => $room,
                    'label' => $label,
                    'state' => $state,
                    'onValue' => $dev['onValue'] ?? 1,
                    'offValue' => $dev['offValue'] ?? 0,
                    'debug' => $debug
                ];
            } else {
                $debug[] = "[DEBUG] Kein passendes Gerät gefunden.";
                $results[] = [
                    'error' => "Kein passendes Gerät für '{$label}' im Raum '{$room}' gefunden.",
                    'room' => $room,
                    'label' => $label,
                    'state' => $state,
                    'debug' => $debug
                ];
            }
        } else {
            $debug[] = "[DEBUG] Raum oder State fehlt.";
            $results[] = [
                'error' => "Ungültiger Befehl: Raum oder Zustand nicht erkannt.",
                'room' => $room,
                'label' => $label,
                'state' => $state,
                'debug' => $debug
            ];
        }
    }
    return $results;
}

// Definiere Funktion(en) wie gehabt
// KI-Funktionsdefinitionen erweitert: Rollladen-Position explizit
$functions = array(
    array(
        "name" => "toggle_light",
        "description" => "Schaltet ein Licht an oder aus",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string"),
                "state" => array("type" => "string", "enum" => array("on", "off"))
            ),
            "required" => array("room", "state")
        )
    ),
    array(
        "name" => "get_temperature",
        "description" => "Liest die aktuelle Temperatur eines Raumes aus",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string")
            ),
            "required" => array("room")
        )
    ),
    array(
        "name" => "set_temperature",
        "description" => "Setzt die Solltemperatur eines Raumes",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string"),
                "value" => array("type" => "number")
            ),
            "required" => array("room", "value")
        )
    ),
    array(
        "name" => "control_ac",
        "description" => "Steuert die Klimaanlage eines Raumes (ein/aus, Solltemperatur, Modus, Turbo, Kombinationen möglich)",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string"),
                "action" => array("type" => "string", "enum" => array("on", "off", "set_temp", "set_mode", "set_turbo", "set_all")),
                "mode" => array("type" => "number", "description" => "1=Automatik, 2=Kühlen, 3=Entfeuchten, 4=Heizen, 5=Nur Lüfter"),
                "temperature" => array("type" => "number", "description" => "Solltemperatur in °C"),
                "turbo" => array("type" => "boolean", "description" => "Turbo-Modus an/aus"),
                "value" => array("type" => "number")
            ),
            "required" => array("room", "action")
        )
    ),
    array(
        "name" => "set_shutter_position",
        "description" => "Setzt die Position eines Rollladens im Raum (0=geschlossen, 100=offen)",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string"),
                "label" => array("type" => "string", "description" => "z.B. rechts, links, mittig"),
                "position" => array("type" => "number", "description" => "0=geschlossen, 100=offen, 1-99=Zwischenwert")
            ),
            "required" => array("room", "label", "position")
        )
    )
);
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

// Mehrfachbefehle für Klima: "Schalte die Klimaanlage an, stelle sie auf Kühlmodus und stelle die Zieltemperatur auf 22 grad."
function parseMultiClimateCommands($input, $devices) {
    $pattern = '/klima(anlage)?\s*(im|in)?\s*([\wäöüß]+)?/iu';
    preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);
    $results = [];
    foreach ($matches as $m) {
        $room = $m[3] ?? '';
        if ($room) {
            $devs = $devices[strtolower($room)] ?? [];
            $climate = [];
            foreach ($devs as $dev) {
                if (isset($dev['name']) && stripos($dev['name'], 'klima') !== false) {
                    $climate[] = $dev;
                }
            }
            if ($climate) {
                // Aktionen parsen
                $actions = [];
                if (preg_match('/(an|ein)/iu', $input)) $actions[] = 'on';
                if (preg_match('/aus/iu', $input)) $actions[] = 'off';
                if (preg_match('/kühl|cool|modus\s*:?\s*(2|kühlen)/iu', $input)) $actions[] = ['set_mode', 2];
                if (preg_match('/heiz|heizen|modus\s*:?\s*(4|heizen)/iu', $input)) $actions[] = ['set_mode', 4];
                if (preg_match('/zieltemperatur|solltemperatur|auf\s*(\d{2})\s*grad/iu', $input, $tm)) $actions[] = ['set_temp', intval($tm[1])];
                $results[] = [
                    'room' => $room,
                    'devices' => $climate,
                    'actions' => $actions
                ];
            }
        }
    }
    return $results;
}

// Mehrfachbefehle für Rollladen: "Schließe den rechten Rollladen im Wohnzimmer und öffne den linken Rollladen im Schlafzimmer."
function parseMultiShutterCommands($input, $devices) {
    $pattern = '/(öffne|schließe|hoch|runter|auf|zu)\s+(den|die)?\s*([\wäöüß]+)?\s*(rollladen|rolladen|jalousie|raffstore|shutter)?\s*(im|in)?\s*([\wäöüß]+)?/iu';
    preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);
    $results = [];
    foreach ($matches as $m) {
        $action = strtolower($m[1] ?? '');
        $label = trim(($m[3] ?? '') . ' ' . ($m[4] ?? ''));
        $room = isset($m[6]) ? strtolower($m[6]) : '';
        $debug = [];
        $debug[] = "[DEBUG] Regex-Match: label='$label', room='$room', action='$action'";
        if ($room && $action) {
            $dev = null;
            if (isset($devices[$room])) {
                $debug[] = "[DEBUG] Raum gefunden in devices.json: '$room'";
                // Suche nur Position-Devices mit type shutter
                foreach ($devices[$room] as $d) {
                    if ($d['type'] === 'shutter' && isset($d['name']) && stripos($d['name'], 'position') !== false) {
                        $labelLower = strtolower($label);
                        // Label/Synonym muss im Namen/Synonym vorkommen
                        $match = false;
                        if (stripos($d['name'], $labelLower) !== false || stripos($labelLower, strtolower($d['name'])) !== false) $match = true;
                        if (!$match && isset($d['synonyms'])) {
                            foreach ($d['synonyms'] as $syn) {
                                $synLower = strtolower($syn);
                                if ($synLower === $labelLower || stripos($synLower, $labelLower) !== false || stripos($labelLower, $synLower) !== false) {
                                    $match = true;
                                    break;
                                }
                            }
                        }
                        if ($match) {
                            $dev = $d;
                            break;
                        }
                    }
                }
            }
            if ($dev) {
                // Positionswert bestimmen: öffnen = 100, schließen = 0
                $value = null;
                if (in_array($action, ['öffne', 'hoch', 'auf'])) $value = 100;
                else if (in_array($action, ['schließe', 'runter', 'zu'])) $value = 0;
                $results[] = [
                    'id' => $dev['id'],
                    'room' => $room,
                    'name' => $dev['name'],
                    'action' => $action,
                    'value' => $value,
                    'debug' => $debug
                ];
            } else {
                $debug[] = "[DEBUG] Kein passender Rollladen-Positionsdatenpunkt gefunden.";
                $results[] = [
                    'error' => "Kein passender Rollladen-Positionsdatenpunkt für '{$label}' im Raum '{$room}' gefunden.",
                    'room' => $room,
                    'action' => $action,
                    'debug' => $debug
                ];
            }
        } else {
            $debug[] = "[DEBUG] Raum oder Aktion fehlt.";
            $results[] = [
                'error' => "Ungültiger Rollladen-Befehl: Raum oder Aktion nicht erkannt.",
                'room' => $room,
                'action' => $action,
                'debug' => $debug
            ];
        }
    }
    return $results;
}

// Einzelbefehl wie bisher
if ($call && $call['name'] === 'toggle_light') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $state = $params['state'] ?? '';
    // Suche nach passendem Licht anhand Name und Synonym
    $dev = findDevice($devices, $room, 'switch');
    $call['status'] = $dev ? "Schaltbefehl für $room ($state) ausgeführt. Gerät: {$dev['name']} (ID: {$dev['id']})" : "Kein passendes Gerät gefunden.";
    echo json_encode($call);
    exit;
}
if ($call && $call['name'] === 'get_temperature') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    // Suche nach Sensor mit Name oder Synonym 'Temperatur'
    $dev = null;
    if (isset($devices[$room])) {
        foreach ($devices[$room] as $d) {
            if ($d['type'] === 'sensor') {
                if (isset($d['name']) && stripos($d['name'], 'temperatur') !== false) {
                    $dev = $d;
                    break;
                }
                if (isset($d['synonyms'])) {
                    foreach ($d['synonyms'] as $syn) {
                        if (stripos($syn, 'temperatur') !== false) {
                            $dev = $d;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    $call['status'] = $dev ? "Temperaturabfrage für $room. Sensor: {$dev['name']} (ID: {$dev['id']})" : "Kein Temperatursensor gefunden.";
    echo json_encode($call);
    exit;
}
if ($call && $call['name'] === 'set_temperature') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $value = $params['value'] ?? '';
    // Suche nach Target mit Name oder Synonym 'Solltemperatur' oder 'Zieltemperatur'
    $dev = null;
    if (isset($devices[$room])) {
        foreach ($devices[$room] as $d) {
            if ($d['type'] === 'target') {
                if (isset($d['name']) && (stripos($d['name'], 'solltemperatur') !== false || stripos($d['name'], 'zieltemperatur') !== false)) {
                    $dev = $d;
                    break;
                }
                if (isset($d['synonyms'])) {
                    foreach ($d['synonyms'] as $syn) {
                        if (stripos($syn, 'solltemperatur') !== false || stripos($syn, 'zieltemperatur') !== false) {
                            $dev = $d;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    $call['status'] = $dev ? "Solltemperatur für $room auf $value gesetzt. Gerät: {$dev['name']} (ID: {$dev['id']})" : "Kein Zieltemperatur-Gerät gefunden.";
    echo json_encode($call);
    exit;
}

// KI: Rollladen-Positionsbefehl
if ($call && $call['name'] === 'set_shutter_position') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $label = $params['label'] ?? '';
    $position = $params['position'] ?? null;
    $dev = null;
    if (isset($devices[$room])) {
        foreach ($devices[$room] as $d) {
            if ($d['type'] === 'shutter' && isset($d['name']) && stripos($d['name'], 'position') !== false) {
                // Label muss im Namen/Synonym vorkommen
                $labelLower = strtolower($label);
                $nameMatch = stripos($d['name'], $labelLower) !== false || stripos($labelLower, strtolower($d['name'])) !== false;
                $synMatch = false;
                if (isset($d['synonyms'])) {
                    foreach ($d['synonyms'] as $syn) {
                        if (stripos($syn, $labelLower) !== false || stripos($labelLower, strtolower($syn)) !== false) {
                            $synMatch = true;
                            break;
                        }
                    }
                }
                if ($nameMatch || $synMatch) {
                    $dev = $d;
                    break;
                }
            }
        }
    }
    $call['status'] = $dev ? "Rollladen-Position für $label im $room auf $position gesetzt. Gerät: {$dev['name']} (ID: {$dev['id']})" : "Kein passender Rollladen-Positionsdatenpunkt gefunden.";
    if ($dev) {
        $call['id'] = $dev['id'];
        $call['position'] = $position;
    }
    echo json_encode($call);
    exit;
}

// --- Rollladen-Befehle erkennen und verarbeiten ---
$shutterResults = parseMultiShutterCommands($userInput, $devices);
if (!empty($shutterResults) && isset($shutterResults[0]['id'])) {
    // Mindestens ein passender Rollladen-Befehl erkannt
    echo json_encode(["multi_shutter" => $shutterResults]);
    exit;
}

$status = $decoded['choices'][0]['message']['content'] ?? '';
echo json_encode(["status" => $status ? $status : "Kein passender Funktionsaufruf erkannt."]);
exit;
?>
