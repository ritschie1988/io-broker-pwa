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
$apiKey = '';
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
    $pattern = '/(?:schalte|mach|stelle)\s+(das|die)?\s*([\wäöüß]+)?\s*(fernsehlicht|nachtlicht|hauptlicht|licht|lampe|ambiente|kochlicht|stimmungslicht)?\s*(im|in)?\s*([\wäöüß]+)?\s*(an|aus|ein)/iu';
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
        if ($state === 'ein') $state = 'an'; // Normalisierung
        
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
                $value = ($state === 'an') ? $dev['onValue'] : $dev['offValue'];
                $results[] = [
                    'id' => $dev['id'],
                    'name' => $dev['name'], 
                    'room' => $room,
                    'label' => $label,
                    'state' => $state,
                    'value' => $value,
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

// Mehrfachbefehle für Rollladen
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

// *** HAUPTLOGIK BEGINNT HIER ***

// 1. ZUERST: Direkte Pattern-Erkennung für Lichtbefehle
$lightResults = parseMultiLightCommands($userInput, $devices);
if (!empty($lightResults)) {
    echo json_encode(["multi_light" => $lightResults]);
    exit;
}

// 2. Rollladen-Befehle erkennen und verarbeiten
$shutterResults = parseMultiShutterCommands($userInput, $devices);
if (!empty($shutterResults) && isset($shutterResults[0]['id'])) {
    echo json_encode(["multi_shutter" => $shutterResults]);
    exit;
}

// 3. FALLBACK: OpenAI API für komplexere Befehle
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
        "name" => "control_ac",
        "description" => "Steuert die Klimaanlage eines Raumes",
        "parameters" => array(
            "type" => "object",
            "properties" => array(
                "room" => array("type" => "string"),
                "action" => array("type" => "string", "enum" => array("on", "off", "set_temp", "set_mode", "set_turbo", "set_all")),
                "mode" => array("type" => "number"),
                "temperature" => array("type" => "number"),
                "turbo" => array("type" => "boolean"),
                "value" => array("type" => "number")
            ),
            "required" => array("room", "action")
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

// *** HIER WAR DER FEHLER: API-Antwort wurde nie dekodiert! ***
$decoded = json_decode($response, true);
if (!$decoded || !isset($decoded['choices'][0]['message'])) {
    echo json_encode(["status" => "Ungültige API-Antwort"]);
    exit;
}

$message = $decoded['choices'][0]['message'];
$call = isset($message['function_call']) ? $message['function_call'] : null;

// Function Call Verarbeitung
if ($call && $call['name'] === 'toggle_light') {
    $params = json_decode($call['arguments'], true);
    $room = $params['room'] ?? '';
    $state = $params['state'] ?? '';
    
    $dev = findDevice($devices, $room, 'switch');
    if ($dev) {
        $value = ($state === 'on') ? $dev['onValue'] : $dev['offValue'];
        echo json_encode([
            "multi_light" => [[
                'id' => $dev['id'],
                'name' => $dev['name'],
                'room' => $room,
                'value' => $value,
                'status' => "Schaltbefehl für {$dev['name']} im $room ($state) wird ausgeführt."
            ]]
        ]);
    } else {
        echo json_encode([
            "multi_light" => [[
                'error' => "Kein passendes Licht im Raum '$room' gefunden."
            ]]
        ]);
    }
    exit;
}

if ($call && $call['name'] === 'control_ac') {
    $params = json_decode($call['arguments'], true);
    echo json_encode(["ac_control" => $params]);
    exit;
}

// Fallback: Standard-Antwort
$content = $message['content'] ?? '';
echo json_encode(["response" => $content ? $content : "Entschuldigung, ich konnte Ihre Anfrage nicht verstehen."]);
?>