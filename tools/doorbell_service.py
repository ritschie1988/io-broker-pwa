#!/usr/bin/env python3
"""
ESP32-CAM Doorbell Service für Raspberry Pi
Datei: /var/www/html/progpfad/io-broker-pwa/tools/doorbell_service.py
Daten: /var/www/html/progpfad/io-broker-pwa/data/doorbell/
"""

import cv2
import numpy as np
import threading
import time
import sqlite3
from datetime import datetime
from flask import Flask, jsonify, request, Response, send_file
from flask_cors import CORS
from pathlib import Path
import logging
import zipfile
import requests
from contextlib import contextmanager
from contextlib import contextmanager

# Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger('DoorbellService')

# Konfiguration - HIER ANPASSEN!
ESP32_CAM_IP = "10.0.0.109"  # Deine ESP32-CAM IP

# Pfade (Script liegt in /tools/, Daten in /data/)
SCRIPT_PATH = Path(__file__).parent  # /tools/
PROJECT_ROOT = SCRIPT_PATH.parent    # /var/www/html/progpfad/io-broker-pwa/
DATA_PATH = PROJECT_ROOT / "data" / "doorbell"
IMAGES_PATH = DATA_PATH / "images"
DB_PATH = DATA_PATH / "doorbell.db"

class DoorbellService:
    def __init__(self):
        self.setup_paths()
        self.setup_database()
        
        # State
        self.stream_active = False
        self.current_frame = None
        self.frame_lock = threading.Lock()
        
        # Camera Request Management
        self.camera_lock = threading.Lock()
        self.last_camera_access = 0
        self.min_request_interval = 0.5  # Mindestens 500ms zwischen Requests
        self.camera_timeout = 5  # 5 Sekunden Timeout für Requests
        
        # Frame-Stabilisierung für konsistentes Cropping
        self.stable_crop = None
        self.crop_history = []
        self.crop_history_size = 5  # Über 5 Frames glätten
        
        # Settings
        self.settings = {
            'enabled': True,
            'sensitivity': 7,
            'captureDelay': 5,
            'pushToken': ''
        }
        
        logger.info("DoorbellService gestartet")
        logger.info(f"Daten-Pfad: {DATA_PATH}")

    def setup_paths(self):
        """Verzeichnisse erstellen"""
        DATA_PATH.mkdir(parents=True, exist_ok=True)
        IMAGES_PATH.mkdir(exist_ok=True)
        logger.info(f"Verzeichnisse erstellt: {DATA_PATH}")

    def setup_database(self):
        """SQLite Datenbank erstellen"""
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS alarms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            confidence REAL NOT NULL,
            image_count INTEGER DEFAULT 0,
            images_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        ''')
        
        conn.commit()
        conn.close()
        logger.info(f"Datenbank bereit: {DB_PATH}")

    def get_esp32_stream_url(self):
        """ESP32-CAM Stream URL"""
        return f"http://{ESP32_CAM_IP}/stream"

    @contextmanager
    def camera_request(self):
        """Context Manager für ESP32-CAM Requests mit Timing"""
        with self.camera_lock:
            # Warten zwischen Requests
            elapsed = time.time() - self.last_camera_access
            if elapsed < self.min_request_interval:
                time.sleep(self.min_request_interval - elapsed)
            
            try:
                yield
            finally:
                self.last_camera_access = time.time()

    def check_camera_online(self):
        """ESP32-CAM Status prüfen mit /capture endpoint"""
        try:
            with self.camera_request():
                response = requests.get(
                    f"http://{ESP32_CAM_IP}/capture",
                    timeout=2,  # Kurzer Timeout
                    stream=False
                )
                is_online = (response.status_code == 200 and 
                           'image/jpeg' in response.headers.get('content-type', ''))
                logger.debug(f"Kamera-Check: {response.status_code}, online={is_online}")
                return is_online
        except Exception as e:
            logger.warning(f"Kamera-Check fehlgeschlagen: {e}")
            return False

    def capture_single_image(self):
        """Einzelbild von ESP32-CAM über /capture endpoint"""
        try:
            with self.camera_request():
                # ESP32-CAM /capture endpoint für Einzelbilder
                response = requests.get(
                    f"http://{ESP32_CAM_IP}/capture",
                    timeout=self.camera_timeout,
                    stream=False  # Einzelbild, kein Stream
                )
                
                if response.status_code == 200:
                    return response.content
                else:
                    logger.warning(f"/capture failed ({response.status_code})")
                    return None
                    
        except Exception as e:
            logger.error(f"Capture-Fehler: {e}")
            return None

    def get_stream_frame(self):
        """EINZELNEN Frame vom /stream holen (nicht dauerhaft)"""
        try:
            with self.camera_request():
                response = requests.get(
                    f"http://{ESP32_CAM_IP}/stream",
                    timeout=3,  # Kurzer Timeout
                    stream=True
                )
                
                if response.status_code == 200:
                    # Nur ERSTEN Frame lesen mit Timeout
                    bytes_data = b''
                    start_time = time.time()
                    max_wait = 3  # 3 Sekunden max warten
                    
                    for chunk in response.iter_content(chunk_size=1024, decode_unicode=False):
                        if time.time() - start_time > max_wait:
                            logger.warning("Stream-Frame Timeout")
                            break
                            
                        bytes_data += chunk
                        
                        # MJPEG Frame extrahieren
                        a = bytes_data.find(b'\xff\xd8')  # JPEG Start
                        b = bytes_data.find(b'\xff\xd9')  # JPEG End
                        
                        if a != -1 and b != -1:
                            jpg = bytes_data[a:b+2]
                            response.close()
                            logger.debug(f"Stream-Frame erhalten: {len(jpg)} bytes")
                            return jpg
                    
                    response.close()
                return None
        except Exception as e:
            logger.error(f"Stream-Frame-Fehler: {e}")
            return None

    def process_frame(self, frame):
        """90° im Uhrzeigersinn drehen + Schwarzrand-Entfernung + 4:3 Querformat"""
        # 90° im Uhrzeigersinn drehen
        rotated = cv2.rotate(frame, cv2.ROTATE_90_CLOCKWISE)
        
        # Aggressivere Schwarz-Erkennung für mehr Cropping
        gray = cv2.cvtColor(rotated, cv2.COLOR_BGR2GRAY)
        
        # Niedrigerer Threshold (35) für aggressivere Erkennung
        _, thresh = cv2.threshold(gray, 35, 255, cv2.THRESH_BINARY)
        
        # Stärkere morphologische Operationen
        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (4, 4))
        thresh = cv2.morphologyEx(thresh, cv2.MORPH_CLOSE, kernel)
        thresh = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, kernel)
        
        # Finde Konturen der nicht-schwarzen Bereiche
        contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        
        if contours:
            # Größte Kontur finden
            largest_contour = max(contours, key=cv2.contourArea)
            x, y, w, h = cv2.boundingRect(largest_contour)
            
            # Kleinere Margins für aggressiveres Cropping
            margin_x = max(8, int(w * 0.025))  # 2.5% Margin
            margin_y = max(8, int(h * 0.025))
            
            # Neue Crop-Grenzen berechnen
            img_h, img_w = rotated.shape[:2]
            new_crop = {
                'x': max(0, x - margin_x),
                'y': max(0, y - margin_y),
                'w': min(img_w - max(0, x - margin_x), w + 2 * margin_x),
                'h': min(img_h - max(0, y - margin_y), h + 2 * margin_y)
            }
            
            # Stabilisierung
            self.crop_history.append(new_crop)
            if len(self.crop_history) > 3:
                self.crop_history.pop(0)
            
            # 70% neuer Wert, 30% Historie für schnellere Anpassung
            if len(self.crop_history) >= 2 and self.stable_crop:
                avg_x = int(0.7 * new_crop['x'] + 0.3 * self.stable_crop['x'])
                avg_y = int(0.7 * new_crop['y'] + 0.3 * self.stable_crop['y'])
                avg_w = int(0.7 * new_crop['w'] + 0.3 * self.stable_crop['w'])
                avg_h = int(0.7 * new_crop['h'] + 0.3 * self.stable_crop['h'])
                
                self.stable_crop = {'x': avg_x, 'y': avg_y, 'w': avg_w, 'h': avg_h}
            else:
                self.stable_crop = new_crop
            
            # Anwenden der Crop-Grenzen
            crop = self.stable_crop
            cropped = rotated[crop['y']:crop['y']+crop['h'], crop['x']:crop['x']+crop['w']]
        else:
            # Fallback: Aggressiveres Center Crop (87% statt 90%)
            h, w = rotated.shape[:2]
            crop_w = int(w * 0.87)
            crop_h = int(h * 0.87)
            
            start_x = (w - crop_w) // 2
            start_y = (h - crop_h) // 2
            
            cropped = rotated[start_y:start_y+crop_h, start_x:start_x+crop_w]
        
        # WICHTIG: Auf 4:3 Querformat zuschneiden (Breite:Höhe = 4:3)
        # Interessanter Bereich ist etwas oberhalb der Mitte
        current_h, current_w = cropped.shape[:2]
        target_aspect = 4.0 / 3.0  # 4:3 Querformat
        current_aspect = current_w / current_h
        
        if current_aspect > target_aspect:
            # Bild ist zu breit -> Breite reduzieren (Center-Crop horizontal)
            new_w = int(current_h * target_aspect)
            start_x = (current_w - new_w) // 2
            final_crop = cropped[:, start_x:start_x + new_w]
        else:
            # Bild ist zu hoch -> Höhe reduzieren (Crop vom oberen Bildbereich)
            new_h = int(current_w / target_aspect)
            # Statt Center: 40% von oben, 60% von unten
            # Das verschiebt den Crop-Bereich nach oben wo der interessante Bereich ist
            start_y = int(current_h * 0.25)  # Beginne bei 25% von oben
            # Sicherstellen dass genug Platz für new_h vorhanden ist
            if start_y + new_h > current_h:
                start_y = current_h - new_h
            if start_y < 0:
                start_y = 0
            final_crop = cropped[start_y:start_y + new_h, :]
        
        return final_crop

    def stream_processor(self):
        """DEAKTIVIERT - Kein dauerhafter Stream mehr"""
        logger.info("Stream Processor deaktiviert - verwende Einzelbild-Requests")
        # Kein dauerhafter Stream mehr - die Kamera wird nur bei Bedarf angefragt

    def start_stream(self):
        """Stream-Modus aktivieren (ohne dauerhaften Stream)"""
        self.stream_active = True
        # Crop-Historie zurücksetzen für frische Erkennung
        self.crop_history = []
        self.stable_crop = None
        logger.info("Stream-Modus aktiviert (Einzelbild-Requests) - Crop-Historie zurückgesetzt")

    def stop_stream(self):
        """Stream-Modus deaktivieren und Crop-Historie zurücksetzen"""
        self.stream_active = False
        # Crop-Historie zurücksetzen für nächsten Stream-Start
        self.crop_history = []
        self.stable_crop = None
        logger.info("Stream-Modus deaktiviert - Crop-Historie zurückgesetzt")

    def trigger_test_alarm(self):
        """Test-Alarm mit Einzelbild-Capture"""
        logger.info("Test-Alarm wird ausgelöst...")
        
        # Test-Bilder erstellen
        timestamp = datetime.now().isoformat()
        alarm_dir = IMAGES_PATH / timestamp.replace(':', '-')
        alarm_dir.mkdir(exist_ok=True)
        
        # 3 Test-Bilder über 3 Sekunden (weniger wegen Request-Timing)
        images = []
        for i in range(3):
            image_data = self.capture_single_image()
            if image_data:
                filename = f"test_{i:03d}.jpg"
                filepath = alarm_dir / filename
                with open(filepath, 'wb') as f:
                    f.write(image_data)
                images.append(filename)
                logger.info(f"Test-Bild {i+1}/3 gespeichert")
            else:
                logger.warning(f"Test-Bild {i+1}/3 konnte nicht aufgenommen werden")
            
            if i < 2:  # Nicht nach dem letzten Bild warten
                time.sleep(1)
        
        # In Datenbank speichern
        alarm_id = self.save_alarm(timestamp, 0.9, len(images), alarm_dir.name)
        logger.info(f"Test-Alarm erstellt: ID {alarm_id}, {len(images)} Bilder")
        return True

    def save_alarm(self, timestamp, confidence, image_count, images_path):
        """Alarm in Datenbank speichern"""
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        INSERT INTO alarms (timestamp, confidence, image_count, images_path)
        VALUES (?, ?, ?, ?)
        ''', (timestamp, confidence, image_count, images_path))
        
        alarm_id = cursor.lastrowid
        conn.commit()
        conn.close()
        return alarm_id

    def get_alarms(self, page=1, limit=10):
        """Alarme aus Datenbank laden"""
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        offset = (page - 1) * limit
        
        cursor.execute('''
        SELECT id, timestamp, confidence, image_count, images_path
        FROM alarms 
        ORDER BY timestamp DESC 
        LIMIT ? OFFSET ?
        ''', (limit, offset))
        
        alarms = []
        for row in cursor.fetchall():
            alarms.append({
                'id': row[0],
                'timestamp': row[1],
                'confidence': row[2],
                'imageCount': row[3],
                'imagesPath': row[4]
            })
        
        # Prüfen ob mehr Daten vorhanden
        cursor.execute('SELECT COUNT(*) FROM alarms')
        total = cursor.fetchone()[0]
        has_more = (page * limit) < total
        
        conn.close()
        return {'alarms': alarms, 'hasMore': has_more}

    def get_alarm_images(self, alarm_id):
        """Bilder für Alarm laden"""
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('SELECT images_path FROM alarms WHERE id = ?', (alarm_id,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return {'images': []}
        
        images_dir = IMAGES_PATH / row[0]
        if not images_dir.exists():
            return {'images': []}
        
        images = []
        for img_file in sorted(images_dir.glob('*.jpg')):
            images.append(f"{row[0]}/{img_file.name}")
        
        return {'images': images}

    def create_alarm_zip(self, alarm_id):
        """ZIP-Datei mit Alarm-Bildern erstellen"""
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('SELECT timestamp, images_path FROM alarms WHERE id = ?', (alarm_id,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return None
        
        images_dir = IMAGES_PATH / row[1]
        if not images_dir.exists():
            return None
        
        # ZIP erstellen
        zip_path = DATA_PATH / f"alarm_{alarm_id}.zip"
        
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for img_file in images_dir.glob('*.jpg'):
                zipf.write(img_file, img_file.name)
        
        return zip_path

# Flask App erstellen
service = DoorbellService()
app = Flask(__name__)
CORS(app)

# ============ API ENDPOINTS ============

@app.route('/api/doorbell/status')
def get_status():
    """Status ohne dauerhafte Stream-Verbindung"""
    try:
        # Schneller Online-Check ohne Stream
        online = service.check_camera_online()
        
        return jsonify({
            'online': online,
            'stream_active': service.stream_active,
            'timestamp': datetime.now().isoformat(),
            'settings': service.settings,
            'esp32_cam_ip': ESP32_CAM_IP,
            'data_path': str(DATA_PATH)
        })
    except Exception as e:
        return jsonify({'error': str(e), 'online': False}), 500

@app.route('/api/doorbell/stream/start', methods=['POST'])
def start_stream():
    """Stream-URL zurückgeben (Frontend macht periodische Requests)"""
    try:
        # SOFORTIGE Antwort ohne Camera-Check - Check wird beim ersten Frame gemacht
        service.start_stream()
        
        # Stream-URL für periodische Einzelbilder zurückgeben
        stream_url = f"/iobroker/api/doorbell.php?endpoint=stream/live"
        
        logger.info("Stream-Start: Sofortige Antwort an Frontend")
        
        return jsonify({
            'success': True,
            'streamUrl': stream_url,
            'message': 'Stream-Modus aktiviert (Camera-Check beim ersten Frame)'
        })
    except Exception as e:
        logger.error(f"Stream start error: {e}")
        return jsonify({'error': str(e), 'success': False}), 500

@app.route('/api/doorbell/stream/stop', methods=['POST'])
def stop_stream():
    service.stop_stream()
    return jsonify({'success': True})

@app.route('/api/doorbell/stream/live')
def stream_live():
    """MJPEG Video-Stream von ESP32-CAM weiterleiten - SICHER"""
    try:
        if not service.stream_active:
            return jsonify({'error': 'Stream nicht aktiv'}), 503
        
        # Prüfen ob Kamera erreichbar ist
        if not service.check_camera_online():
            return jsonify({'error': 'Kamera nicht erreichbar'}), 503
        
        def generate_stream():
            """Generator für MJPEG Stream von ESP32-CAM mit Bildbearbeitung"""
            connection = None
            try:
                with service.camera_request():
                    # Kurze Timeout für ESP32-CAM Stream
                    connection = requests.get(
                        f"http://{ESP32_CAM_IP}/stream",
                        stream=True,
                        timeout=10
                    )
                    
                    if connection.status_code == 200:
                        logger.info("ESP32-CAM Stream verbunden - starte Bildbearbeitung")
                        
                        # MJPEG Stream Parser
                        buffer = b""
                        for chunk in connection.iter_content(chunk_size=512):
                            if not chunk:
                                continue
                                
                            buffer += chunk
                            
                            # Suche nach JPEG Bild im Stream
                            while b'\xff\xd8' in buffer and b'\xff\xd9' in buffer:
                                # Finde JPEG Start und Ende
                                start = buffer.find(b'\xff\xd8')
                                end = buffer.find(b'\xff\xd9', start) + 2
                                
                                if end > start:
                                    # Extrahiere JPEG Bild
                                    jpeg_data = buffer[start:end]
                                    buffer = buffer[end:]
                                    
                                    try:
                                        # Decode JPEG
                                        frame = cv2.imdecode(np.frombuffer(jpeg_data, dtype=np.uint8), cv2.IMREAD_COLOR)
                                        if frame is not None:
                                            # Bildbearbeitung anwenden
                                            processed_frame = service.process_frame(frame)
                                            
                                            # Zurück zu JPEG encodieren
                                            _, encoded = cv2.imencode('.jpg', processed_frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
                                            
                                            # MJPEG Frame Format erstellen
                                            yield (b'--frame\r\n'
                                                   b'Content-Type: image/jpeg\r\n'
                                                   b'Content-Length: ' + str(len(encoded)).encode() + b'\r\n\r\n' +
                                                   encoded.tobytes() + b'\r\n')
                                    except Exception as e:
                                        logger.warning(f"Frame processing error: {e}")
                                        continue
                                else:
                                    break
                            
                            # Sicherheitscheck: Stream aktiv?
                            if not service.stream_active:
                                logger.info("Stream deaktiviert, beende Generator")
                                break
                    else:
                        logger.error(f"ESP32-CAM Stream Fehler: {connection.status_code}")
                        yield b"--frame\r\nContent-Type: text/plain\r\n\r\nStream Fehler\r\n"
                        
            except requests.exceptions.Timeout:
                logger.warning("ESP32-CAM Stream Timeout")
                yield b"--frame\r\nContent-Type: text/plain\r\n\r\nTimeout\r\n"
            except Exception as e:
                logger.error(f"Stream Generator Fehler: {e}")
                yield b"--frame\r\nContent-Type: text/plain\r\n\r\nVerbindungsfehler\r\n"
            finally:
                if connection:
                    connection.close()
                logger.info("ESP32-CAM Stream Verbindung geschlossen")
        
        return Response(
            generate_stream(),
            mimetype='multipart/x-mixed-replace; boundary=frame',
            headers={
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0',
                'Access-Control-Allow-Origin': '*'
            }
        )
        
    except Exception as e:
        logger.error(f"Stream live error: {e}")
        return jsonify({'error': str(e)}), 500
        
    except Exception as e:
        logger.error(f"Stream live error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/settings', methods=['POST'])
def update_settings():
    try:
        settings = request.json
        service.settings.update(settings)
        logger.info(f"Einstellungen aktualisiert: {settings}")
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/doorbell/test-alarm', methods=['POST'])
def test_alarm():
    try:
        service.trigger_test_alarm()
        return jsonify({'success': True})
    except Exception as e:
        logger.error(f"Test alarm error: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/doorbell/alarms')
def get_alarms():
    try:
        page = int(request.args.get('page', 1))
        limit = int(request.args.get('limit', 10))
        
        result = service.get_alarms(page, limit)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/alarms/<int:alarm_id>/images')
def get_alarm_images(alarm_id):
    try:
        result = service.get_alarm_images(alarm_id)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/alarms/<int:alarm_id>/download')
def download_alarm(alarm_id):
    try:
        zip_path = service.create_alarm_zip(alarm_id)
        if zip_path and zip_path.exists():
            return send_file(str(zip_path), as_attachment=True)
        else:
            return jsonify({'error': 'Alarm nicht gefunden'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/images/<path:image_path>')
def get_image(image_path):
    try:
        full_path = IMAGES_PATH / image_path
        if full_path.exists() and full_path.suffix.lower() in ['.jpg', '.jpeg']:
            return send_file(str(full_path))
        else:
            return jsonify({'error': 'Bild nicht gefunden'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    # ESP32-CAM IP als Parameter
    import sys
    if len(sys.argv) > 1:
        ESP32_CAM_IP = sys.argv[1]
        logger.info(f"ESP32-CAM IP aus Parameter: {ESP32_CAM_IP}")
    
    # Service NICHT automatisch starten - nur bei Bedarf
    # service.start_stream()
    
    logger.info("=" * 50)
    logger.info("Doorbell Service startet auf Port 5000")
    logger.info(f"ESP32-CAM IP: {ESP32_CAM_IP}")
    logger.info(f"Daten-Pfad: {DATA_PATH}")
    logger.info("Stream-Modus: On-Demand (keine dauerhafte Verbindung)")
    logger.info("=" * 50)
    
    app.run(host='0.0.0.0', port=5000, debug=False)