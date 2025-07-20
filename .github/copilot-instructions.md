# Copilot Instructions for io-broker-pwa

## Projektüberblick

- **io-broker-pwa** ist eine Progressive Web App (PWA) zur Steuerung eines Smart Homes mit ioBroker, Vue 3 und Vite.
- Die App integriert Sprachsteuerung (ChatGPT/OpenAI API), MQTT, Energiemanagement und Geräteverwaltung.
- Backend-Kommunikation erfolgt über PHP-APIs im Verzeichnis `public/api/` und Python-Skripte in `tools/`.

## Architektur & Datenfluss

- **Frontend:**  
  - Vue 3 + Vuetify, Einstiegspunkt: `src/main.js`, Hauptkomponente: `src/App.vue`.
  - Views liegen in `src/views/`, Komponenten in `src/components/` (z.B. `VoiceAssistant.vue` für GPT-Integration).
  - Assets und Styles: `src/assets/`, `src/style.css`.
- **Backend:**  
  - PHP-APIs: `public/api/*.php` (z.B. `devices.php`, `energy-today-ww.php`).
  - Python-Tools: `tools/*.py` (z.B. `bartels_abfrage.py` für externe Datenabfrage und SQLite-Export).
  - Daten werden als JSON und in SQLite (`public/api/Von_Bartels_Daten/`) abgelegt.

## Build & Deployment

- **Build:**  
  - `npm run build` erzeugt die Produktionsdateien im `dist/`-Verzeichnis.
  - Das Script `build.sh` kopiert die gebauten Dateien nach `public/` und leert die Asset-Ordner.
- **Entwicklung:**  
  - `npm run dev` startet Vite-Dev-Server.
  - Basis-URL ist `/iobroker/` (siehe `vite.config.js`).

## Konventionen & Besonderheiten

- **API-Kommunikation:**  
  - Frontend ruft PHP-APIs unter `/iobroker/api/` auf.
  - Python-Skripte speichern Daten als JSON und SQLite, werden meist per Cron ausgeführt.
  - SSL-Validierung kann bei externen APIs (z.B. `bartels_abfrage.py`) deaktiviert sein (`verify=False`), da die Zertifikatskette des Fremdservers fehlerhaft ist.
- **MQTT:**  
  - Integration über ioBroker, MQTT-Backend nicht im Repo enthalten.
- **Sprachsteuerung:**  
  - GPT-Integration in `VoiceAssistant.vue`, API-Key/Config nicht im Repo.
- **UI:**  
  - Vuetify mit Custom Theme (`myTheme` in `main.js`).
  - Navigation über Tabs und Drawer (`App.vue`).

## Beispiele für typische Workflows

- **Neues Gerät einbinden:**  
  - Gerät in `devices.json` ergänzen, API in `public/api/devices.php` anpassen.
- **Neue Ansicht:**  
  - View in `src/views/` anlegen, in `App.vue` und Navigation einbinden.
- **Datenabfrage von extern:**  
  - Python-Skript in `tools/` schreiben, Ergebnis als JSON/SQLite in `public/api/Von_Bartels_Daten/` speichern.

## Wichtige Dateien & Verzeichnisse

- `src/` – Frontend-Quellcode
- `public/api/` – PHP-APIs und Datenablage
- `tools/` – Python- und PHP-Tools für Datenimport
- `build.sh` – Build- und Deployment-Script
- `vite.config.js` – Vite-Konfiguration

---

**Feedback:**  
Bitte prüfe, ob alle wichtigen Workflows und Besonderheiten abgedeckt sind. Fehlt etwas Spezifisches (z.B. Teststrategie, Deployment-Details, API-Security)? Gib Bescheid, damit die Anleitung weiter optimiert werden kann!
