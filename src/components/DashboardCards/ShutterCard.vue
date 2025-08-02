<template>
  <v-card>
    <v-card-title>Rollläden {{ roomName }}</v-card-title>
    <v-card-text>
      <template v-for="shutter in shutters" :key="shutter.name">
        <div class="mb-6">
          <h4 class="mb-3">{{ shutter.label }}</h4>
          
          <!-- Canvas + Slider Layout -->
          <div class="shutter-display-container justify-center mb-3">
            <!-- Canvas mit integriertem Slider -->
            <ShutterCanvas 
              :position="shutterPositions[shutter.positionId] || 0"
              :width="canvasWidth"
              @positionChange="(newPosition) => setPosition(shutter, newPosition)"
            />
          </div>

          <!-- Steuerungsbuttons -->
          <div class="d-flex justify-center gap-2 mb-3">
            <v-btn
              size="small"
              @click="openShutter(shutter)"
              :disabled="isMoving[shutter.name]"
            >
              <v-icon left>mdi-arrow-up</v-icon>
              Öffnen
            </v-btn>
            
            <v-btn
              size="small"
              @click="stopShutter(shutter)"
              :disabled="!isMoving[shutter.name]"
            >
              <v-icon left>mdi-stop</v-icon>
              Stop
            </v-btn>
            
            <v-btn
              size="small"
              @click="closeShutter(shutter)"
              :disabled="isMoving[shutter.name]"
            >
              <v-icon left>mdi-arrow-down</v-icon>
              Schließen
            </v-btn>
          </div>


        </div>
        
        <v-divider v-if="shutter !== shutters[shutters.length - 1]" class="mb-4"></v-divider>
      </template>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, computed, onUnmounted } from 'vue'
import ShutterCanvas from './ShutterCanvas.vue'

const props = defineProps({ room: String })

// Canvas Breite dynamisch berechnen
const canvasWidth = ref(300)

// Definition aller Rollläden pro Raum basierend auf devices.json
const roomShutters = {
  wohnzimmer: [
    {
      name: 'links',
      label: 'Rollladen links',
      openId: 'alias.0.shelly.0.shellyplus2pmc82e1809c2701.Cover0.Open',
      closeId: 'alias.0.shelly.0.shellyplus2pmc82e1809c2701.Cover0.Close',
      stopId: 'alias.0.shelly.0.shellyplus2pmc82e1809c2701.Cover0.Stop',
      positionId: 'alias.0.shelly.0.shellyplus2pmc82e1809c2701.Cover0.Position'
    },
    {
      name: 'rechts',
      label: 'Rollladen rechts',
      openId: 'alias.0.shelly.0.shellyplus2pmc82e18096a181.Cover0.Open',
      closeId: 'alias.0.shelly.0.shellyplus2pmc82e18096a181.Cover0.Close',
      stopId: 'alias.0.shelly.0.shellyplus2pmc82e18096a181.Cover0.Stop',
      positionId: 'alias.0.shelly.0.shellyplus2pmc82e18096a181.Cover0.Position'
    }
  ],
  schlafzimmer: [
    {
      name: 'hauptrollladen',
      label: 'Rollladen',
      openId: 'alias.0.shelly.0.shellyplus2pmc4d8d550a2941.Cover0.Open',
      closeId: 'alias.0.shelly.0.shellyplus2pmc4d8d550a2941.Cover0.Close',
      stopId: 'alias.0.shelly.0.shellyplus2pmc4d8d550a2941.Cover0.Stop',
      positionId: 'alias.0.shelly.0.shellyplus2pmc4d8d550a2941.Cover0.Position'
    }
  ]
}

// Raumname für die Card-Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'wohnzimmer': return 'Wohnzimmer'
    case 'schlafzimmer': return 'Schlafzimmer'
    default: return ''
  }
})

// Aktuelle Rollläden für den Raum
const shutters = computed(() => roomShutters[props.room] || [])

// Status der Rollläden
const shutterPositions = ref({})
const isMoving = ref({})
const manualPositions = ref({})

// Timer für Live-Updates
let updateInterval = null

