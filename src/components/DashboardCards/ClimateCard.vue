<template>
  <v-card class="climate-card">
    <v-card-title>Klimaanlage {{ roomName }}</v-card-title>
    <v-card-text>
      <!-- Klimaanlagen-Visualisierung -->
      <div class="climate-unit-container">
        <div class="climate-unit">
          <div class="unit-body">
            <div class="unit-logo">DIMSTAL</div>
            <div class="unit-display">{{ targetTemperature }}°C</div>
            <div class="status-led" :class="{ 
              'led-cool': powerState && operationalMode === '2',
              'led-heat': powerState && operationalMode === '4', 
              'led-fan': powerState && operationalMode === '5',
              'led-off': !powerState 
            }"></div>
            
            <div class="swing-indicator" :class="{ active: swingMode !== '0' }">
              {{ getSwingText() }}
            </div>
            
            <div class="unit-vents" :class="getSwingClass()">
              <div class="vent-lamella" v-for="n in 8" :key="n"></div>
            </div>
          </div>
          
          <!-- Partikel Container -->
          <div ref="particleContainerRef" class="particle-container" :class="getSwingClass()">
            <!-- Partikel werden hier dynamisch eingefügt -->
          </div>
        </div>
        
        <!-- Temperatur-Anzeige -->
        <div class="temp-display">
          <span v-if="powerState">Soll: {{ targetTemperature }}°C | Ist: {{ currentTemperature }}°C</span>
          <span v-else>Klimaanlage ausgeschaltet</span>
        </div>
      </div>

      <!-- Original Controls -->
      <div class="mt-4">
        <div class="mt-2 mb-2" style="display: flex; align-items: center; gap: 12px; justify-content: center;">
          <v-btn
            icon
            size="small"
            color="primary"
            :disabled="targetTemperature <= 17"
            @click="decreaseTemperature"
          >
            <v-icon>mdi-minus</v-icon>
          </v-btn>
          
          <div class="temperature-display">
            <span class="temperature-value">{{ targetTemperature }}°C</span>
            <span class="temperature-label">Solltemperatur</span>
          </div>
          
          <v-btn
            icon
            size="small"
            color="primary"
            :disabled="targetTemperature >= 30"
            @click="increaseTemperature"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </div>
        
        <v-row>
          <v-col cols="12" md="6">
            <v-switch
              v-model="powerState"
              :label="powerState ? 'An' : 'Aus'"
              @change="setState('powerState', powerState); updateAnimations()"
            />
            <v-switch
              v-model="turboMode"
              label="Turbo"
              @change="setState('turboMode', turboMode); updateAnimations()"
            />
            <v-switch
              v-model="ecoMode"
              label="Eco"
              @change="setState('ecoMode', ecoMode)"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="fanSpeed"
              :items="fanSpeeds"
              label="Lüfterstufe"
              item-title="label"
              item-value="value"
              @update:modelValue="setState('fanSpeed', fanSpeed); updateAnimations()"
            />
            <v-select
              v-model="operationalMode"
              :items="operationalModes"
              label="Modus"
              item-title="label"
              item-value="value"
              @update:modelValue="setState('operationalMode', operationalMode); updateAnimations()"
            />
            <v-select
              v-model="swingMode"
              :items="swingModes"
              label="Swing"
              item-title="label"
              item-value="value"
              @update:modelValue="setState('swingMode', swingMode); updateAnimations()"
            />
          </v-col>
        </v-row>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted, watch, computed, onUnmounted, nextTick } from 'vue'

// ===== PROPS =====
const props = defineProps({ 
  room: {
    type: String,
    required: true
  }
})

// ===== REACTIVE DATA =====
const powerState = ref(false)
const turboMode = ref(false)
const ecoMode = ref(false)
const targetTemperature = ref(22)
const currentTemperature = ref(22)
const fanSpeed = ref('')
const operationalMode = ref('')
const swingMode = ref('')

// Device configuration
const climatePrefix = ref('')
const tempSensorId = ref('')

// Animation
const particleContainerRef = ref(null)
let particleInterval = null
let extraParticleInterval = null

// ===== COMPUTED =====
const roomName = computed(() => {
  const roomNames = {
    'wohnzimmer': 'Wohnzimmer',
    'schlafzimmer': 'Schlafzimmer', 
    'esszimmer': 'Esszimmer'
  }
  return roomNames[props.room] || props.room
})

// ===== CONSTANTS =====
const fanSpeeds = [
  { label: 'Silent', value: '20' },
  { label: 'Low', value: '40' },
  { label: 'Medium', value: '60' },
  { label: 'High', value: '80' },
  { label: 'Auto', value: '102' }
]

