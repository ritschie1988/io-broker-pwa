#!/usr/bin/env python3

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
import base64
import json
import urllib.request
import os
from contextlib import contextmanager

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger('DoorbellService')

# Pfade
SCRIPT_PATH = Path(__file__).parent
PROJECT_ROOT = SCRIPT_PATH.parent
DATA_PATH = PROJECT_ROOT / "data" / "doorbell"
IMAGES_PATH = DATA_PATH / "images"
DB_PATH = DATA_PATH / "doorbell.db"
MODELS_PATH = DATA_PATH / "models"

class DoorbellService:
    def __init__(self):
        self.setup_paths()
        self.setup_database()
        self.setup_yolo()
        
        logger.info("DoorbellService mit echter YOLO Person Detection gestartet")

    def setup_paths(self):
        DATA_PATH.mkdir(parents=True, exist_ok=True)
        IMAGES_PATH.mkdir(exist_ok=True)
        MODELS_PATH.mkdir(exist_ok=True)

    def setup_yolo(self):
        """Download und Setup von YOLO für Person Detection"""
        try:
            # YOLOv4-tiny mit korrekten URLs
            weights_url = "https://github.com/AlexeyAB/darknet/releases/download/yolov4/yolov4-tiny.weights"
            config_url = "https://raw.githubusercontent.com/AlexeyAB/darknet/master/cfg/yolov4-tiny.cfg"
            names_url = "https://raw.githubusercontent.com/AlexeyAB/darknet/master/data/coco.names"
            
            weights_path = MODELS_PATH / "yolov4-tiny.weights"
            config_path = MODELS_PATH / "yolov4-tiny.cfg"
            names_path = MODELS_PATH / "coco.names"
            
            # Download Modell-Dateien falls nicht vorhanden
            if not weights_path.exists():
                logger.info("Downloading YOLO weights...")
                urllib.request.urlretrieve(weights_url, weights_path)
                logger.info(f"YOLO weights downloaded: {weights_path}")
            
            if not config_path.exists():
                logger.info("Downloading YOLO config...")
                urllib.request.urlretrieve(config_url, config_path)
                logger.info(f"YOLO config downloaded: {config_path}")
            
            if not names_path.exists():
                logger.info("Downloading YOLO class names...")
                urllib.request.urlretrieve(names_url, names_path)
                logger.info(f"YOLO names downloaded: {names_path}")
            
            # YOLO Netzwerk laden
            self.net = cv2.dnn.readNet(str(weights_path), str(config_path))
            self.net.setPreferableBackend(cv2.dnn.DNN_BACKEND_OPENCV)
            self.net.setPreferableTarget(cv2.dnn.DNN_TARGET_CPU)
            
            # Ausgabe-Layer Namen
            self.layer_names = self.net.getLayerNames()
            self.output_layers = [self.layer_names[i - 1] for i in self.net.getUnconnectedOutLayers()]
            
            # COCO Klassen laden (Person ist Index 0)
            with open(names_path, 'r') as f:
                self.classes = [line.strip() for line in f.readlines()]
            
            logger.info("YOLO Person Detection erfolgreich initialisiert")
            self.yolo_ready = True
            
        except Exception as e:
            logger.error(f"YOLO Setup fehlgeschlagen: {e}")
            logger.error("YOLO ist erforderlich für Personenerkennung!")
            self.yolo_ready = False

    def setup_database(self):
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS detections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            confidence REAL NOT NULL,
            person_detected INTEGER NOT NULL,
            image_count INTEGER DEFAULT 0,
            images_path TEXT,
            esp32_wakeup_count INTEGER DEFAULT 0,
            detection_type TEXT DEFAULT 'motion',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        ''')
        
        # Prüfe ob esp32_picture_count Spalte existiert, falls nicht erstelle sie
        cursor.execute("PRAGMA table_info(detections)")
        columns = [column[1] for column in cursor.fetchall()]
        
        # Für Kompatibilität mit ESP32-S3 picture_count - verwende esp32_wakeup_count Spalte
        # (esp32_wakeup_count wird jetzt als picture_count interpretiert)
        
        conn.commit()
        conn.close()
        logger.info(f"Datenbank bereit: {DB_PATH}")
        logger.info("ESP32-S3 Firmware Kompatibilität: picture_count -> esp32_wakeup_count")

    def cleanup_old_detections(self):
        # Nur letzte 50 Ereignisse behalten
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        DELETE FROM detections WHERE id NOT IN (
            SELECT id FROM detections ORDER BY created_at DESC LIMIT 50
        )
        ''')
        
        # Alte Bilddateien lÃ¶schen
        cursor.execute('SELECT images_path FROM detections')
        valid_paths = [row[0] for row in cursor.fetchall() if row[0]]
        
        for img_dir in IMAGES_PATH.iterdir():
            if img_dir.is_dir() and img_dir.name not in valid_paths:
                import shutil
                shutil.rmtree(img_dir)
        
        conn.commit()
        conn.close()

    def preprocess_image(self, img_b64):
        """Bild dekodieren und sanft drehen - OHNE aggressive Verarbeitung die Mosaikeffekt verursacht"""
        try:
            # Base64 dekodieren
            img_bytes = base64.b64decode(img_b64)
            img_array = np.frombuffer(img_bytes, dtype=np.uint8)
            img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
            
            if img is None:
                return None
            
            # 90° im Uhrzeigersinn drehen
            img_rotated = cv2.rotate(img, cv2.ROTATE_90_CLOCKWISE)
            
            # MINIMALES Zuschneiden - nur 5% von allen Seiten (statt 20%)
            height, width = img_rotated.shape[:2]
            
            # Nur 5% Rand entfernen um Fisheye-Verzerrung zu reduzieren
            crop_margin_h = int(height * 0.05)  # Nur 5% statt 20%
            crop_margin_w = int(width * 0.05)   # Nur 5% statt 20%
            
            img_cropped = img_rotated[
                crop_margin_h:height-crop_margin_h,
                crop_margin_w:width-crop_margin_w
            ]
            
            # SEHR SANFTE Farbkorrektur - NUR bei extremen Grünstich
            # Verwende einfache Gamma-Korrektur statt HSV-Manipulation
            
            # Prüfe ob Grünstich vorhanden (mittlere Farbwerte)
            b_mean, g_mean, r_mean = cv2.mean(img_cropped)[:3]
            
            # Nur korrigieren wenn Grün deutlich dominiert
            if g_mean > r_mean * 1.2 and g_mean > b_mean * 1.2:
                # Sanfte Grünkorrektur durch selektive Kanal-Anpassung
                img_corrected = img_cropped.copy()
                img_corrected[:, :, 1] = cv2.multiply(img_corrected[:, :, 1], 0.9)  # Grünkanal um 10% reduzieren
                
                logger.info(f"Green tint detected (G:{g_mean:.1f} vs R:{r_mean:.1f}) - applied gentle correction")
            else:
                img_corrected = img_cropped
                logger.info(f"No significant green tint detected (G:{g_mean:.1f} vs R:{r_mean:.1f})")
            
            # MINIMALE Kontrast-/Helligkeitsanpassung
            # Nur sehr sanft anpassen um Details zu erhalten
            alpha = 1.05  # Nur 5% mehr Kontrast (statt 10%)
            beta = 2      # Nur +2 Helligkeit (statt +5)
            img_enhanced = cv2.convertScaleAbs(img_corrected, alpha=alpha, beta=beta)
            
            logger.info(f"Image processed: {width}x{height} -> {img_enhanced.shape[1]}x{img_enhanced.shape[0]} (cropped {crop_margin_w*2}x{crop_margin_h*2}px, minimal processing)")
            
            return img_enhanced
            
        except Exception as e:
            logger.error(f"Image preprocessing error: {e}")
            return None

    def detect_persons_yolo(self, image):
        """YOLO Person Detection - optimiert für ESP32-CAM"""
        try:
            height, width = image.shape[:2]
            
            # Bild für YOLO vorbereiten - kleinere Größe für bessere Performance
            blob = cv2.dnn.blobFromImage(image, 1/255.0, (320, 320), swapRB=True, crop=False)
            self.net.setInput(blob)
            outputs = self.net.forward(self.output_layers)
            
            # Erkennungen verarbeiten
            boxes = []
            confidences = []
            class_ids = []
            
            for output in outputs:
                for detection in output:
                    scores = detection[5:]
                    class_id = np.argmax(scores)
                    confidence = scores[class_id]
                    
                    # Nur Personen (class_id = 0) mit angemessener Confidence
                    if class_id == 0 and confidence > 0.25:  # Niedrigere Schwelle für bessere Erkennung
                        center_x = int(detection[0] * width)
                        center_y = int(detection[1] * height)
                        w = int(detection[2] * width)
                        h = int(detection[3] * height)
                        
                        x = int(center_x - w / 2)
                        y = int(center_y - h / 2)
                        
                        boxes.append([x, y, w, h])
                        confidences.append(float(confidence))
                        class_ids.append(class_id)
            
            # Non-Maximum Suppression
            if len(boxes) > 0:
                indices = cv2.dnn.NMSBoxes(boxes, confidences, 0.25, 0.4)
                if len(indices) > 0:
                    max_confidence = max([confidences[i] for i in indices.flatten()])
                    person_count = len(indices.flatten())
                    logger.info(f"YOLO: {person_count} Person(en) erkannt, max confidence: {max_confidence:.3f}")
                    return True, max_confidence
            
            logger.debug("YOLO: Keine Personen erkannt")
            return False, 0.0
            
        except Exception as e:
            logger.error(f"YOLO detection error: {e}")
            return False, 0.0

    def real_person_detection(self, image_data_list):
        """YOLO Person Detection - OHNE Fallback"""
        if not image_data_list:
            return False, 0.0
        
        if not self.yolo_ready:
            logger.error("YOLO nicht verfügbar - Person Detection nicht möglich!")
            return False, 0.0
        
        try:
            # Erstes Bild für Detection verwenden
            img_b64 = image_data_list[0]
            processed_img = self.preprocess_image(img_b64)
            
            if processed_img is None:
                return False, 0.0
            
            return self.detect_persons_yolo(processed_img)
                
        except Exception as e:
            logger.error(f"Person detection error: {e}")
            return False, 0.0

    def save_detection(self, timestamp, person_detected, confidence, images_data, detection_type="motion", esp32_picture_count=0):
        """Speichert NUR echte Person-Detections in Ordnern - Live Images werden separat behandelt"""
        
        # NUR speichern wenn Person erkannt wurde
        if not person_detected:
            logger.info(f"Keine Person erkannt - Event NICHT gespeichert (Typ: {detection_type})")
            return None
        
        # Cleanup alte Detections
        self.cleanup_old_detections()
        
        # Verzeichnis für Person-Detection erstellen
        detection_id = timestamp.replace(':', '-').replace('T', '_').replace('Z', '')
        detection_dir = IMAGES_PATH / detection_id
        detection_dir.mkdir(exist_ok=True)
        
        # Bilder verarbeiten und speichern (nur bei Person-Detection!)
        saved_images = []
        for i, img_b64 in enumerate(images_data):
            try:
                # Bild verarbeiten
                processed_img = self.preprocess_image(img_b64)
                
                if processed_img is not None:
                    # Verarbeitetes Bild speichern
                    img_filename = f"img_{i:03d}.jpg"
                    img_path = detection_dir / img_filename
                    
                    cv2.imwrite(str(img_path), processed_img)
                    saved_images.append(img_filename)
                    logger.info(f"Person detection image saved: {img_path}")
                else:
                    logger.error(f"Failed to process image {i}")
                
            except Exception as e:
                logger.error(f"Error saving image {i}: {e}")
        
        # In Datenbank speichern - nur bei Person-Detection
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        INSERT INTO detections (timestamp, confidence, person_detected, image_count, images_path, esp32_wakeup_count, detection_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ''', (timestamp, confidence, int(person_detected), len(saved_images), detection_id, esp32_picture_count, detection_type))
        
        detection_db_id = cursor.lastrowid
        conn.commit()
        conn.close()
        
        logger.info(f"Person detection saved: ID={detection_db_id}, Images={len(saved_images)}")
        return detection_db_id

    def update_live_image(self, img_b64):
        """Aktualisiert nur das Live-Bild (live.jpg) - KEINE Ordner oder DB-Einträge"""
        try:
            processed_img = self.preprocess_image(img_b64)
            if processed_img is not None:
                live_path = IMAGES_PATH / "live.jpg"
                cv2.imwrite(str(live_path), processed_img)
                logger.info(f"Live image updated: {live_path}")
                return True
            else:
                logger.error("Failed to process live image")
                return False
        except Exception as e:
            logger.error(f"Error updating live.jpg: {e}")
            return False

    def get_detections(self, limit=50):
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('''
        SELECT id, timestamp, confidence, person_detected, image_count, images_path, detection_type, created_at
        FROM detections 
        ORDER BY created_at DESC 
        LIMIT ?
        ''', (limit,))
        
        detections = []
        for row in cursor.fetchall():
            detections.append({
                'id': row[0],
                'timestamp': row[1],
                'confidence': row[2],
                'person_detected': bool(row[3]),
                'image_count': row[4],
                'images_path': row[5],
                'detection_type': row[6],
                'created_at': row[7]
            })
        
        conn.close()
        return detections

    def get_detection_images(self, detection_id):
        conn = sqlite3.connect(str(DB_PATH))
        cursor = conn.cursor()
        
        cursor.execute('SELECT images_path FROM detections WHERE id = ?', (detection_id,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return []
        
        images_dir = IMAGES_PATH / row[0]
        if not images_dir.exists():
            return []
        
        images = []
        for img_file in sorted(images_dir.glob('*.jpg')):
            images.append(f"{row[0]}/{img_file.name}")
        
        return images

# Flask App
service = DoorbellService()
app = Flask(__name__)
CORS(app)

@app.route('/api/doorbell/person-check', methods=['POST'])
def person_check():
    try:
        data = request.json
        timestamp = data.get('timestamp')
        # Neue ESP32-S3 Firmware sendet picture_count statt wakeup_count
        picture_count = data.get('picture_count', 0)
        detection_type = data.get('type', 'motion')
        
        if detection_type == 'live_image':
            # Live Image Mode - NUR live.jpg aktualisieren, KEINE Ordner oder DB-Einträge
            live_image = data.get('live_image')
            if not live_image:
                return jsonify({'error': 'No live image provided'}), 400
            
            # Nur live.jpg aktualisieren
            success = service.update_live_image(live_image)
            
            return jsonify({
                'person_detected': False,
                'stay_awake': False,
                'confidence': 0.0,
                'live_image_updated': success
            })
        
        else:
            # Motion Detection Mode - ESP32-S3 neue Firmware mit motion_images Array
            motion_images = data.get('motion_images', [])
            if len(motion_images) < 2:
                return jsonify({'error': 'Need at least 2 motion images'}), 400
            
            # Erstes Bild auch als Live-Image speichern
            service.update_live_image(motion_images[0])
            
            # ECHTE YOLO Person Detection
            person_detected, confidence = service.real_person_detection(motion_images)
            
            # Nur bei Person-Erkennung in Ordner speichern
            detection_id = None
            if person_detected:
                detection_id = service.save_detection(
                    timestamp, person_detected, confidence, motion_images, 
                    "motion", picture_count
                )
                logger.info(f"Motion Detection: Person erkannt! Detection ID: {detection_id}")
            else:
                logger.info("Motion Detection: Keine Person erkannt - nur Live-Image aktualisiert")
            
            return jsonify({
                'person_detected': bool(person_detected),  # Sicherstellen dass es ein Python bool ist
                'stay_awake': bool(person_detected),  # Wach bleiben wenn Person erkannt
                'confidence': float(confidence),  # Sicherstellen dass es ein Python float ist
                'detection_id': detection_id
            })
        
    except Exception as e:
        logger.error(f"Person check error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/detections')
def get_detections():
    try:
        limit = int(request.args.get('limit', 50))
        detections = service.get_detections(limit)
        return jsonify({'detections': detections})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/detections/<int:detection_id>/images')
def get_detection_images(detection_id):
    try:
        images = service.get_detection_images(detection_id)
        return jsonify({'images': images})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/images/<path:image_path>')
def get_image(image_path):
    try:
        full_path = IMAGES_PATH / image_path
        if full_path.exists() and full_path.suffix.lower() in ['.jpg', '.jpeg']:
            return send_file(str(full_path))
        else:
            return jsonify({'error': 'Image not found'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/live-image')
def get_live_image():
    try:
        live_path = IMAGES_PATH / "live.jpg"
        if live_path.exists():
            return send_file(str(live_path))
        else:
            return jsonify({'error': 'No live image available'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/doorbell/status')
def get_status():
    try:
        return jsonify({
            'online': True,
            'detections_count': len(service.get_detections(50)),
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'error': str(e), 'online': False}), 500

if __name__ == '__main__':
    logger.info("=" * 50)
    logger.info("Doorbell Service startet auf Port 5000")
    logger.info(f"Daten-Pfad: {DATA_PATH}")
    logger.info("ESP32 Deep Sleep Integration aktiv")
    logger.info("=" * 50)
    
    app.run(host='0.0.0.0', port=5000, debug=False)