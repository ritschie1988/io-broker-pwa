# ioBroker PWA - Smart Home Dashboard

⚠️ **Wichtiger Hinweis**: Dies ist mein persönliches Smart Home Setup. Der Code ist spezifisch für meine Hardware-Konfiguration und kann nicht direkt übernommen werden. Das Repository dient als Inspiration und Referenz für andere ioBroker-Nutzer.

## 📋 Über dieses Projekt

Eine progressive Web-App zur Steuerung meines Smart Homes auf Basis von ioBroker mit GPT-Sprachintegration. Das Projekt zeigt moderne Implementierungsansätze für:
- PWA + ioBroker Integration
- OpenAI API Einbindung in Smart Home Systeme  
- Vue.js basierte Smart Home Oberflächen
- MQTT-basierte Gerätesteuerung

## ✨ Features

- 🎤 **Sprachsteuerung** über ChatGPT API
- 💡 **Lichtsteuerung** aller Räume
- 🌡️ **Heizungssteuerung** (Kessel, Vorlaufpumpe, Thermostatventile)
- 🏠 **Raumthermostate** individuell einstellbar
- 🌅 **Rolllädensteuerung** (Wohn- & Schlafzimmer – in Planung)
- ☀️ **Solaranlagen-Kontrolle** (Warmwasser & Speicherheizstab)
- ⚡ **PV-Statusanzeige**
- 📱 **PWA-fähig** (offline nutzbar, App-Installation möglich)

## 🛠️ Tech Stack

- **Frontend**: Vite + Vue 3 + TailwindCSS
- **Backend**: ioBroker (MQTT)
- **KI-Integration**: OpenAI API (GPT)
- **Persistierung**: LocalStorage & IndexedDB
- **Offline**: Progressive Web App (PWA)

## 🏗️ Architektur

### ioBroker Adapter (mein Setup)
⚠️ **Hinweis**: Die verwendeten Adapter sind spezifisch für meine Hardware-Konfiguration.
Typischerweise verwendet:
- **MQTT Adapter** - Hauptkommunikation mit der PWA
- Weitere Adapter je nach verwendeter Hardware (Modbus, Shelly, etc.)

### MQTT Topic Schema (Beispiele)
```
home/living-room/lights/main → {state: true/false, brightness: 0-100}
home/heating/boiler/temperature → 45.2
home/solar/hot-water/temperature → 62.1
home/blinds/living-room → {position: 0-100, tilt: 0-100}
```

### Komponenten-Struktur
```
⚠️ Die genaue Verzeichnisstruktur ist projektspezifisch.
Typische Vue 3 + Vite Struktur wird verwendet.
```

## 🔧 Lokale Entwicklung

Für eigene Anpassungen und Experimente:

```bash
# Repository klonen
git clone https://github.com/ritschie1988/io-broker-pwa
cd io-broker-pwa

# Dependencies installieren
npm install

# Konfiguration anpassen (siehe unten)
cp src/config.example.js src/config.js

# Development Server starten
npm run dev
```

### Konfiguration

Erstellen Sie eine `.env` Datei im Projektroot:

```bash
# .env (nicht in Git!)
VITE_MQTT_HOST=your-iobroker-ip
VITE_MQTT_PORT=1883
VITE_MQTT_USERNAME=your-mqtt-user
VITE_MQTT_PASSWORD=your-mqtt-password

# ⚠️ OpenAI API-Key sollte über Backend-Proxy laufen
# VITE_OPENAI_API_KEY=sk-...
```

**Wichtig**: 
- `.env` zur `.gitignore` hinzufügen
- Erstellen Sie eine `.env.example` mit Platzhaltern für andere Entwickler
- Nutzen Sie `import.meta.env.VITE_*` in Ihrem Vue-Code

## 🔒 Sicherheitshinweise

- **OpenAI API-Key**: Implementieren Sie einen Backend-Proxy! Nie API-Keys im Frontend-Code
- **MQTT**: Nutzen Sie Authentifizierung und SSL/TLS-Verschlüsselung
- **Netzwerk**: Firewall-Regeln für ioBroker-Zugriff beachten

## 🎯 Roadmap

- [ ] Rollladensteuerung implementieren
- [ ] Energiemanagement-Dashboard (Verbrauch, Einspeisung, Autarkie)
- [ ] Benutzerverwaltung mit Rollen
- [ ] UI-Polishing & Dark Mode
- [ ] Push-Benachrichtigungen
- [ ] Gruppierung nach Räumen/Zonen

## 🚀 Eigene Implementierung

Wenn Sie etwas Ähnliches bauen möchten:

1. **Analysieren Sie die Struktur**: Schauen Sie sich die Komponenten-Aufteilung an
2. **MQTT-Topics anpassen**: Passen Sie alle Topics an Ihr ioBroker-Setup an
3. **Geräte-Definitionen**: Modifizieren Sie die Gerätekonfiguration
4. **OpenAI-Prompts**: Anpassen der Sprachbefehle an Ihre Geräte
5. **UI-Anpassungen**: TailwindCSS-Klassen für Ihr Design anpassen

### Hilfreiche Code-Bereiche als Referenz

⚠️ **Hinweis**: Die tatsächliche Dateistruktur kann vom Standard abweichen.
Schauen Sie sich den Source-Code für die spezifische Implementierung an.

Typische Bereiche in Vue.js PWA-Projekten:
- MQTT-Service für ioBroker-Kommunikation
- OpenAI API Integration für Sprachbefehle
- State Management für Gerätezustände
- PWA-Konfiguration (Service Worker, Manifest)

## 📸 Screenshots

*(Hier könnten später Screenshots oder GIFs der Benutzeroberfläche eingefügt werden)*

## 🤝 Beitragen

Da dies ein persönliches Setup ist, sind Pull Requests begrenzt sinnvoll. Aber:
- **Issues** für Fragen oder Diskussionen sind willkommen
- **Forks** für eigene Anpassungen erwünscht
- **Erfahrungsaustausch** in den Discussions

## 📄 Lizenz

MIT License - Nutzen Sie den Code gerne als Basis für eigene Projekte.

## 📧 Kontakt

**Richard Gruber**  
📧 ritschie1988@hotmail.com  
🐙 github.com/ritschie1988

---

*Dieses Projekt entstand aus dem Wunsch nach einer modernen, sprachgesteuerten Smart Home Oberfläche. Es zeigt, wie sich aktuelle Web-Technologien elegant mit bewährten Smart Home Systemen verbinden lassen.*
