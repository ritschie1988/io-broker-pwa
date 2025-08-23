<template>
  <v-card class="vent-card pa-4">
        <v-card-title class="d-flex align-center justify-space-between">
      <div class="d-flex align-center gap-2">
        <span class="text-h6">🌀 Lüftung {{ roomName }}</span>
        <v-chip :color="isRunning ? 'primary' : 'grey'" size="small" variant="flat">{{ statusText }}</v-chip>
      </div>
      <div class="d-flex align-center gap-2">
        <v-chip size="small" variant="tonal">WRG {{ displayHeatRecovery.toFixed(0) }}%</v-chip>
        <v-chip 
          size="small" 
          variant="tonal"
          :color="displayFilterStatus >= 70 ? 'success' : displayFilterStatus >= 30 ? 'warning' : 'error'"
        >
          Filter {{ displayFilterStatus.toFixed(0) }}%
        </v-chip>
      </div>
    </v-card-title>

    <v-card-text>
      <!-- Temps -->
      <div class="d-flex flex-wrap gap-3 mb-4">
        <v-chip class="temp-pill" variant="elevated">
          <v-icon start icon="mdi-weather-sunny" />
          <span class="mr-1">Außen</span>
          <strong>{{ displayOutsideTemp.toFixed(1) }}°C</strong>
        </v-chip>
        <v-chip class="temp-pill" variant="elevated">
          <v-icon start icon="mdi-home-thermometer" />
          <span class="mr-1">Innen</span>
          <strong>{{ displayInsideTemp.toFixed(1) }}°C</strong>
        </v-chip>
      </div>

      <!-- Visualization -->
      <div class="viz-wrapper mb-4">
        <div class="mode-badge">{{ mode }}-Modus</div>
        <canvas ref="canvasRef" class="viz-canvas" width="600" height="300"></canvas>
      </div>

      <!-- Controls -->
      <div class="controls d-grid">
        <v-card variant="tonal" class="pa-3">
          <div class="text-subtitle-2 mb-2">Modus</div>
          <v-select
            :items="modes"
            v-model="localMode"
            density="comfortable"
            hide-details
            class="mb-2"
          />
          <v-switch v-model="localAutoMode" inset color="primary" hide-details label="Modus-Automatik" />
        </v-card>

        <v-card variant="tonal" class="pa-3">
          <div class="text-subtitle-2 mb-2">Stufe</div>
          <div class="d-flex align-center gap-2 mb-2">
            <v-btn 
              icon="mdi-minus" 
              size="small" 
              variant="outlined"
              :disabled="localLevel <= 1"
              @click="decrementLevel"
            />
            <v-text-field
              v-model.number="localLevel"
              :min="1"
              :max="5"
              type="number"
              density="compact"
              hide-details
              variant="outlined"
              class="level-input"
              @blur="validateLevel"
            />
            <v-btn 
              icon="mdi-plus" 
              size="small" 
              variant="outlined"
              :disabled="localLevel >= 5"
              @click="incrementLevel"
            />
          </div>
          <div class="d-flex align-center justify-space-between">
            <div class="text-caption">{{ levelText }}</div>
            <v-switch v-model="localAutoLevel" inset color="primary" hide-details label="Stufen-Automatik" />
          </div>
        </v-card>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'

// ===== TYPES =====
type Mode = 'Sommer' | 'Winter' | 'Party' | 'Stop'

class AirParticle {
  type: string
  active: boolean
  x: number
  y: number
  targetY?: number
  speed: number
  size: number
  opacity: number
  initialX: number
  initialY: number

  constructor(type = 'indoor') {
    this.type = type // 'indoor' oder 'outdoor'
    this.active = false // Wird durch Timing gesteuert
    this.speed = 0
    this.size = 0
    this.opacity = 0
    this.x = 0
    this.y = 0
    this.initialX = 0
    this.initialY = 0
    this.reset()
  }
  
  reset() {
    if (this.type === 'outdoor') {
      // Außenstrom: von unten nach oben an der Abdeckung - weiter links und unten
      this.x = 120 + Math.random() * 15 // Weiter nach links verschoben (130-145 statt 145-155)
      this.y = 250 + Math.random() * 20 // Weiter unten und mehr Variation (250-270 statt 235)
      this.targetY = 110 + Math.random() * 10 // Tiefer aufhören (90-100 statt 60-75)
      this.speed = this.getSpeedFromLevel() * 0.8 // Etwas langsamer außen
    } else {
      // Innenstrom: vom Ventilator nach rechts - mehr Auflockerung
      this.x = 185 + Math.random() * 10 // Startpunkt um 10px nach links verschoben (185-195 statt 195-205)
      this.y = 140 + Math.random() * 50 // Mehr vertikale Streuung (140-190 statt 130-170)
      this.speed = this.getSpeedFromLevel()
    }
    
    this.size = Math.random() * 2 + 1
    this.opacity = Math.random() * 0.6 + 0.4
    this.initialX = this.x
    this.initialY = this.y
  }
  