const operationalModes = [
  { label: 'Auto', value: '1' },
  { label: 'Cool', value: '2' },
  { label: 'Dry', value: '3' },
  { label: 'Heat', value: '4' },
  { label: 'Fan_only', value: '5' }
]

const swingModes = [
  { label: 'Off', value: '0' },
  { label: 'Horizontal', value: '3' },
  { label: 'Vertical', value: '12' },
  { label: 'Both', value: '15' }
]

// ===== METHODS =====

// Temperature control methods
function increaseTemperature() {
  if (targetTemperature.value < 30) {
    targetTemperature.value++
    setState('targetTemperature', targetTemperature.value)
  }
}

function decreaseTemperature() {
  if (targetTemperature.value > 17) {
    targetTemperature.value--
    setState('targetTemperature', targetTemperature.value)
  }
}

// Device configuration loading
async function loadDeviceConfig() {
  try {
    const response = await fetch('/iobroker/devices.json')
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }
    
    const devices = await response.json()
    const roomDevices = devices[props.room] || []
    
    // Find climate device
    const climateDevice = roomDevices.find(device => 
      device.id && device.id.includes('control.powerState')
    )
    
    if (climateDevice) {
      const deviceId = climateDevice.id
      climatePrefix.value = deviceId.replace('.powerState', '') + '.'
    }
    
    // Find temperature sensor
    const tempDevice = roomDevices.find(device => 
      device.name && device.name.toLowerCase().includes('temperatur') &&
      device.type === 'sensor' &&
      !device.name.toLowerCase().includes('soll')
    )
    
    if (tempDevice) {
      tempSensorId.value = tempDevice.id
    }
    
  } catch (error) {
    console.error('Fehler beim Laden der devices.json:', error)
    // Fallback values
    setFallbackValues()
  }
}

function setFallbackValues() {
  if (props.room === 'wohnzimmer') {
    climatePrefix.value = 'midea.0.145135534992585.control.'
    tempSensorId.value = 'tuya.0.bf2557b234ab753959fp8n.1'
  } else if (props.room === 'schlafzimmer') {
    climatePrefix.value = 'midea.0.19791209303536.control.'
    tempSensorId.value = 'tuya.0.bf4ee5091e3b1689eer3nq.1'
  }
}