// Positionen laden
async function fetchPositions() {
  const positions = {}
  for (const shutter of shutters.value) {
    try {
      const res = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${shutter.positionId}`)
      const data = await res.json()
      positions[shutter.positionId] = data.val || 0
      
      // Manuelle Position synchronisieren
      if (!manualPositions.value[shutter.name]) {
        manualPositions.value[shutter.name] = data.val || 0
      }
    } catch (error) {
      console.error(`Fehler beim Laden der Position für ${shutter.label}:`, error)
      positions[shutter.positionId] = 0
    }
  }
  shutterPositions.value = positions
}

// Rollladen öffnen
async function openShutter(shutter) {
  try {
    isMoving.value[shutter.name] = true
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${shutter.openId}&query=value=1`)
    
    // Nach kurzer Zeit wieder aktivieren (Rollläden brauchen Zeit zum Fahren)
    setTimeout(() => {
      isMoving.value[shutter.name] = false
    }, 2000)
  } catch (error) {
    console.error(`Fehler beim Öffnen von ${shutter.label}:`, error)
    isMoving.value[shutter.name] = false
  }
}

// Rollladen schließen
async function closeShutter(shutter) {
  try {
    isMoving.value[shutter.name] = true
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${shutter.closeId}&query=value=1`)
    
    setTimeout(() => {
      isMoving.value[shutter.name] = false
    }, 2000)
  } catch (error) {
    console.error(`Fehler beim Schließen von ${shutter.label}:`, error)
    isMoving.value[shutter.name] = false
  }
}

// Rollladen stoppen
async function stopShutter(shutter) {
  try {
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${shutter.stopId}&query=value=1`)
    isMoving.value[shutter.name] = false
  } catch (error) {
    console.error(`Fehler beim Stoppen von ${shutter.label}:`, error)
  }
}

// Position setzen
async function setPosition(shutter, position) {
  try {
    isMoving.value[shutter.name] = true
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${shutter.positionId}&query=value=${position}`)
    
    // Position sofort aktualisieren
    shutterPositions.value[shutter.positionId] = position
    
    setTimeout(() => {
      isMoving.value[shutter.name] = false
    }, 2000)
  } catch (error) {
    console.error(`Fehler beim Setzen der Position von ${shutter.label}:`, error)
    isMoving.value[shutter.name] = false
  }
}

// Live-Updates starten
function startLiveUpdates() {
  updateInterval = setInterval(fetchPositions, 5000) // Alle 5 Sekunden aktualisieren
}

// Live-Updates stoppen
function stopLiveUpdates() {
  if (updateInterval) {
    clearInterval(updateInterval)
    updateInterval = null
  }
}

// Bei Raumwechsel oder Mount neu laden
watch(() => props.room, () => {
  fetchPositions()
  // Manuelle Positionen zurücksetzen
  manualPositions.value = {}
}, { immediate: true })

onMounted(() => {
  fetchPositions()
  startLiveUpdates()
  
  // Canvas Breite basierend auf Card-Breite berechnen
  // Das machen wir später responsive
  canvasWidth.value = 300
})

onUnmounted(() => {
  stopLiveUpdates()
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}

.shutter-display-container {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.canvas-wrapper {
  flex: 0 0 80%;
}

.slider-wrapper {
  flex: 0 0 20%;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100px;
}

/* Höhe mit :deep() und exakten Klassen */
.vertical-slider :deep(.v-input__control) {
  height: 70px !important;
}

.vertical-slider :deep(.v-slider__container) {
  height: 70px !important;
  width: 30px !important;
}

.vertical-slider :deep(.v-slider-track) {
  width: 6px !important;
}

/* Glas-Design mit :deep() */
.vertical-slider :deep(.v-slider-track__fill) {
  background: linear-gradient(180deg, 
    rgba(33, 150, 243, 0.8) 0%, 
    rgba(33, 150, 243, 0.6) 50%, 
    rgba(33, 150, 243, 0.4) 100%) !important;
  backdrop-filter: blur(10px) !important;
  border: 1px solid rgba(33, 150, 243, 0.3) !important;
  border-radius: 12px !important;
}

.vertical-slider :deep(.v-slider-track__background) {
  background: rgba(255, 255, 255, 0.1) !important;
  backdrop-filter: blur(5px) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 12px !important;
}

.vertical-slider :deep(.v-slider-thumb) {
  background: linear-gradient(145deg, 
    rgba(33, 150, 243, 0.9) 0%, 
    rgba(33, 150, 243, 0.7) 100%) !important;
  border: 2px solid rgba(255, 255, 255, 0.3) !important;
  backdrop-filter: blur(10px) !important;
  box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3) !important;
}

.vertical-slider :deep(.v-slider-thumb__surface) {
  background: transparent !important;
}

.slider-label {
  margin-top: 8px;
  font-size: 12px;
  font-weight: bold;
  color: rgba(255, 255, 255, 0.87);
}
</style>