  getSpeedFromLevel() {
    const speeds = [0, 1.5, 2, 3, 4, 6] // Index 0 = Stop, 1-5 = Stufen
    return speeds[localLevel.value] || 0
  }
  
  update() {
    if (!isRunning.value || localMode.value === 'Stop' || !this.active) {
      return
    }
    
    if (this.type === 'outdoor') {
      // Außenstrom: vertikal nach oben mit reduziertem seitlichen Schwanken
      this.y -= this.speed * (0.8 + Math.random() * 0.4) // Geschwindigkeitsvariation 0.8-1.2
      this.x += Math.sin(this.y * 0.02) * (0.2 + Math.random() * 0.3) // Weniger seitliche Bewegung (0.2-0.5 statt 0.5-1.3)
      
      // Zurücksetzen wenn oben angekommen
      if (this.y < (this.targetY || 90)) {
        this.reset()
        return 'completed' // Signal dass dieser Strom fertig ist
      }
    } else {
      // Innenstrom: horizontal nach rechts mit reduziertem Schwanken
      this.x += this.speed * (0.9 + Math.random() * 0.2) // Geschwindigkeitsvariation 0.9-1.1
      this.y += Math.sin(this.x * 0.015) * (0.4 + Math.random() * 0.4) // Weniger Wellenbewegung (0.4-0.8 statt 0.6-1.2, ca. 30% Reduktion)
      
      // Zurücksetzen wenn rechts angekommen
      if (this.x > 570) {
        this.reset()
        return 'completed' // Signal dass dieser Strom fertig ist
      }
    }
    
    return 'running'
  }
  
  draw(ctx: CanvasRenderingContext2D) {
    if (!isRunning.value || localMode.value === 'Stop' || !this.active) return
    
    ctx.save()
    ctx.globalAlpha = this.opacity
    ctx.fillStyle = this.getColorFromMode()
    ctx.beginPath()
    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2)
    ctx.fill()
    ctx.restore()
  }
  
  getColorFromMode() {
    if (this.type === 'outdoor') {
      // Außenstrom: etwas heller/transparenter
      return '#5dade2'
    } else {
      // Innenstrom: kräftiger
      return '#3498db'
    }
  }
  
  activate() {
    this.active = true
  }
  
  deactivate() {
    this.active = false
  }
}

// ===== PROPS =====
const props = defineProps<{ 
  room?: string
  mode?: Mode
  level?: number // 1..5
  autoMode?: boolean
  autoLevel?: boolean
  outsideTemp?: number
  insideTemp?: number
  heatRecovery?: number
  filterStatus?: number // 0-100%
}>()

const emits = defineEmits<{
  (e: 'update:mode', v: Mode): void
  (e: 'update:level', v: number): void
  (e: 'update:autoMode', v: boolean): void
  (e: 'update:autoLevel', v: boolean): void
}>()

// ===== DEFAULTS (computed with fallbacks) =====
const mode = computed<Mode>(() => props.mode ?? 'Winter')
const level = computed<number>(() => props.level ?? 2)
const autoMode = computed<boolean>(() => props.autoMode ?? false)
const autoLevel = computed<boolean>(() => props.autoLevel ?? false)
const outsideTemp = computed<number>(() => props.outsideTemp ?? 22.5)
const insideTemp = computed<number>(() => props.insideTemp ?? 23.1)
const heatRecovery = computed<number>(() => props.heatRecovery ?? 85)
const filterStatus = computed<number>(() => props.filterStatus ?? 100)

// ===== LOCAL STATE (for immediate UI response, then emit) =====
const localMode = ref<Mode>(mode.value)
const localLevel = ref<number>(level.value)
const localAutoMode = ref<boolean>(autoMode.value)
const localAutoLevel = ref<boolean>(autoLevel.value)

// ===== LIVE DATA FROM IOBROKER =====
const liveOutsideTemp = ref<number>(22.5)
const liveInsideTemp = ref<number>(23.1) 
const liveHeatRecovery = ref<number>(85)
const liveFilterStatus = ref<number>(100)

// ===== DEVICES MAPPING =====
interface VentilationDatapoints {
  tempInside?: string
  tempOutside?: string  
  mode?: string
  level?: string
  autoMode?: string
  autoLevel?: string
  heatRecovery?: string
  filterStatus?: string
}

const ventilationDatapoints = ref<VentilationDatapoints>({})