// State management
async function setState(key, value) {
  if (!climatePrefix.value) {
    console.warn('Climate prefix not available, cannot set state')
    return
  }
  
  let val = value
  if (typeof value === 'boolean') {
    val = value ? 'true' : 'false'
  } else if (['fanSpeed', 'operationalMode', 'swingMode'].includes(key)) {
    val = Number(value)
  }
  
  try {
    const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${climatePrefix.value}${key}&query=value=${encodeURIComponent(val)}`
    await fetch(url)
  } catch (error) {
    console.error(`Fehler beim Setzen von ${key}:`, error)
  }
}

async function fetchStates() {
  if (!climatePrefix.value) {
    await loadDeviceConfig()
  }
  
  if (!climatePrefix.value) {
    console.error('No climate prefix available')
    return
  }
  
  const states = [
    { key: 'powerState', ref: powerState },
    { key: 'turboMode', ref: turboMode },
    { key: 'ecoMode', ref: ecoMode },
    { key: 'targetTemperature', ref: targetTemperature },
    { key: 'fanSpeed', ref: fanSpeed },
    { key: 'operationalMode', ref: operationalMode },
    { key: 'swingMode', ref: swingMode }
  ]
  
  for (const state of states) {
    try {
      const response = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${climatePrefix.value}${state.key}`)
      const data = await response.json()
      
      if (['fanSpeed', 'operationalMode', 'swingMode'].includes(state.key)) {
        state.ref.value = data.val !== undefined && data.val !== null ? String(data.val) : ''
      } else {
        state.ref.value = data.val
      }
    } catch (error) {
      console.error(`Fehler beim Laden von ${state.key}:`, error)
    }
  }
  
  // Load temperature
  if (tempSensorId.value) {
    try {
      const response = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${tempSensorId.value}`)
      const data = await response.json()
      currentTemperature.value = Math.round(data.val || 22)
    } catch (error) {
      console.error('Fehler beim Laden der Temperatur:', error)
      currentTemperature.value = 22
    }
  }
  
  await nextTick()
  updateAnimations()
}

// Animation
function getSwingClass() {
  if (swingMode.value === '3') return 'swing-horizontal'
  if (swingMode.value === '12') return 'swing-vertical'  
  if (swingMode.value === '15') return 'swing-both'
  return ''
}

function getSwingText() {
  if (swingMode.value === '3') return 'HORIZONTAL'
  if (swingMode.value === '12') return 'VERTIKAL'
  if (swingMode.value === '15') return 'BEIDE'
  return 'SWING'
}

function createParticle() {
  if (!powerState.value) return
  
  const container = particleContainerRef.value
  if (!container) return
  
  const particle = document.createElement('div')
  
  // Partikel Setup
  particle.style.position = 'absolute'
  particle.style.width = '8px'
  particle.style.height = '8px'
  particle.style.borderRadius = '50%'
  particle.style.zIndex = '9999'
  particle.style.pointerEvents = 'none'
  particle.style.opacity = '0'
  
  // Farbe basierend auf Modus
  if (operationalMode.value === '2') {
    particle.style.background = 'radial-gradient(circle, #3498db, rgba(52,152,219,0.3))'
  } else if (operationalMode.value === '4') {
    particle.style.background = 'radial-gradient(circle, #e74c3c, rgba(231,76,60,0.3))'
  } else {
    particle.style.background = 'radial-gradient(circle, #95a5a6, rgba(149,165,166,0.3))'
  }
  
  // UMGEKEHRTE Startposition (von rechts-oben nach links-unten)
  const startLeft = 65 + Math.random() * 25    // Start RECHTS (65-90%)
  const endLeft = 15 + Math.random() * 40      // Ende LINKS (15-55%)
  
  particle.style.left = startLeft + '%'
  particle.style.top = '25px'
  
  container.appendChild(particle)
  
  // JAVASCRIPT ANIMATION
  let progress = 0
  const duration = 2500
  const startTime = Date.now()
  
  function animate() {
    const elapsed = Date.now() - startTime
    progress = elapsed / duration
    
    if (progress >= 1) {
      if (container.contains(particle)) {
        container.removeChild(particle)
      }
      return
    }
    
    const easeOut = 1 - Math.pow(1 - progress, 3)
    
    const currentTop = 45 + (110 * easeOut)
    const currentLeft = startLeft + ((endLeft - startLeft) * easeOut)
    
    let opacity
    if (progress < 0.1) {
      opacity = progress / 0.1
    } else if (progress > 0.8) {
      opacity = (1 - progress) / 0.2
    } else {
      opacity = 0.7
    }
    
    const scale = 0.3 + (0.7 * Math.min(progress * 2, 1))
    
    particle.style.top = currentTop + 'px'
    particle.style.left = currentLeft + '%'
    particle.style.opacity = opacity
    particle.style.transform = `scale(${scale})`
    
    requestAnimationFrame(animate)
  }
  
  requestAnimationFrame(animate)
}

function updateAnimations() {
  clearInterval(particleInterval)
  clearInterval(extraParticleInterval)
  particleInterval = null
  extraParticleInterval = null
  
  const container = particleContainerRef.value
  if (container) {
    container.innerHTML = ''
  }
  
  if (!powerState.value) return
  
  let interval = 800
  const speed = parseInt(fanSpeed.value) || 40
  
  if (speed <= 20) interval = 1200
  else if (speed <= 40) interval = 900
  else if (speed <= 60) interval = 600
  else if (speed <= 80) interval = 400
  else interval = 200
  
  if (turboMode.value) {
    interval = Math.max(interval / 2, 150)
  }
  
  particleInterval = setInterval(createParticle, interval)
  
  if (speed >= 60) {
    setTimeout(() => {
      extraParticleInterval = setInterval(createParticle, interval * 1.5)
    }, interval / 3)
  }
}

// ===== LIFECYCLE =====
watch(() => props.room, async () => {
  await loadDeviceConfig()
  await fetchStates()
}, { immediate: true })

onMounted(async () => {
  await loadDeviceConfig()
  await fetchStates()
})

onUnmounted(() => {
  clearInterval(particleInterval)
  clearInterval(extraParticleInterval)
})
</script>

<style scoped>
.climate-card {
  position: relative;
}

.climate-unit-container {
  margin: 20px 0;
}

.climate-unit {
  position: relative;
  width: 320px;
  height: 120px;
  margin: 0 auto 20px;
  background: linear-gradient(145deg, #f8f9fa, #e9ecef);
  border-radius: 15px;
  box-shadow: 
    0 8px 16px rgba(0,0,0,0.1),
    inset 0 1px 0 rgba(255,255,255,0.5);
  overflow: visible;
}

.unit-body {
  width: 100%;
  height: 100%;
  background: #ffffff;
  border-radius: 15px;
  position: relative;
  border: 2px solid #dbdfe2;
  overflow: hidden;
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.unit-display {
  position: absolute;
  top: 15px;
  right: 20px;
  background: #1a1a1a;
  color: #00ff00;
  padding: 5px 10px;
  border-radius: 5px;
  font-family: 'Courier New', monospace;
  font-size: 14px;
  font-weight: bold;
}

.unit-logo {
  position: absolute;
  top: 15px;
  left: 20px;
  color: #666;
  font-size: 12px;
  font-weight: bold;
}

.unit-vents {
  position: absolute;
  bottom: 20px;
  left: 20px;
  right: 20px;
  height: 30px;
  background: #2c3e50;
  border-radius: 5px;
  display: flex;
  align-items: center;
  justify-content: space-around;
}

.vent-lamella {
  width: 2px;
  height: 20px;
  background: #34495e;
  border-radius: 1px;
  transition: transform 0.5s ease;
  transform-origin: center;
}

.status-led {
  position: absolute;
  top: 50%;
  left: 10px;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.led-cool {
  background: #3498db;
  box-shadow: 0 0 10px rgba(52, 152, 219, 0.6);
}

.led-heat {
  background: #e74c3c;
  box-shadow: 0 0 10px rgba(231, 76, 60, 0.6);
}

.led-fan {
  background: #f39c12;
  box-shadow: 0 0 10px rgba(243, 156, 18, 0.6);
}

.led-off {
  background: #27ae60;
  box-shadow: 0 0 10px rgba(39, 174, 96, 0.6);
}

.swing-indicator {
  position: absolute;
  top: -10px;
  right: 10px;
  background: rgba(155, 89, 182, 0.9);
  color: white;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: bold;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.swing-indicator.active {
  opacity: 1;
}

.particle-container {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 200px;
  pointer-events: none;
  overflow: visible;
  z-index: 5;
}

.temp-display {
  font-size: 18px;
  font-weight: bold;
  text-align: center;
  color: #2c3e50;
  margin-bottom: 10px;
}

.temperature-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 140px;
  padding: 16px 20px;
  background: linear-gradient(145deg, #f5f5f5, #e8e8e8);
  border: none;
  border-radius: 20px;
  box-shadow: 
    0 8px 20px rgba(0, 0, 0, 0.15),
    inset 0 1px 0 rgba(255, 255, 255, 0.8),
    inset 0 -1px 0 rgba(0, 0, 0, 0.1);
  position: relative;
  transition: all 0.3s ease;
}

.temperature-display:hover {
  transform: translateY(-2px);
  box-shadow: 
    0 12px 25px rgba(0, 0, 0, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.9),
    inset 0 -1px 0 rgba(0, 0, 0, 0.15);
}

.temperature-display::before {
  content: '';
  position: absolute;
  top: 3px;
  left: 15%;
  right: 15%;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
  border-radius: 50%;
}

.temperature-value {
  font-size: 26px;
  font-weight: bold;
  color: #2c3e50;
  line-height: 1;
  text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
}

.temperature-label {
  font-size: 12px;
  color: #7f8c8d;
  margin-top: 4px;
  font-weight: 500;
  text-shadow: 0 1px 1px rgba(255, 255, 255, 0.5);
}

/* Swing-Animationen für Lamellen */
.swing-horizontal .vent-lamella:nth-child(odd) {
  animation: lamellaSwingH1 3s infinite ease-in-out;
}
.swing-horizontal .vent-lamella:nth-child(even) {
  animation: lamellaSwingH2 3s infinite ease-in-out;
}

.swing-vertical .vent-lamella {
  animation: lamellaSwingV 2.5s infinite ease-in-out;
}

.swing-both .vent-lamella:nth-child(odd) {
  animation: lamellaSwingBoth1 4s infinite ease-in-out;
}
.swing-both .vent-lamella:nth-child(even) {
  animation: lamellaSwingBoth2 4s infinite ease-in-out;
}

@keyframes lamellaSwingH1 {
  0%, 100% { transform: rotateY(-15deg); }
  50% { transform: rotateY(15deg); }
}

@keyframes lamellaSwingH2 {
  0%, 100% { transform: rotateY(15deg); }
  50% { transform: rotateY(-15deg); }
}

@keyframes lamellaSwingV {
  0%, 100% { transform: rotateX(-20deg); }
  50% { transform: rotateX(20deg); }
}

@keyframes lamellaSwingBoth1 {
  0%, 100% { transform: rotateY(-15deg) rotateX(-10deg); }
  25% { transform: rotateY(0deg) rotateX(15deg); }
  50% { transform: rotateY(15deg) rotateX(-10deg); }
  75% { transform: rotateY(0deg) rotateX(15deg); }
}

@keyframes lamellaSwingBoth2 {
  0%, 100% { transform: rotateY(15deg) rotateX(10deg); }
  25% { transform: rotateY(0deg) rotateX(-15deg); }
  50% { transform: rotateY(-15deg) rotateX(10deg); }
  75% { transform: rotateY(0deg) rotateX(-15deg); }
}
</style>