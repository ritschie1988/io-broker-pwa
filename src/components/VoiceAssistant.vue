<template>
  <v-card class="voice-assistant-card" elevation="4">
    <v-card-title>
      <v-icon class="mr-2">mdi-microphone</v-icon>
      Sprachsteuerung & ChatGPT
    </v-card-title>
    <v-card-text>
      <div class="voice-controls">
        <v-btn :color="isListening ? 'primary' : 'default'" @click="toggleListening" rounded>
          <v-icon left>{{ isListening ? 'mdi-microphone-off' : 'mdi-microphone' }}</v-icon>
          {{ isListening ? 'Stop' : 'Hey GPT!' }}
        </v-btn>
        <span v-if="lastTranscript" class="ml-3">{{ lastTranscript }}</span>
      </div>
      <div class="chat-window" ref="chatWindow">
        <div v-for="msg in chatHistory" :key="msg.id" :class="['chat-msg', msg.role]">
          <strong v-if="msg.role==='user'">Du:</strong>
          <strong v-else>GPT:</strong>
          <span>{{ msg.content }}</span>
        </div>
      </div>
      <v-text-field v-model="chatInput" label="Chat mit GPT" @keyup.enter="sendChat" append-icon="mdi-send" @click:append="sendChat" />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Fuse from 'fuse.js'

const isListening = ref(false)
const lastTranscript = ref('')
const chatInput = ref('')
const chatHistory = ref([])
const chatWindow = ref(null)
const roomsData = ref({})

// Hilfsfunktion: Flache Geräteliste mit Synonymen für Fuzzy-Suche
function getFlatDeviceList(rooms, roomKey) {
  if (!rooms || !roomKey || !Array.isArray(rooms[roomKey])) return [];
  return rooms[roomKey].map(device => {
    return {
      id: device.id,
      name: device.name,
      synonyms: Array.isArray(device.synonyms) ? device.synonyms : [],
      type: device.type,
      deviceObj: device
    }
  });
}

// Hilfsfunktion für case-insensitive Raumzuordnung
function findRoomKey(rooms, spokenRoom) {
  if (!spokenRoom) return null;
  const lower = spokenRoom.toLowerCase();
  return Object.keys(rooms).find(key => key.toLowerCase() === lower);
}

let recognition = null

onMounted(async () => {
  // Lade Geräte (rooms-Objekt)
  try {
    const res = await fetch('/iobroker/devices.json')
    roomsData.value = await res.json()
  } catch (error) {
    console.error('Fehler beim Laden der Geräte:', error)
  }

  // Web Speech API
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
  if (SpeechRecognition) {
    recognition = new SpeechRecognition()
    recognition.lang = 'de-DE'
    recognition.continuous = true // Mehrere Sätze möglich
    recognition.interimResults = true // Zeigt Zwischenergebnisse
    
    let lastSpeechTime = Date.now()
    let pauseTimeout = null
    
    recognition.onresult = (event) => {
      let transcript = ''
      for (let i = event.resultIndex; i < event.results.length; ++i) {
        transcript += event.results[i][0].transcript
      }
      lastTranscript.value = transcript.trim()
      lastSpeechTime = Date.now()
      
      // Timer für Pause zurücksetzen
      if (pauseTimeout) clearTimeout(pauseTimeout)
      pauseTimeout = setTimeout(() => {
        recognition.stop()
      }, 1500) // 1,5 Sekunden Pause = Aufnahme beenden
    }
    
    recognition.onend = () => {
      isListening.value = false
      if (lastTranscript.value) {
        handleVoiceCommand(lastTranscript.value)
        lastTranscript.value = ''
      }
    }
  }
})

function toggleListening() {
  if (!recognition) return
  if (isListening.value) {
    recognition.stop()
    isListening.value = false
  } else {
    recognition.start()
    isListening.value = true
  }
}

async function handleVoiceCommand(text) {
  chatHistory.value.push({ id: Date.now(), role: 'user', content: text })
  await sendToGPT(text)
}

