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
const devices = ref({})

let recognition = null
onMounted(async () => {
  // Lade Geräte
  const res = await fetch('/iobroker/devices.json')
  devices.value = await res.json()
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
  const allDevices = Object.values(devices.value).flat()

  // Licht schalten (verbesserte Logik: Suche nach Lichtname und Raum)
  if (data && data.name === 'toggle_light') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    const state = params.state
    // Versuche Lichtname aus dem User-Text zu extrahieren
    let lightName = ''
    const lightKeywords = ['nachtlicht', 'fernsehlicht', 'hauptlicht', 'licht', 'ambiente', 'kochlicht']
    for (const keyword of lightKeywords) {
      if (userText.toLowerCase().includes(keyword)) {
        lightName = keyword
        break
      }
    }
    let device = null
    if (lightName) {
      device = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.type === 'switch' && d.name.toLowerCase().includes(lightName))
    }
    // Fallback: erstes Licht im Raum
    if (!device) {
      device = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.type === 'switch')
    }
    if (device) {
      const value = state === 'on' ? device.onValue : device.offValue
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(device.id)}&query=value=${encodeURIComponent(value)}`
      try {
        const res = await fetch(url)
        if (res.ok) {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Das ${lightName ? lightName : 'Licht'} im ${room} wurde ${state === 'on' ? 'eingeschaltet' : 'ausgeschaltet'}.` })
        } else {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Schalten!' })
        }
      } catch (e) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
      }
    } else {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Gerät gefunden!' })
    }
    return
  }

  // Temperatur auslesen
  if (data && data.name === 'get_temperature') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    const device = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.type === 'sensor' && d.name.toLowerCase().includes('temperatur'))
    if (device) {
      const url = `/iobroker/api/iobroker-proxy.php?endpoint=get/${encodeURIComponent(device.id)}`
      try {
        const res = await fetch(url)
        if (res.ok) {
          const val = await res.json()
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: `Die Temperatur im ${room} beträgt ${val.val} ${device.unit || '°C'}.` })
        } else {
          chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler beim Auslesen der Temperatur!' })
        }
      } catch (e) {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Fehler: ' + (e.message || 'Unbekannt') })
      }
    } else {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein Temperatursensor gefunden!' })
    }
    return
  }

  // Temperatur setzen
  if (data && data.name === 'set_temperature') {
    const params = JSON.parse(data.arguments || '{}')
    const room = params.room
    const value = params.value
    const device = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.type === 'target' && d.name.toLowerCase().includes('temperatur'))
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
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Zieltemperatur-Gerät gefunden!' })
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
    const acDevice = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.name.toLowerCase().includes('klima power'))
    const tempDevice = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.name.toLowerCase().includes('klima solltemperatur'))
    const opModeDevice = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.name.toLowerCase().includes('klima') && d.name.toLowerCase().includes('operationalmode'))
    const turboDevice = allDevices.find(d => d.name.toLowerCase().includes(room.toLowerCase()) && d.name.toLowerCase().includes('klima') && d.name.toLowerCase().includes('turbo'))

    // set_all: mehrere Parameter gleichzeitig setzen
    if (action === 'set_all') {
      let results = []
      // Power einschalten
      if (acDevice) {
        const acValue = 1 // an
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(acDevice.id)}&query=value=${encodeURIComponent(acValue)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push('Klimaanlage eingeschaltet.')
        } catch {}
      }
      // Modus setzen
      if (opModeDevice && mode) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(opModeDevice.id)}&query=value=${encodeURIComponent(mode)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Modus auf ${mode} gesetzt.`)
        } catch {}
      }
      // Temperatur setzen
      if (tempDevice && temperature) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(tempDevice.id)}&query=value=${encodeURIComponent(temperature)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Solltemperatur auf ${temperature}°C gesetzt.`)
        } catch {}
      }
      // Turbo setzen
      if (turboDevice && typeof turbo !== 'undefined') {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(turboDevice.id)}&query=value=${encodeURIComponent(turbo)}`
        try {
          const res = await fetch(url)
          if (res.ok) results.push(`Turbo-Modus ${turbo ? 'aktiviert' : 'deaktiviert'}.`)
        } catch {}
      }
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: results.length ? results.join(' ') : 'Keine passenden AC-Geräte gefunden.' })
      return
    }

    // Einzelaktionen wie bisher
    if (action === 'on' || action === 'off') {
      if (acDevice) {
        const acValue = action === 'on' ? acDevice.onValue : acDevice.offValue
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(acDevice.id)}&query=value=${encodeURIComponent(acValue)}`
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
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Keine Klimaanlage gefunden!' })
      }
    } else if (action === 'set_temp') {
      if (tempDevice && value) {
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
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Klimaanlagen-Temperaturgerät gefunden!' })
      }
    } else if (action === 'set_mode') {
      // operationalMode setzen
      if (opModeDevice && value) {
        const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${encodeURIComponent(opModeDevice.id)}&query=value=${encodeURIComponent(value)}`
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
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Klimaanlagen-Modusgerät gefunden!' })
      }
    } else if (action === 'set_turbo') {
      // turboMode setzen
      if (turboDevice && typeof value !== 'undefined') {
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
      } else {
        chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Kein passendes Turbo-Modus-Gerät gefunden!' })
      }
    } else {
      chatHistory.value.push({ id: Date.now(), role: 'assistant', content: 'Unbekannte Klimaanlagen-Aktion!' })
    }
    return
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