// Load devices.json and map datapoints based on room
async function loadDevicesAndMapDatapoints() {
  try {
    const response = await fetch('/iobroker/devices.json')
    const devices = await response.json()
    
    const roomKey = props.room?.toLowerCase() || 'wohnzimmer'
    const roomDevices = devices[roomKey] || []
    
    // Map ventilation datapoints
    const mapping: VentilationDatapoints = {}
    
    // Temperature sensors
    const tempSensor = roomDevices.find((d: any) => d.name?.includes('Temperatur') && d.type === 'sensor')
    if (tempSensor) mapping.tempInside = tempSensor.id
    
    // Outside temperature from "Garten"
    const gardenDevices = devices['garten'] || devices['Garten'] || []
    const outsideTempSensor = gardenDevices.find((d: any) => d.name?.includes('Aussen Temperatur'))
    if (outsideTempSensor) mapping.tempOutside = outsideTempSensor.id
    
    // Ventilation controls
    const modeDevice = roomDevices.find((d: any) => d.type === 'air-mode')
    if (modeDevice) mapping.mode = modeDevice.id
    
    const levelDevice = roomDevices.find((d: any) => d.type === 'air-level')
    if (levelDevice) mapping.level = levelDevice.id
    
    const autoModeDevice = roomDevices.find((d: any) => d.type === 'air-automode')
    if (autoModeDevice) mapping.autoMode = autoModeDevice.id
    
    const autoLevelDevice = roomDevices.find((d: any) => d.type === 'air-autolevel')
    if (autoLevelDevice) mapping.autoLevel = autoLevelDevice.id
    
    // Future datapoints (will be available soon)
    if (mapping.mode) {
      // Derive from mode datapoint base path
      const basePath = mapping.mode.replace('.modus', '')
      mapping.heatRecovery = `${basePath}.wrg`
      mapping.filterStatus = `${basePath}.filter`
    }
    
    ventilationDatapoints.value = mapping
    console.log(`Ventilation datapoints for ${roomKey}:`, mapping)
    
  } catch (error) {
    console.error('Failed to load devices.json:', error)
  }
}

// Fetch live values from ioBroker
async function fetchVentilationStates() {
  const datapoints = ventilationDatapoints.value
  if (!datapoints.mode) return // No ventilation configured for this room
  
  try {
    // Fetch all values in parallel
    const requests: Promise<Response>[] = []
    const keys: string[] = []
    
    if (datapoints.tempInside) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.tempInside}`))
      keys.push('tempInside')
    }
    if (datapoints.tempOutside) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.tempOutside}`))
      keys.push('tempOutside')
    }
    if (datapoints.mode) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.mode}`))
      keys.push('mode')
    }
    if (datapoints.level) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.level}`))
      keys.push('level')
    }
    if (datapoints.autoMode) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.autoMode}`))
      keys.push('autoMode')
    }
    if (datapoints.autoLevel) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.autoLevel}`))
      keys.push('autoLevel')
    }
    if (datapoints.heatRecovery) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.heatRecovery}`))
      keys.push('heatRecovery')
    }
    if (datapoints.filterStatus) {
      requests.push(fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${datapoints.filterStatus}`))
      keys.push('filterStatus')
    }
    
    const responses = await Promise.all(requests)
    const data = await Promise.all(responses.map(r => r.json()))
    
    // Update local state with fetched values
    data.forEach((result, index) => {
      const key = keys[index]
      if (result && result.val !== undefined) {
        switch (key) {
          case 'tempInside':
            liveInsideTemp.value = Number(result.val)
            break
          case 'tempOutside':
            liveOutsideTemp.value = Number(result.val)
            break
          case 'mode':
            if (!localModeChanging.value) {
              localMode.value = result.val as Mode
            }
            break
          case 'level':
            if (!localLevelChanging.value) {
              localLevel.value = Number(result.val)
            }
            break
          case 'autoMode':
            localAutoMode.value = Boolean(result.val)
            break
          case 'autoLevel':
            localAutoLevel.value = Boolean(result.val)
            break
          case 'heatRecovery':
            liveHeatRecovery.value = Number(result.val)
            break
          case 'filterStatus':
            liveFilterStatus.value = Number(result.val)
            break
        }
      }
    })
    
  } catch (error) {
    console.error('Error fetching ventilation states:', error)
  }
}

