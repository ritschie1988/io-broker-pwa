import requests
import time
import json
import os
import sqlite3

# Login-Daten
USERNAME = 'ritschie1988'
PASSWORD = 'Nixisu64?'

# Webserver-Zielpfad-Daten
json_dir_data = '/var/www/html/progpfad/io-broker-pwa/public/api/Von_Bartels_Daten'
os.makedirs(json_dir_data, exist_ok=True)

# SQLite-Datenbank-Pfad
db_path = os.path.join(json_dir_data, 'bartels_data.db')

 # Login
session = requests.Session()
login_data = {
    'login': '1',
    'user_name': USERNAME,
    'user_password': PASSWORD,

    'user_rememberme': '1'
}

login_resp = session.post('https://energycenter.vonbartels.de/index.php', data=login_data, verify=False)
print("Login-Status:", login_resp.status_code)

print("Login-Antwort:", login_resp.text[:500])  # nur ein Ausschnitt



# Prüfe, ob der Login erfolgreich war (z.B. Logout-Link vorhanden)
if ("Logout" in login_resp.text) or ("Abmelden" in login_resp.text):
    print("✓ Login erfolgreich! Logout-Link gefunden.")
else:
    print("✗ Login vermutlich fehlgeschlagen! Kein Logout-Link im Antworttext gefunden.")


# Daten abrufen
timestamp = int(time.time() * 1000)
url = f'https://energycenter.vonbartels.de/requests/requestCurrentStatus.php?cc={timestamp}'

headers = {
    'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/103.0.0.0 Safari/537.36'
}


response = session.get(url, headers=headers)
print("Statuscode:", response.status_code)
print("Antworttext:", response.text[:500])  # erste 500 Zeichen


status_path = os.path.join(json_dir_data, 'status.json')
if response.status_code == 200:
    try:
        data = response.json()
        # Werte extrahieren
        zeit = data['benutzer'][3]  # Zeitstempel als String
        sensor1 = int(data['sensoren'][1])
        sensor2 = int(data['sensoren'][2])
        sensor3 = int(data['sensoren'][3])
        sensor4 = int(data['sensoren'][4])
        relay1 = int(data['aktoren'][1])  # 1. Wert nach Datum unter "aktoren"

        # Prüfe, ob Zeitstempel älter als 30 Minuten ist
        from datetime import datetime, timedelta
        try:
            zeit_dt = datetime.strptime(zeit, "%Y-%m-%d %H:%M:%S")
            now = datetime.now()
            server_alive = (now - zeit_dt) <= timedelta(minutes=30)
        except Exception as e:
            server_alive = False

        # Status OK schreiben
        with open(status_path, 'w', encoding='utf-8') as f:
            json.dump({"ok": True, "timestamp": zeit, "server_alive": server_alive}, f)
    except Exception as e:
        print("Fehler beim Verarbeiten der Daten:", e)
        with open(status_path, 'w', encoding='utf-8') as f:
            json.dump({"ok": False, "error": str(e)}, f)
        exit(1)
else:
    print("Datenabruf fehlgeschlagen! Skript wird beendet.")
    with open(status_path, 'w', encoding='utf-8') as f:
        json.dump({"ok": False, "error": f"Statuscode: {response.status_code}"}, f)
    exit(1)

# In SQLite speichern
conn = sqlite3.connect(db_path)
c = conn.cursor()


# Tabelle anlegen (wenn nicht vorhanden)
c.execute('''
CREATE TABLE IF NOT EXISTS sensordaten (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    zeit TEXT,
    sensor1 INTEGER,
    sensor2 INTEGER,
    sensor3 INTEGER,
    sensor4 INTEGER,
    relay1 INTEGER
)
''')

# Datensatz einfügen
c.execute('''
INSERT INTO sensordaten (zeit, sensor1, sensor2, sensor3, sensor4, relay1)
VALUES (?, ?, ?, ?, ?, ?)
''', (zeit, sensor1, sensor2, sensor3, sensor4, relay1))

conn.commit()
conn.close()

# JSON sichern
with open(os.path.join(json_dir_data, 'letzte_daten.json'), 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("✓ Daten erfolgreich in SQLite gespeichert und JSON-Backup angelegt.")
