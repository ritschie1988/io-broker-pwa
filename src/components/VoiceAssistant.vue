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
      <div class="chat-window">
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
const isListening = ref(false)
const lastTranscript = ref('')
const chatInput = ref('')
const chatHistory = ref([])
const roomsData = ref({})

// Hilfsfunktion für case-insensitive Raumzuordnung
function findRoomKey(rooms, spokenRoom) {
  if (!spokenRoom) return null;
  const lower = spokenRoom.toLowerCase();
  return Object.keys(rooms).find(key => key.toLowerCase() === lower);
}

let recognition = null
onMounted(async () => {
  // Lade Geräte (rooms-Objekt)
  const res = await fetch('/iobroker/devices.json')
  roomsData.value = await res.json()
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
  const response = await fetch('/iobroker/api/gpt.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ user: userText })
  })
  const data = await response.json()
  const rooms = roomsData.value // rooms-Objekt

  // Licht schalten (direkt im Raum suchen)
  if (data && data.name === 'toggle_light') {
    const params = JSON.parse(data.arguments || '{}')
    // Mehrere Lichtbefehle erkennen: z.B. "Schalte das Nachtlicht im Schlafzimmer ein und das Fernsehlicht im Wohnzimmer aus"
    const re = /(nachtlicht|fernsehlicht|hauptlicht|licht|ambiente|kochlicht) im ([a-zäöüß]+) (ein|aus)/gi
    const matches = [...userText.matchAll(re)]
    let found = false
    if (matches.length > 0) {
      for (const m of matches) {
        const lightLabel = m[1].toLowerCase()
        const spokenRoom = m[2]
        const state = m[3] === 'ein' ? 'on' : 'off'
        // Raum-Key case-insensitive suchen
        const roomKey = findRoomKey(rooms, spokenRoom)
        let device = null
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `[Debug] Raum erkannt: "${spokenRoom}" → Key: "${roomKey}"` })
        if (roomKey && rooms[roomKey] && Array.isArray(rooms[roomKey].lights)) {
          // Suche nur in lights-Array des Raums
          const lightsArr = rooms[roomKey].lights
          device = lightsArr.find(d => d.label && d.label.toLowerCase() === lightLabel)
          if (!device) {
            device = lightsArr.find(d => d.label && d.label.toLowerCase() === lightLabel.toLowerCase())
          }
          if (!device) {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `[Debug] Kein Gerät mit Label "${lightLabel}" im lights-Array von Raum "${spokenRoom}" gefunden.` })
          }
        }
        if (device) {
          found = true
          const value = state === 'on' ? (device.onValue ?? true) : (device.offValue ?? false)
          const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(device.id)}&query=value=${encodeURIComponent(value)}`
          try {
            const res = await fetch(url)
            if (res.ok) {
              chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Das ${device.label} im ${spokenRoom} wurde ${state === 'on' ? 'eingeschaltet' : 'ausgeschaltet'}.` })
            } else {
              chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten!' })
            }
          } catch (e) {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
          }
        } else if (roomKey && rooms[roomKey] && Array.isArray(rooms[roomKey].lights)) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `[Debug] Kein passendes Gerät für ${lightLabel} im ${spokenRoom} gefunden!` })
        }
      }
    }
    // Falls kein Match, Standardlogik wie bisher (einzelner Befehl)
    if (!found) {
      const spokenRoom = params.room
      const state = params.state
      let lightName = ''
      const lightKeywords = ['nachtlicht', 'fernsehlicht', 'hauptlicht', 'licht', 'ambiente', 'kochlicht']
      for (const keyword of lightKeywords) {
        if (userText.toLowerCase().includes(keyword)) {
          lightName = keyword
          break
        }
      }
      let device = null
      const roomKey = findRoomKey(rooms, spokenRoom)
      if (roomKey && rooms[roomKey] && Array.isArray(rooms[roomKey].lights)) {
        const lightsArr = rooms[roomKey].lights
        device = lightsArr.find(d => d.label && d.label.toLowerCase() === lightName.toLowerCase())
        if (!device) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `[Debug] Kein Gerät mit Label "${lightName}" im lights-Array von Raum "${spokenRoom}" gefunden.` })
        }
      }
      if (device) {
        const value = state === 'on' ? (device.onValue ?? true) : (device.offValue ?? false)
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(device.id)}&query=value=${encodeURIComponent(value)}`
        try {
          const res = await fetch(url)
          if (res.ok) {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Das ${device.label} im ${spokenRoom} wurde ${state === 'on' ? 'eingeschaltet' : 'ausgeschaltet'}.` })
          } else {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten!' })
          }
        } catch (e) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
        }
      } else if (roomKey && rooms[roomKey] && Array.isArray(rooms[roomKey].lights)) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `[Debug] Kein passendes Gerät für ${lightName} im ${spokenRoom} gefunden!` })
      }
    }
    return
  }

  // Temperatur auslesen
  if (data && data.name === 'get_temperature') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    let device = null
    let label = ''
    if (userText && rooms[room] && rooms[room].temperature && rooms[room].temperature.label) {
      label = rooms[room].temperature.label.toLowerCase()
      if (userText.toLowerCase().includes(label)) {
        device = rooms[room].temperature
      }
    }
    if (!device && rooms[room] && rooms[room].temperature) {
      device = rooms[room].temperature
    }
    if (device) {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=get/${encodeURIComponent(device.id)}`
      try {
        const res = await fetch(url)
        if (res.ok) {
          const val = await res.json()
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Die Temperatur im ${room} beträgt ${val.val} °C.` })
        } else {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Auslesen der Temperatur!' })
        }
      } catch (e) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
      }
    } else {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein Temperatursensor mit passendem Label gefunden!' })
    }
    return
  }

  // Temperatur setzen
  if (data && data.name === 'set_temperature') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    const value = params.value
    let device = null
    let label = ''
    if (userText && rooms[room] && rooms[room].targetTemp && rooms[room].targetTemp.label) {
      label = rooms[room].targetTemp.label.toLowerCase()
      if (userText.toLowerCase().includes(label)) {
        device = rooms[room].targetTemp
      }
    }
    if (!device && rooms[room] && rooms[room].targetTemp) {
      device = rooms[room].targetTemp
    }
    if (device) {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(device.id)}&query=value=${encodeURIComponent(value)}`
      try {
        const res = await fetch(url)
        if (res.ok) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Solltemperatur im ${room} auf ${value}°C gesetzt.` })
        } else {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Setzen der Temperatur!' })
        }
      } catch (e) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
      }
    } else {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Zieltemperatur-Gerät mit Label gefunden!' })
    }
    return
  }

  // Klimaanlage steuern
  if (data && data.name === 'control_ac') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    const action = params.action // z.B. 'on', 'off', 'set_temp', 'set_all'
    const value = params.value // optional für Einzelaktionen
    const mode = params.mode
    const temperature = params.temperature
    const turbo = params.turbo
    let climate = null
    let label = ''
    if (userText && rooms[room] && rooms[room].climate) {
      // Suche nach Label in allen climate-Unterobjekten
      const keys = ['powerState', 'operationalMode', 'targetTemperature', 'turboMode']
      for (const k of keys) {
        if (rooms[room].climate[k] && rooms[room].climate[k].label) {
          const l = rooms[room].climate[k].label.toLowerCase()
          if (userText.toLowerCase().includes(l)) {
            label = l
            break
          }
        }
      }
      climate = rooms[room].climate
    }
    if (!climate && rooms[room] && rooms[room].climate) {
      climate = rooms[room].climate
    }
    // set_all: mehrere Parameter gleichzeitig setzen
    if (action === 'set_all' && climate) {
      let results = []
      // Power einschalten
      if (climate.powerState && climate.powerState.id && (!label || climate.powerState.label.toLowerCase() === label)) {
        const acValue = 1 // an
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.powerState.id)}&query=value=${encodeURIComponent(acValue)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push('Klimaanlage eingeschaltet.')
        } catch {}
      }
      // Modus setzen
      if (climate.operationalMode && climate.operationalMode.id && mode && (!label || climate.operationalMode.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.operationalMode.id)}&query=value=${encodeURIComponent(mode)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Modus auf ${mode} gesetzt.`)
        } catch {}
      }
      // Temperatur setzen
      if (climate.targetTemperature && climate.targetTemperature.id && temperature && (!label || climate.targetTemperature.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.targetTemperature.id)}&query=value=${encodeURIComponent(temperature)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Solltemperatur auf ${temperature}°C gesetzt.`)
        } catch {}
      }
      // Turbo setzen
      if (climate.turboMode && climate.turboMode.id && typeof turbo !== 'undefined' && (!label || climate.turboMode.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.turboMode.id)}&query=value=${encodeURIComponent(turbo)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Turbo-Modus ${turbo ? 'aktiviert' : 'deaktiviert'}.`)
        } catch {}
      }
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: results.length ? results.join(' ') : 'Keine passenden AC-Geräte gefunden.' })
      return
    }

    // Einzelaktionen wie bisher, aber mit Label-Matching
    if (climate) {
      if ((action === 'on' || action === 'off') && climate.powerState && climate.powerState.id && (!label || climate.powerState.label.toLowerCase() === label)) {
        const acValue = action === 'on' ? 1 : 0
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.powerState.id)}&query=value=${encodeURIComponent(acValue)}`
        try {
          const res = await fetch(url)
          if (res.ok) {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Klimaanlage im ${room} ${action === 'on' ? 'eingeschaltet' : 'ausgeschaltet'}.` })
          } else {
            chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten der Klimaanlage!' })
          }
        } catch (e) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
        }
      } else if (action === 'set_temp' && climate.targetTemperature && climate.targetTemperature.id && value && (!label || climate.targetTemperature.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.targetTemperature.id)}&query=value=${encodeURIComponent(value)}`
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
      } else if (action === 'set_mode' && climate.operationalMode && climate.operationalMode.id && value && (!label || climate.operationalMode.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.operationalMode.id)}&query=value=${encodeURIComponent(value)}`
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
      } else if (action === 'set_turbo' && climate.turboMode && climate.turboMode.id && typeof value !== 'undefined' && (!label || climate.turboMode.label.toLowerCase() === label)) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(climate.turboMode.id)}&query=value=${encodeURIComponent(value)}`
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
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Unbekannte Klimaanlagen-Aktion oder kein passendes Label!' })
      }
      return
    }
  }

  // Fallback
  chatHistory.value.push({ id: Date.now(), role: 'assistant', content: data.status || 'Kein passender Funktionsaufruf erkannt.' })
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


.chat-window {
  background: #f5f5f5;
  border-radius: 8px;
  padding: 1rem;
  max-height: 300px;
  overflow-y: auto;
  margin-bottom: 1rem;
  scrollbar-width: thin;
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
