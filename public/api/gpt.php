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
                        // AUSSCHLUSS: Keine Klimageräte in der Lichtsteuerung
                        if (isset($d['name']) && (
                            stripos($d['name'], 'klima') !== false || 
                            stripos($d['name'], 'klimaanlage') !== false
                        )) {
                            $debug[] = "[DEBUG] Klimagerät übersprungen: '{$d['name']}' (gehört nicht zu Lichtsteuerung)";
                            continue;
                        }
                        if (isset($d['synonyms'])) {
                            foreach ($d['synonyms'] as $syn) {
                                if (stripos($syn, 'klima') !== false || stripos($syn, 'klimaanlage') !== false) {
                                    $debug[] = "[DEBUG] Klimagerät übersprungen (Synonym): '{$d['name']}' (gehört nicht zu Lichtsteuerung)";
                                    continue 2;
                                }
                            }
                        }
                        // AUSSCHLUSS: Neue AC-Types
                        if (in_array($d['type'], ['ac-switch', 'ac-target', 'ac-mode'])) {
                            $debug[] = "[DEBUG] AC-Gerät übersprungen: '{$d['name']}' (Type: {$d['type']})";
                            continue;
                        }
                        
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
                            // AUSSCHLUSS: Keine Klimageräte in der Lichtsteuerung
                            if (isset($d['name']) && (
                                stripos($d['name'], 'klima') !== false || 
                                stripos($d['name'], 'klimaanlage') !== false
                            )) {
                                continue;
                            }
                            if (isset($d['synonyms'])) {
                                foreach ($d['synonyms'] as $syn) {
                                    if (stripos($syn, 'klima') !== false || stripos($syn, 'klimaanlage') !== false) {
                                        continue 2;
                                    }
                                }
                            }
                            // AUSSCHLUSS: Neue AC-Types
                            if (in_array($d['type'], ['ac-switch', 'ac-target', 'ac-mode'])) {
                                continue;
                            }
                            
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
    // Verbesserter Regex für Rollläden - flexibler und robuster
    $pattern = '/(öffne|schließe|hoch|runter|auf|zu)\s+(das|den|die)?\s*([lrm]?[\wäöüß]*\s*)?(rollladen|rolladen|rollo|jalousie|raffstore|shutter)\s*(im|in)\s+([\wäöüß]+)/iu';
    preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);
    $results = [];
    
    // Debug: Zeige was der Regex gefunden hat
    error_log("[DEBUG] Shutter-Regex Input: '$input'");
    error_log("[DEBUG] Shutter-Regex Matches: " . print_r($matches, true));
    
    foreach ($matches as $m) {
        $action = strtolower($m[1] ?? '');
        $article = $m[2] ?? ''; // das/den/die
        $label = $m[3] ?? '';   // Zusätzliches Wort (z.B. "rechten", "linken")  
        $shutterWord = $m[4] ?? ''; // rollo/rollladen etc.
        $preposition = $m[5] ?? ''; // im/in
        $room = isset($m[6]) ? strtolower($m[6]) : '';
        
        // Label zusammensetzen
        $fullLabel = trim($label . ' ' . $shutterWord);
        if (empty($fullLabel)) $fullLabel = $shutterWord ?: 'rollladen';
        
        $debug = [];
        $debug[] = "[DEBUG] Regex-Match: action='$action', article='$article', label='$label', shutterWord='$shutterWord', preposition='$preposition', room='$room', fullLabel='$fullLabel'";
        
        if ($room && $action) {
            $dev = null;
            if (isset($devices[$room])) {
                $debug[] = "[DEBUG] Raum gefunden in devices.json: '$room'";
                
                // Suche nach Shutter-Geräten mit 'position' in der ID oder im Namen
                foreach ($devices[$room] as $d) {
                    if ($d['type'] === 'shutter' && (stripos($d['id'], 'position') !== false || stripos($d['name'], 'position') !== false)) {
                        $debug[] = "[DEBUG] Prüfe Shutter-Position-Gerät: '{$d['name']}' (ID: {$d['id']})";
                        
                        // Sehr permissive Matching - wenn es ein Shutter-Position-Gerät gibt, nehmen wir es
                        $match = true; // Standard-Match für Position-Geräte
                        
                        // Optional: Spezifisches Label-Matching (links/rechts etc.)
                        if ($label && (stripos($d['name'], $label) !== false || (isset($d['synonyms']) && array_filter($d['synonyms'], function($syn) use($label) {
                            return stripos($syn, $label) !== false;
                        })))) {
                            $debug[] = "[DEBUG] Spezifisches Label-Match gefunden für '$label'";
                        }
                        
                        if ($match) {
                            $dev = $d;
                            $debug[] = "[DEBUG] Shutter-Gerät gefunden: '{$d['name']}' (ID: {$d['id']})";
                            break;
                        }
                    }
                }
            } else {
                $debug[] = "[DEBUG] Raum nicht in devices.json gefunden: '$room'";
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
                $debug[] = "[DEBUG] Shutter-Befehl erfolgreich erstellt: ID={$dev['id']}, value=$value";
            } else {
                $debug[] = "[DEBUG] Kein passender Rollladen-Positionsdatenpunkt gefunden.";
                $results[] = [
                    'error' => "Kein passender Rollladen-Positionsdatenpunkt für '$fullLabel' im Raum '$room' gefunden.",
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

// 1. ZUERST: Direkte Pattern-Erkennung für Lichtbefehle (aber NICHT für Klimabefehle)
$lightResults = parseMultiLightCommands($userInput, $devices);

// Prüfe ob es sich um einen Klimabefehl handelt - dann überspringen
$isClimateCommand = (
    stripos($userInput, 'klima') !== false || 
    stripos($userInput, 'klimaanlage') !== false ||
    stripos($userInput, 'temperatur') !== false ||
    stripos($userInput, 'grad') !== false
);

if (!empty($lightResults) && !$isClimateCommand) {
    echo json_encode(["multi_light" => $lightResults]);
    exit;
} else if (!empty($lightResults) && $isClimateCommand) {
    // Klimabefehl erkannt - ignoriere Light-Results und gehe zur OpenAI API
    error_log("[DEBUG] Klimabefehl erkannt, überspringe Light-Parsing: '$userInput'");
}

// 2. Rollladen-Befehle erkennen und verarbeiten
$shutterResults = parseMultiShutterCommands($userInput, $devices);
if (!empty($shutterResults)) {
    // Prüfe ob mindestens ein Ergebnis eine ID hat (nicht nur einen Fehler)
    $hasValidShutterCommand = false;
    foreach ($shutterResults as $result) {
        if (isset($result['id']) && $result['id']) {
            $hasValidShutterCommand = true;
            break;
        }
    }
    
    if ($hasValidShutterCommand) {
        echo json_encode(["multi_shutter" => $shutterResults]);
        exit;
    } else {
        // Wenn Regex matched, aber kein Gerät gefunden: Error-Log ausgeben
        error_log("[DEBUG] Shutter-Regex matched, aber kein Gerät gefunden: " . print_r($shutterResults, true));
    }
}

// 3. FALLBACK: OpenAI API für komplexere Befehle
$tools = array(
    array(
        "type" => "function",
        "function" => array(
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
        )
    ),
    array(
        "type" => "function",
        "function" => array(
            "name" => "control_ac",
            "description" => "Steuert die Klimaanlage eines Raumes (ein/aus, Solltemperatur, Modus, Turbo, Kombinationen möglich)",
            "parameters" => array(
                "type" => "object",
                "properties" => array(
                    "room" => array("type" => "string"),
                    "action" => array("type" => "string", "enum" => array("on", "off", "set_temp", "set_mode", "set_turbo", "set_all")),
                    "power" => array("type" => "boolean", "description" => "Klimaanlage ein- oder ausschalten"),
                    "temperature" => array("type" => "number", "description" => "Solltemperatur in °C (16-30)"),
                    "mode" => array("type" => "number", "description" => "1=Automatik, 2=Kühlen, 3=Entfeuchten, 4=Heizen, 5=Nur Lüfter"),
                    "turbo" => array("type" => "boolean", "description" => "Turbo-Modus an/aus")
                ),
                "required" => array("room")
            )
        )
    )
);

$data = [
    "model" => "gpt-4o-mini",  // Kostengünstiger und schneller als gpt-4o
    "messages" => [
        ["role" => "system", "content" => "Du bist ein Smart Home Assistent. Antworte auf Deutsch und nutze die verfügbaren Funktionen zur Gerätesteuerung."],
        ["role" => "user", "content" => $userInput]
    ],
    "tools" => $tools,
    "tool_choice" => "auto",
    "max_tokens" => 500  // Begrenzt die Antwortlänge für bessere Performance
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug: API-Response loggen
error_log("[DEBUG] OpenAI API Response Code: $httpCode");
error_log("[DEBUG] OpenAI API Response: " . substr($response, 0, 500) . "...");

if ($curlError) {
    echo json_encode(["status" => "Fehler beim API-Request: $curlError"]);
    exit;
}

// Prüfe HTTP Status Code
if ($httpCode !== 200) {
    echo json_encode(["status" => "API HTTP Error: $httpCode", "response" => $response]);
    exit;
}

// API-Antwort dekodieren
$decoded = json_decode($response, true);

// Spezielle Fehlerbehandlung für OpenAI API
if ($httpCode === 429) {
    echo json_encode([
        "response" => "⚠️ OpenAI API-Kontingent erreicht. Bitte überprüfen Sie Ihr OpenAI-Konto und Billing-Details.",
        "status" => "quota_exceeded"
    ]);
    exit;
}

if ($httpCode === 401) {
    echo json_encode([
        "response" => "❌ OpenAI API-Key ungültig oder abgelaufen. Bitte aktualisieren Sie den API-Key in der .env Datei.",
        "status" => "invalid_api_key"
    ]);
    exit;
}

if (!$decoded || !isset($decoded['choices'][0]['message'])) {
    // Fallback für wenn die API nicht verfügbar ist
    echo json_encode([
        "response" => "Entschuldigung, der GPT-Service ist momentan nicht verfügbar. Bitte versuchen Sie es später noch einmal.",
        "status" => "api_unavailable",
        "debug" => ["http_code" => $httpCode, "raw_response" => substr($response, 0, 200)]
    ]);
    exit;
}

$message = $decoded['choices'][0]['message'];

// Neue Tools API: tool_calls statt function_call
$toolCalls = isset($message['tool_calls']) ? $message['tool_calls'] : [];

// Tool Call Verarbeitung
if (!empty($toolCalls)) {
    foreach ($toolCalls as $toolCall) {
        if ($toolCall['type'] === 'function') {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);
            
            if ($functionName === 'toggle_light') {
                $room = $arguments['room'] ?? '';
                $state = $arguments['state'] ?? '';
                
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
            
            if ($functionName === 'control_ac') {
                echo json_encode(["ac_control" => $arguments]);
                exit;
            }
        }
    }
}

// Fallback: Standard-Antwort
$content = $message['content'] ?? '';
echo json_encode(["response" => $content ? $content : "Entschuldigung, ich konnte Ihre Anfrage nicht verstehen."]);
?>