<?php
//  settings.php
// API zum Lesen und Schreiben der PV-Heizstab-Settings

$settingsFile = __DIR__ . '/../../data/settings.json';
header('Content-Type: application/json');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (file_exists($settingsFile)) {
            $json = file_get_contents($settingsFile);
            $data = json_decode($json, true);
            if (isset($data['pv_settings'])) {
                echo json_encode($data);
            } else {
                // Falls noch altes Format, migrieren
                $data = [
                    'pv_settings' => [
                        'pvThreshold' => $data['pvThreshold'] ?? 3500,
                        'tempMin' => $data['tempMin'] ?? 50,
                        'tempMax' => $data['tempMax'] ?? 60
                    ]
                ];
                file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT));
                echo json_encode($data);
            }
        } else {
            echo json_encode([
                'pv_settings' => [
                    'pvThreshold' => 3500,
                    'tempMin' => 50,
                    'tempMax' => 60
                ]
            ]);
        }
        break;
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        $allSettings = [];
        if (file_exists($settingsFile)) {
            $allSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }
        // PV Settings
        $allSettings['pv_settings'] = [
            'pvThreshold' => isset($input['pvThreshold']) ? (int)$input['pvThreshold'] : ($allSettings['pv_settings']['pvThreshold'] ?? 3500),
            'tempMin' => isset($input['tempMin']) ? (int)$input['tempMin'] : ($allSettings['pv_settings']['tempMin'] ?? 50),
            'tempMax' => isset($input['tempMax']) ? (int)$input['tempMax'] : ($allSettings['pv_settings']['tempMax'] ?? 60)
        ];
        // General Settings
        $allSettings['general'] = [
            'darkTheme' => isset($input['darkTheme']) ? (bool)$input['darkTheme'] : ($allSettings['general']['darkTheme'] ?? false),
            'notifications' => isset($input['notifications']) ? (bool)$input['notifications'] : ($allSettings['general']['notifications'] ?? false)
        ];
        file_put_contents($settingsFile, json_encode($allSettings, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