// Send values to ioBroker
async function updateVentilationState(datapointKey: keyof VentilationDatapoints, value: any) {
  const datapoint = ventilationDatapoints.value[datapointKey]
  if (!datapoint) return
  
  try {
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${datapoint}&query=value=${value}`)
  } catch (error) {
    console.error(`Error updating ${datapointKey}:`, error)
  }
}

// Prevent overwriting local changes during user interaction
const localModeChanging = ref(false)
const localLevelChanging = ref(false)

// Function to get filter color based on status
function getFilterColor(status: number): string {
  if (status >= 90) return 'rgba(0, 100, 0, 0.4)'      // Dark green
  if (status >= 80) return 'rgba(50, 150, 0, 0.4)'     // Green  
  if (status >= 70) return 'rgba(100, 200, 0, 0.4)'    // Light green
  if (status >= 60) return 'rgba(150, 200, 50, 0.4)'   // Light yellow-green
  if (status >= 50) return 'rgba(200, 200, 0, 0.4)'    // Yellow
  if (status >= 40) return 'rgba(200, 150, 0, 0.4)'    // Dark yellow
  if (status >= 30) return 'rgba(200, 100, 0, 0.4)'    // Orange
  if (status >= 20) return 'rgba(200, 50, 0, 0.4)'     // Dark orange
  return 'rgba(150, 0, 0, 0.4)'                        // Dark red
}

watch(mode, v => (localMode.value = v))
watch(level, v => (localLevel.value = v))
watch(autoMode, v => (localAutoMode.value = v))
watch(autoLevel, v => (localAutoLevel.value = v))

watch(localMode, (v) => {
  localModeChanging.value = true
  emits('update:mode', v)
  updateVentilationState('mode', v)
  setTimeout(() => { localModeChanging.value = false }, 1000)
})

watch(localLevel, (v) => {
  localLevelChanging.value = true 
  emits('update:level', v)
  updateVentilationState('level', v)
  setTimeout(() => { localLevelChanging.value = false }, 1000)
})

watch(localAutoMode, (v) => {
  emits('update:autoMode', v)
  updateVentilationState('autoMode', v)
})

watch(localAutoLevel, (v) => {
  emits('update:autoLevel', v)
  updateVentilationState('autoLevel', v)
})

// ===== DERIVED =====
const isRunning = computed<boolean>(() => localMode.value !== 'Stop')
const roomName = computed<string>(() => (props.room ?? 'Wohnzimmer'))
const statusText = computed<string>(() => (isRunning.value ? 'AKTIV' : 'STOP'))
const levelText = computed<string>(() => `Stufe ${localLevel.value}`)

// Use live values if available, fallback to props
const displayOutsideTemp = computed<number>(() => liveOutsideTemp.value ?? outsideTemp.value)
const displayInsideTemp = computed<number>(() => liveInsideTemp.value ?? insideTemp.value)  
const displayHeatRecovery = computed<number>(() => liveHeatRecovery.value ?? heatRecovery.value)
const displayFilterStatus = computed<number>(() => liveFilterStatus.value ?? filterStatus.value)

const modes: Mode[] = ['Sommer', 'Winter', 'Party', 'Stop']

// ===== CANVAS ANIMATION =====
const canvasRef = ref<HTMLCanvasElement | null>(null)
let raf: number | null = null
let ctx: CanvasRenderingContext2D | null = null

const outdoorParticles = ref<AirParticle[]>([])
const indoorParticles = ref<AirParticle[]>([])
const fanAngle = ref<number>(0)

function initParticles(): void {
  outdoorParticles.value = []
  indoorParticles.value = []
  
  // 15 Partikel für den Außenstrom (vertikal) mit zeitlicher Verzögerung
  for (let i = 0; i < 15; i++) {
    const particle = new AirParticle('outdoor')
    
    // Verzögerte Aktivierung für natürlicheren Effekt
    setTimeout(() => {
      particle.activate()
    }, Math.random() * 3000) // Über 3 Sekunden verteilt
    
    outdoorParticles.value.push(particle)
  }
  
  // 20 Partikel für den Innenstrom (horizontal) mit zeitlicher Verzögerung
  for (let i = 0; i < 20; i++) {
    const particle = new AirParticle('indoor')
    
    // Verzögerte Aktivierung für natürlicheren Effekt
    setTimeout(() => {
      particle.activate()
    }, Math.random() * 2500) // Über 2.5 Sekunden verteilt
    
    indoorParticles.value.push(particle)
  }
}

function drawWall(ctx: CanvasRenderingContext2D) {
  // Außenwand (vertikal, längs aufgeschnitten) - zurück zur ursprünglichen Position
  const unitWidth = 300
  const wallX = 150 // Gleiche X-Position wie das Gerät
  
  // Kies Pattern 1 für äußere Streifen (helles Zementgrau für Putz)
  function createKies1Pattern(): CanvasPattern | null {
    const patternCanvas = document.createElement('canvas')
    patternCanvas.width = 20
    patternCanvas.height = 20
    const pCtx = patternCanvas.getContext('2d')!
    
    // Hintergrund helles Zementgrau
    pCtx.fillStyle = '#d4d0cc'
    pCtx.fillRect(0, 0, 20, 20)
    
    // Kreise und Rechtecke in Putz-Grautönen
    pCtx.fillStyle = '#c2beb9'
    pCtx.strokeStyle = '#b8b4af'
    pCtx.lineWidth = 0.5
    
    // Kreise
    pCtx.beginPath()
    pCtx.arc(5, 7, 1, 0, Math.PI * 2)
    pCtx.fill()
    pCtx.stroke()
    
    pCtx.beginPath()
    pCtx.arc(5, 17, 1, 0, Math.PI * 2)
    pCtx.fill()
    pCtx.stroke()
    
    pCtx.beginPath()
    pCtx.arc(17, 5, 1, 0, Math.PI * 2)
    pCtx.fill()
    pCtx.stroke()
    
    pCtx.beginPath()
    pCtx.arc(13, 13, 1, 0, Math.PI * 2)
    pCtx.fill()
    pCtx.stroke()
    
    // Rechtecke
    pCtx.fillRect(2, 2, 1, 1)
    pCtx.fillRect(10, 8, 1, 1)
    pCtx.fillRect(16, 12, 1, 1)
    pCtx.fillRect(4, 12, 1, 1)
    pCtx.fillRect(18, 18, 1, 1)
    pCtx.fillRect(13, 16, 1, 1)
    
    return ctx.createPattern(patternCanvas, 'repeat')
  }
  
  // Kies Pattern 2 für Hauptwand
  function createKies2Pattern(): CanvasPattern | null {
    const patternCanvas = document.createElement('canvas')
    patternCanvas.width = 100
    patternCanvas.height = 100
    const pCtx = patternCanvas.getContext('2d')!
    
    // Hintergrund
    pCtx.fillStyle = '#c6bbb5'
    pCtx.fillRect(0, 0, 100, 100)
    
    pCtx.strokeStyle = 'black'
    pCtx.lineWidth = 0.5
    
    // Kiesel-Pfade definieren und zeichnen
    const kieselPaths = {
      0: "M5,20 c-2,-4 -2,-8 -1,-12 c3-2,5-6,10-4c4,2.3,6.3,6.4,0,12 c0,0 -6,10 -9,4",
      1: "M5,15 c-5,-10 10,-20 20,-5 c5,5 0,20 -9,14 z",
      2: "M5,15 c-5,-10 10,-20 20,-5 l5,7 c5,5 0,20 -9,14 c-10,-5 0,-5 -15,-15 z",
      3: "M5,20 C3,16 7,15 8,11 11,9 9,2 14,4 c4,8 8.4,4 9,16.3 0,0 -11.5,6.6 -18,-0.3",
      4: "m 10,15 c -5,-10 15.5,-20 12,-5 -1,3.4 2,10 -6,6 z",
      5: "m10,12 c-0,-5 3,-1.75 4,-6 5.7,1.4 0.36,-1.33 5,1 4.35,8 -2,9 -2,9 0,0 -2,2 -7,-4",
      6: "m3,15 C-1,8 12,-2.5 12.6,6.8 12,9 12,13 3,15"
    }
    
    // Kiesel zeichnen mit verschiedenen Positionen und Farben
    const kieselData = [
      { type: 0, x: 1, y: 1, fill: '#9c8c7d' },
      { type: 0, x: 29, y: 15, fill: '#f7e7d7' },
      { type: 0, x: 54, y: 90, fill: '#937962' },
      { type: 0, x: 54, y: -10, fill: '#937962' },
      { type: 1, x: 0, y: 70, fill: '#e5d3bd' },
      { type: 1, x: -15, y: 40, fill: '#e5d3bd' },
      { type: 1, x: 38, y: 48, fill: '#937962' },
      { type: 1, x: 85, y: 40, fill: '#e5d3bd' },
      { type: 1, x: 65, y: 0, fill: '#f7e7d7' },
      { type: 2, x: 15, y: 55, fill: '#c7b29f' },
      { type: 2, x: 45, y: 12, fill: '#e5d3bd' },
      { type: 3, x: 88, y: 15, fill: '#f7e7d7' },
      { type: 3, x: -12, y: 15, fill: '#f7e7d7' },
      { type: 3, x: 40, y: 72, fill: '#f7e7d7' },
      { type: 3, x: 36, y: 26, fill: '#f7e7d7' },
      { type: 3, x: 75, y: 75, fill: '#e5d3bd' },
      { type: 4, x: 69, y: 25, fill: '#937962' },
      { type: 4, x: 18, y: 88, fill: '#e5d3bd' },
      { type: 4, x: 18, y: -12, fill: '#e5d3bd' },
      { type: 4, x: 5, y: 15, fill: '#f7e7d7' },
      { type: 5, x: 10, y: 0, fill: '#c7b29f' },
      { type: 5, x: -3, y: 31, fill: '#f7e7d7' },
      { type: 5, x: 63, y: 36, fill: '#f7e7d7' },
      { type: 5, x: 35, y: -3, fill: '#f7e7d7' },
      { type: 5, x: 20, y: 40, fill: '#c7b29f' },
      { type: 5, x: 55, y: 77, fill: '#c7b29f' },
      { type: 5, x: 78, y: 60, fill: '#e5d3bd' },
      { type: 5, x: 50, y: 40, fill: '#e5d3bd' },
      { type: 6, x: 30, y: 3, fill: '#e5d3bd' },
      { type: 6, x: 20, y: 30, fill: '#c7b29f' },
      { type: 6, x: 0, y: -10, fill: '#937962' },
      { type: 6, x: 0, y: 90, fill: '#937962' },
      { type: 6, x: 67, y: 50, fill: '#f7e7d7' },
      { type: 6, x: 77, y: 55, fill: '#937962' }
    ]
    
    kieselData.forEach(kiesel => {
      pCtx.save()
      pCtx.translate(kiesel.x, kiesel.y)
      pCtx.fillStyle = kiesel.fill
      pCtx.strokeStyle = 'black'
      pCtx.lineWidth = 0.5
      
      const path = new Path2D(kieselPaths[kiesel.type as keyof typeof kieselPaths])
      pCtx.fill(path)
      pCtx.stroke(path)
      pCtx.restore()
    })
    
    return ctx.createPattern(patternCanvas, 'repeat')
  }
  
  // Äußere Streifen mit Kies Pattern 1 (helle Farben)
  const kies1Pattern = createKies1Pattern()
  if (kies1Pattern) {
    ctx.fillStyle = kies1Pattern
    ctx.fillRect(wallX, 30, 10, 240) // Linker Streifen
    ctx.fillRect(wallX + unitWidth - 10, 30, 10, 240) // Rechter Streifen
  }
  
  // Hauptwand mit Kies Pattern 2
  const kies2Pattern = createKies2Pattern()
  if (kies2Pattern) {
    ctx.fillStyle = kies2Pattern
    ctx.fillRect(wallX + 10, 30, unitWidth - 20, 240) // Hauptwand
  }
}

function drawVentilationUnit(ctx: CanvasRenderingContext2D) {
  // Lüftungsgerät Gehäuse (längs aufgeschnitten)
  const unitX = 150
  const unitY = 80
  const unitWidth = 300
  const unitHeight = 140
  
  // Hauptgehäuse
  ctx.fillStyle = '#34495e'
  ctx.fillRect(unitX, unitY, unitWidth, unitHeight)
  ctx.strokeStyle = '#2c3e50'
  ctx.lineWidth = 5
  ctx.strokeRect(unitX, unitY, unitWidth, unitHeight)
  
  // Innenaufteilung in 4 Kammern
  ctx.strokeStyle = '#2c3e50'
  ctx.lineWidth = 3
  
  // Vertikale Trennwände
  const sectionWidth = unitWidth / 4
  for (let i = 1; i < 4; i++) {
    ctx.beginPath()
    ctx.moveTo(unitX + i * sectionWidth, unitY)
    ctx.lineTo(unitX + i * sectionWidth, unitY + unitHeight)
    ctx.stroke()
  }
  
  // Wärmetauscher (kreuzförmige Struktur) - an der Innenseite positioniert
  ctx.strokeStyle = '#ffd39b'
  ctx.lineWidth = 4
  const heatExchangerX = 320 // Mehr zur Innenseite verschoben
  const heatExchangerWidth = 100 // Kompakter Wärmetauscher
  
  // Horizontale Lamellen
  for (let i = 0; i < 17; i++) {
    const y = unitY + 5 + (i * 8)
    ctx.beginPath()
    ctx.moveTo(heatExchangerX, y)
    ctx.lineTo(heatExchangerX + heatExchangerWidth, y)
    ctx.stroke()
  }
  
  // Vertikale Lamellen  
  for (let i = 0; i < 12; i++) {
    const x = heatExchangerX + 1 + (i * 9)
    ctx.beginPath()
    ctx.moveTo(x, unitY + 6)
    ctx.lineTo(x, unitY + unitHeight - 5)
    ctx.stroke()
  }
  
  // Filter andeuten - alle zur Innenseite verschoben mit filterstatus-abhängiger Farbe
  const filterColor = getFilterColor(displayFilterStatus.value)
  ctx.fillStyle = filterColor
  ctx.fillRect(295, unitY, 25, unitHeight) // Innerer Filter (vor dem Wärmetauscher)
  ctx.fillRect(420, unitY, 25, unitHeight) // Äußerer Filter (nach dem Wärmetauscher, nah zur Innenseite)
  
  // Innenabdeckung/Gitter an der Innenseite der Wand zeichnen
  const coverX = unitX + unitWidth // Ganz rechts an der Innenseite
  const coverY = unitY -10 // Bündig mit dem Gerät
  const coverWidth = 35 // Nur halb so breit (70 / 2)
  const coverHeight = unitHeight + 20 // So hoch wie der dunkelgraue Balken
  
  // Abdeckungsrahmen (weiß/hellgrau)
  ctx.fillStyle = '#f8f9fa'
  ctx.fillRect(coverX, coverY, coverWidth, coverHeight)
  ctx.strokeStyle = '#dee2e6'
  ctx.lineWidth = 2
  ctx.strokeRect(coverX, coverY, coverWidth, coverHeight)
  
  // Raster von Schlitzen - aufgeteilt in Breite UND Höhe
  ctx.fillStyle = '#343a40'
  const slitWidth = 3
  const slitHeight = 20 // Erhöht von 16 auf 20
  const horizontalSpacing = 6 // Abstand horizontal
  const verticalSpacing = 24 // Abstand vertikal angepasst für größere Schlitze
  
  const slotsX = Math.floor((coverWidth - 6) / horizontalSpacing) // Anzahl Spalten
  const slotsY = Math.floor((coverHeight - 8) / verticalSpacing) // Anzahl Reihen
  
  // Berechne Startposition für zentrierte Schlitze
  const totalWidthUsed = slotsX * horizontalSpacing - (horizontalSpacing - slitWidth)
  const totalHeightUsed = slotsY * verticalSpacing - (verticalSpacing - slitHeight)
  const startX = coverX + (coverWidth - totalWidthUsed) / 2
  const startY = coverY + (coverHeight - totalHeightUsed) / 2
  
  for (let row = 0; row < slotsY; row++) {
    for (let col = 0; col < slotsX; col++) {
      const slitX = startX + (col * horizontalSpacing)
      const slitY = startY + (row * verticalSpacing)
      ctx.fillRect(slitX, slitY, slitWidth, slitHeight)
    }
  }
}

function drawOutsideCover(ctx: CanvasRenderingContext2D) {
  const wallLeftX = 150 // Linke Seite der Wand
  const unitY = 80 // Gleiche Y-Position wie das Gerät
  const unitHeight = 140 // Gleiche Höhe wie der dunkelgraue Balken
  
  // 1. Rechteckige Grundplatte an der Wand (dünner - 60% der ursprünglichen Breite)
  ctx.fillStyle = '#ddd'
  ctx.strokeStyle = '#333'
  ctx.lineWidth = 2
  ctx.fillRect(wallLeftX - 9, unitY - 15, 9, unitHeight + 30) // 9px breit (60% von 15px)
  ctx.strokeRect(wallLeftX - 9, unitY - 15, 9, unitHeight + 30)
  
  // 2. Haube mit EINER Biegung (diagonal nach außen, mit sanftem Farbverlauf)
  
  // Erstelle Farbverlauf von hell oben links zu dunkel unten rechts
  const gradient = ctx.createLinearGradient(wallLeftX - 9, unitY, wallLeftX - 70, unitY + unitHeight)
  gradient.addColorStop(0, '#f8f8f8')   // Sehr hell oben
  gradient.addColorStop(0.3, '#f0f0f0') // Hell
  gradient.addColorStop(0.7, '#e8e8e8') // Mittel
  gradient.addColorStop(1, '#d8d8d8')   // Dunkler unten
  
  ctx.fillStyle = gradient
  ctx.strokeStyle = '#333'
  ctx.lineWidth = 2
  
  ctx.beginPath()
  // Start oben an der dünneren Grundplatte
  ctx.moveTo(wallLeftX - 9, unitY)
  // Eine geschwungene Linie diagonal nach außen, Spitze nur um 15px angehoben (50% weniger steil)
  ctx.quadraticCurveTo(wallLeftX - 60, unitY + 30, wallLeftX - 70, unitY + unitHeight - 15)
  // Zurück zur dünneren Grundplatte unten (schräg)
  ctx.lineTo(wallLeftX - 9, unitY + unitHeight)
  // Und wieder hoch zur Grundplatte oben
  ctx.lineTo(wallLeftX - 9, unitY)
  ctx.closePath()
  ctx.fill()
  ctx.stroke()
}

function drawSingleFan(ctx: CanvasRenderingContext2D, centerX: number, centerY: number, radius: number, color: string) {
  ctx.save()
  ctx.translate(centerX, centerY)
  ctx.rotate(fanAngle.value)
  
  // Ventilator-Flügel
  ctx.fillStyle = color
  for (let i = 0; i < 3; i++) {
    ctx.save()
    ctx.rotate((i * 2 * Math.PI) / 3)
    
    ctx.beginPath()
    ctx.ellipse(0, -radius * 0.7, radius * 0.3, radius * 0.8, 0, 0, Math.PI * 2)
    ctx.fill()
    
    ctx.restore()
  }
  
  // Ventilator-Nabe
  ctx.fillStyle = '#34495e'
  ctx.beginPath()
  ctx.arc(0, 0, 6, 0, Math.PI * 2)
  ctx.fill()
  
  ctx.restore()
  
  // Rotation aktualisieren
  const rotationSpeed = localLevel.value * 0.2
  fanAngle.value += rotationSpeed
}

function drawStaticFan(ctx: CanvasRenderingContext2D, centerX: number, centerY: number, radius: number, color: string) {
  ctx.save()
  ctx.translate(centerX, centerY)
  // Keine Rotation für statischen Ventilator
  
  // Ventilator-Flügel in fixer Position
  ctx.fillStyle = color
  for (let i = 0; i < 3; i++) {
    ctx.save()
    ctx.rotate((i * 2 * Math.PI) / 3)
    
    ctx.beginPath()
    ctx.ellipse(0, -radius * 0.7, radius * 0.3, radius * 0.8, 0, 0, Math.PI * 2)
    ctx.fill()
    
    ctx.restore()
  }
  
  // Ventilator-Nabe
  ctx.fillStyle = '#34495e'
  ctx.beginPath()
  ctx.arc(0, 0, 6, 0, Math.PI * 2)
  ctx.fill()
  
  ctx.restore()
  // Kein Rotations-Update für statischen Ventilator
}

function drawFan(ctx: CanvasRenderingContext2D) {
  // Außenabdeckung zeichnen (an der Außenseite der Wand)
  drawOutsideCover(ctx)
  
  // Ventilator immer zeichnen, aber nur im Stop-Modus ohne Rotation
  const fan1X = 200
  const fan1Y = 150
  
  if (localMode.value === 'Stop') {
    // Im Stop-Modus: Ventilator ohne Rotation zeichnen
    drawStaticFan(ctx, fan1X, fan1Y, 25, '#3498db')
  } else if (isRunning.value) {
    // Normal: Ventilator mit Rotation zeichnen
    drawSingleFan(ctx, fan1X, fan1Y, 25, '#3498db')
  } else {
    // Ausgeschaltet: Ventilator ohne Rotation zeichnen
    drawStaticFan(ctx, fan1X, fan1Y, 25, '#3498db')
  }
}

function drawVentilationSystem() {
  if (!ctx || !canvasRef.value) return
  
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
  
  // Wand zeichnen
  drawWall(ctx)
  
  // Lüftungsgerät zeichnen
  drawVentilationUnit(ctx)
  
  // Ventilator zeichnen (rotierend)
  drawFan(ctx)
  
  // Outdoor-Partikel animieren (Außenstrom)
  outdoorParticles.value.forEach(particle => {
    particle.update()
    particle.draw(ctx!)
  })
  
  // Indoor-Partikel animieren (Innenstrom)
  indoorParticles.value.forEach(particle => {
    particle.update()
    particle.draw(ctx!)
  })
}

function tick(): void {
  drawVentilationSystem()
  raf = requestAnimationFrame(tick)
}

// Watch for mode/level changes to update particles
watch(localMode, () => {
  if (localMode.value === 'Stop') {
    outdoorParticles.value.forEach(particle => particle.deactivate())
    indoorParticles.value.forEach(particle => particle.deactivate())
  } else {
    outdoorParticles.value.forEach(particle => {
      particle.activate()
      particle.reset()
    })
    indoorParticles.value.forEach(particle => {
      particle.activate()
      particle.reset()
    })
  }
})

watch(localLevel, () => {
  // Geschwindigkeit für alle Partikel-Typen aktualisieren
  outdoorParticles.value.forEach(particle => {
    particle.speed = particle.getSpeedFromLevel()
  })
  indoorParticles.value.forEach(particle => {
    particle.speed = particle.getSpeedFromLevel()
  })
})

onMounted(() => {
  if (canvasRef.value) {
    ctx = canvasRef.value.getContext('2d')
    initParticles()
    raf = requestAnimationFrame(tick)
  }
  
  // Load device mappings and start polling
  loadDevicesAndMapDatapoints().then(() => {
    fetchVentilationStates() // Initial fetch
    // Poll every 5 seconds
    setInterval(fetchVentilationStates, 5000)
  })
})

onUnmounted(() => {
  if (raf !== null) cancelAnimationFrame(raf)
})

// ===== LEVEL CONTROL FUNCTIONS =====
function incrementLevel() {
  if (localLevel.value < 5) {
    localLevel.value++
  }
}

function decrementLevel() {
  if (localLevel.value > 1) {
    localLevel.value--
  }
}

function validateLevel() {
  if (localLevel.value < 1) {
    localLevel.value = 1
  } else if (localLevel.value > 5) {
    localLevel.value = 5
  } else if (!Number.isInteger(localLevel.value)) {
    localLevel.value = Math.round(localLevel.value)
  }
}
</script>

<script lang="ts">
export default { name: 'LueftungWohnzimmerCard' }
</script>

<style scoped>
.vent-card { border-radius: 16px; }
.gap-2 { gap: .5rem; }
.gap-3 { gap: .75rem; }

.viz-wrapper {
  position: relative;
  border: 2px solid #e0e0e0;
  border-radius: 14px;
  overflow: hidden;
  background: linear-gradient(90deg, #e3f2fd, #ffffff 40%, #fff3e0);
}

.viz-canvas { 
  width: 100%; 
  height: 300px; 
  display: block; 
}

.mode-badge {
  position: absolute;
  top: 8px; left: 50%; transform: translateX(-50%);
  background: rgba(255,255,255,0.95);
  border: 2px solid #2196f3;
  padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 12px;
  z-index: 10;
}

.cycle-badge {
  position: absolute; top: 8px; right: 8px; font-size: 11px;
  background: rgba(149,165,166,0.9); color: white; padding: 4px 8px; border-radius: 10px;
  z-index: 10;
}
.cycle-badge.active { background: rgba(33,150,243,0.9); }

.legend { 
  display: flex; 
  gap: 16px; 
  justify-content: center; 
  font-size: 12px; 
  padding: 6px 0 10px; 
  background: rgba(255, 255, 255, 0.9);
}
.legend .dot { width: 10px; height: 10px; border-radius: 50%; display:inline-block; margin-right:6px; }

.controls { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
@media (max-width: 960px) { .controls { grid-template-columns: 1fr; } }

.level-input {
  max-width: 80px;
  text-align: center;
}

.level-input :deep(.v-field__input) {
  text-align: center;
  padding: 0 8px;
}

.temp-pill { --v-theme-overlay-multiplier: 0; }
</style>