async function sendToGPT(userText) {
  // Nach jeder neuen Nachricht automatisch scrollen
  setTimeout(() => {
    if (chatWindow.value) {
      chatWindow.value.scrollTop = chatWindow.value.scrollHeight
    }
  }, 50)

  try {
    // KI-Request ausführen
    const response = await fetch('/iobroker/api/gpt.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ user: userText })
    });
    
    const data = await response.json();
    const rooms = roomsData.value;

    // ERWEITERTE DEBUG-AUSGABE
    console.group(`[VoiceAssistant] Verarbeitung: "${userText}"`);
    console.log('🔍 Rohe KI-Antwort:', JSON.stringify(data, null, 2));
    console.log('🏠 Verfügbare Räume:', Object.keys(rooms));
    console.log('📋 Struktur-Check:');
    console.log('  - hat multi_light:', !!data?.multi_light, data?.multi_light);
    console.log('  - hat multi_shutter:', !!data?.multi_shutter, data?.multi_shutter);
    console.log('  - hat ac_control:', !!data?.ac_control, data?.ac_control);
    console.log('  - hat response:', !!data?.response, data?.response);
    console.groupEnd();

    // FALLBACK: Wenn KI nur eine einfache Antwort zurückgibt
    if (data?.response && !data?.multi_light && !data?.multi_shutter && !data?.ac_control) {
      console.warn('⚠️ KI gab nur response zurück, keine Steuerungsdaten');
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: data.response });
      return;
    }

    // 1. MULTI_LIGHT VERARBEITUNG
    if (data && data.multi_light && Array.isArray(data.multi_light)) {
      console.group('💡 Multi-Light Verarbeitung');
      
      for (const item of data.multi_light) {
        console.log('📝 Verarbeite Item:', item);
        
        // Status-Nachrichten anzeigen
        if (item.status) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: item.status });
          console.info('✅ Status-Nachricht:', item.status);
        }
        
        // Fehler-Nachrichten anzeigen
        if (item.error) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: item.error });
          console.warn('❌ Fehler-Nachricht:', item.error);
        }
        
        // Nur verarbeiten wenn ID und Wert vorhanden
        if (item.id && (item.value !== undefined)) {
          let device = null;
          let roomKey = null;
          let targetId = item.id; // Fallback zur direkten ID
          
          console.log('🔎 Suche Gerät:', {
            itemRoom: item.room,
            itemName: item.name,
            itemLabel: item.label,
            itemId: item.id
          });
          
          // Raumzuordnung
          if (item.room) {
            roomKey = findRoomKey(rooms, item.room);
            console.log('🏠 Gefundener Raumschlüssel:', roomKey);
            
            if (roomKey && Array.isArray(rooms[roomKey])) {
              // Filtere nur Switch-Geräte
              const flatDevices = getFlatDeviceList(rooms, roomKey).filter(d => d.type === 'switch');
              console.log('🔌 Verfügbare Switch-Geräte im Raum:', flatDevices);
              
              // Fuzzy-Suche nach Name
              const searchTerm = item.name || item.label || '';
              if (searchTerm) {
                const fuse = new Fuse(flatDevices, { 
                  keys: ['name', 'synonyms'], 
                  threshold: 0.4, // Etwas weniger streng
                  includeScore: true 
                });
                
                const fuseResult = fuse.search(searchTerm);
                console.log('🎯 Fuzzy-Suchergebnis für "' + searchTerm + '":', fuseResult);
                
                if (fuseResult && fuseResult.length > 0) {
                  device = fuseResult[0].item.deviceObj;
                  targetId = device.id;
                  console.log('✅ Gerät via Fuzzy gefunden:', device.name, 'ID:', targetId);
                } else {
                  console.warn('⚠️ Kein Gerät via Fuzzy gefunden für:', searchTerm);
                }
              }
            } else {
              console.warn('⚠️ Raum nicht gefunden oder leer:', item.room);
            }
          } else {
            console.log('ℹ️ Kein Raum angegeben, verwende direkte ID');
          }
          
          // Gerät schalten
          const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(targetId)}&query=value=${encodeURIComponent(item.value)}`;
          console.log('🔗 API-Call URL:', url);
          
          try {
            const res = await fetch(url);
            let resultText = '';
            
            if (res.ok) {
              const deviceName = device ? device.name : targetId;
              const roomName = roomKey || item.room || '';
              resultText = `✅ ${deviceName} ${roomName ? 'im ' + roomName : ''} wurde ${item.value ? 'eingeschaltet' : 'ausgeschaltet'}`;
              console.info('✅ Erfolgreich geschaltet:', targetId, '=', item.value);
            } else {
              resultText = `❌ Fehler beim Schalten! HTTP Status: ${res.status}`;
              console.warn('❌ HTTP-Fehler beim Schalten:', res.status, targetId);
            }
            
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: resultText });
            
          } catch (e) {
            const errorMsg = '❌ Schalt-Fehler: ' + (e.message || 'Unbekannt');
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: errorMsg });
            console.error('❌ Exception beim Schalten:', e);
          }
        } else {
          console.warn('⚠️ Item unvollständig - fehlt ID oder value:', item);
        }
      }
      
      console.groupEnd();
      return; // Multi-Light abgeschlossen
    }

    // 2. MULTI_SHUTTER VERARBEITUNG (vereinfacht)
    if (data && data.multi_shutter && Array.isArray(data.multi_shutter)) {
      console.log('🏠 Multi-Shutter Verarbeitung');
      // ... bestehende Shutter-Logik ...
      return;
    }

    // 3. AC_CONTROL VERARBEITUNG (vereinfacht) 
    if (data && data.ac_control) {
      console.log('❄️ AC-Control Verarbeitung');
      await handleAirConditioningControl(data.ac_control, rooms);
      return;
    }

    // 4. FALLBACK: Normale Antwort oder Fehlermeldung
    if (data && data.response) {
      console.log('💬 Verwende Standard-Antwort:', data.response);
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: data.response });
    } else {
      console.warn('⚠️ Keine verwertbare Antwort von der KI');
      chatHistory.value.push({ 
        id: Date.now(), 
        role: 'assistant', 
        content: 'Entschuldigung, ich konnte Ihre Anfrage nicht verstehen. (Debug: Keine multi_light/multi_shutter/ac_control/response Daten erhalten)' 
      });
    }

  } catch (error) {
    console.error('❌ Fehler beim Senden an GPT:', error);
    chatHistory.value.push({ 
      id: Date.now(), 
      role: 'assistant', 
      content: 'Es ist ein Fehler aufgetreten: ' + (error.message || 'Unbekannt') 
    });
  }
}

async function handleAirConditioningControl(acData, rooms) {
  const { action, room, value, mode, temperature, turbo } = acData

  if (!room || !rooms[room]) {
    chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Raum nicht gefunden!' })
    return
  }

  const roomDevices = rooms[room]
  const switchesArr = roomDevices.filter(d => d.type === 'switch')
  const targetsArr = roomDevices.filter(d => d.type === 'target')

  // Geräte finden
  let powerDevice = null
  let modeDevice = null
  let tempDevice = null
  let turboDevice = null

  // Fuzzy für Power
  const powerFuse = new Fuse(switchesArr, { keys: ['name', 'synonyms'], threshold: 0.3 })
  let powerResult = powerFuse.search('klima power')
  powerDevice = powerResult.length > 0 ? powerResult[0].item : null
  if (!powerDevice) {
    powerResult = powerFuse.search('klima')
    powerDevice = powerResult.length > 0 ? powerResult[0].item : null
  }

  // Fuzzy für Modus
  const modeFuse = new Fuse(switchesArr, { keys: ['name', 'synonyms'], threshold: 0.3 })
  const modeResult = modeFuse.search('betriebsmodus')
  modeDevice = modeResult.length > 0 ? modeResult[0].item : null

  // Fuzzy für Temperatur
  const tempFuse = new Fuse(targetsArr, { keys: ['name', 'synonyms'], threshold: 0.3 })
  let tempResult = tempFuse.search('solltemperatur')
  tempDevice = tempResult.length > 0 ? tempResult[0].item : null
  if (!tempDevice) {
    tempResult = tempFuse.search('zieltemperatur')
    tempDevice = tempResult.length > 0 ? tempResult[0].item : null
  }

  // Fuzzy für Turbo
  const turboFuse = new Fuse(switchesArr, { keys: ['name', 'synonyms'], threshold: 0.3 })
  const turboResult = turboFuse.search('turbo')
  turboDevice = turboResult.length > 0 ? turboResult[0].item : null

  let results = []

  // set_all: mehrere Parameter gleichzeitig setzen
  if (action === 'set_all') {
    // Power einschalten
    if (powerDevice && powerDevice.id) {
      const acValue = 1 // an
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(powerDevice.id)}&query=value=${encodeURIComponent(acValue)}`
      try {
        const res = await fetch(url)
        if (res.ok) results.push('Klimaanlage eingeschaltet.')
      } catch (e) {
        console.error('Fehler beim Einschalten der Klimaanlage:', e)
      }
    }

    // Modus setzen
    if (modeDevice && modeDevice.id && mode) {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(modeDevice.id)}&query=value=${encodeURIComponent(mode)}`
      try {
        const res = await fetch(url)
        if (res.ok) results.push(`Modus auf ${mode} gesetzt.`)
      } catch (e) {
        console.error('Fehler beim Setzen des Modus:', e)
      }
    }

    // Temperatur setzen
    if (tempDevice && tempDevice.id && temperature) {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(tempDevice.id)}&query=value=${encodeURIComponent(temperature)}`
      try {
        const res = await fetch(url)
        if (res.ok) results.push(`Solltemperatur auf ${temperature}°C gesetzt.`)
      } catch (e) {
        console.error('Fehler beim Setzen der Temperatur:', e)
      }
    }

    // Turbo setzen
    if (turboDevice && turboDevice.id && typeof turbo !== 'undefined') {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(turboDevice.id)}&query=value=${encodeURIComponent(turbo)}`
      try {
        const res = await fetch(url)
        if (res.ok) results.push(`Turbo-Modus ${turbo ? 'aktiviert' : 'deaktiviert'}.`)
      } catch (e) {
        console.error('Fehler beim Setzen des Turbo-Modus:', e)
      }
    }

    chatHistory.value.push({ 
      id: Date.now(), 
      role: 'assistant', 
      content: results.length ? results.join(' ') : 'Keine passenden AC-Geräte gefunden.' 
    })
    return
  }

  // Einzelaktionen
  if (action === 'on' && powerDevice && powerDevice.id) {
    const acValue = 1
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(powerDevice.id)}&query=value=${encodeURIComponent(acValue)}`
    try {
      const res = await fetch(url)
      if (res.ok) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room} eingeschaltet.` })
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten der Klimaanlage!' })
      }
    } catch (e) {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
    }
    return
  }

  if (action === 'off' && powerDevice && powerDevice.id) {
    const acValue = 0
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(powerDevice.id)}&query=value=${encodeURIComponent(acValue)}`
    try {
      const res = await fetch(url)
      if (res.ok) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room} ausgeschaltet.` })
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten der Klimaanlage!' })
      }
    } catch (e) {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
    }
    return
  }

  if (action === 'set_temp' && tempDevice && tempDevice.id && value) {
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(tempDevice.id)}&query=value=${encodeURIComponent(value)}`
    try {
      const res = await fetch(url)
      if (res.ok) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room}: Solltemperatur auf ${value}°C gesetzt.` })
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Setzen der Klimaanlagen-Temperatur!' })
      }
    } catch (e) {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
    }
    return
  }

  if (action === 'set_mode' && modeDevice && modeDevice.id && value) {
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(modeDevice.id)}&query=value=${encodeURIComponent(value)}`
    try {
      const res = await fetch(url)
      if (res.ok) {
        let modeText = ''
        switch (value) {
          case 1: modeText = 'Automatik'; break;
          case 2: modeText = 'Kühlen'; break;
          case 3: modeText = 'Entfeuchten'; break;
          case 4: modeText = 'Heizen'; break;
          case 5: modeText = 'Nur Lüfter'; break;
          default: modeText = value
        }
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room}: Modus auf ${modeText} gesetzt.` })
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Setzen des Klimaanlagen-Modus!' })
      }
    } catch (e) {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
    }
    return
  }

  if (action === 'set_turbo' && turboDevice && turboDevice.id && typeof value !== 'undefined') {
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(turboDevice.id)}&query=value=${encodeURIComponent(value)}`
    try {
      const res = await fetch(url)
      if (res.ok) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room}: Turbo-Modus ${value ? 'aktiviert' : 'deaktiviert'}.` })
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Setzen des Turbo-Modus!' })
      }
    } catch (e) {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
    }
    return
  }

  chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Unbekannte Klimaanlagen-Aktion oder kein passendes Gerät gefunden!' })
}

function sendChat() {
  if (!chatInput.value) return
  chatHistory.value.push({ id: Date.now(), role: 'user', content: chatInput.value })
  sendToGPT(chatInput.value)
  chatInput.value = ''
}
</script>

<style scoped>
.voice-assistant-card {
  width: 100%;
  max-width: none;
  margin: 0;
  box-sizing: border-box;
}

.voice-controls {
  display: flex;
  align-items: center;
  margin-bottom: 1rem;
}

/* Chatfenster: Immer scrollbar, auch bei sehr langen Ausgaben */
.chat-window {
  background: #f5f5f5;
  border-radius: 8px;
  padding: 1rem;
  max-height: 400px;
  min-height: 120px;
  overflow-y: auto;
  margin-bottom: 1rem;
  scrollbar-width: auto;
  scrollbar-color: #1976d2 #f5f5f5;
  display: flex;
  flex-direction: column;
}

/* Für Webkit-Browser */
.chat-window::-webkit-scrollbar {
  width: 8px;
}
.chat-window::-webkit-scrollbar-thumb {
  background: #1976d2;
  border-radius: 8px;
}
.chat-window::-webkit-scrollbar-track {
  background: #f5f5f5;
  border-radius: 8px;
}

.chat-msg {
  margin-bottom: 0.5rem;
}
.chat-msg.user {
  text-align: right;
  color: #1976d2;
}
.chat-msg.assistant {
  text-align: left;
  color: #333;
}
</style>