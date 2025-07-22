<template>
  <v-card>
    <v-card-title>Heizung {{ roomName }}</v-card-title>
    <v-card-text>
      <!-- Außentemperatur (immer sichtbar) -->
      <div class="mb-4">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-icon color="blue" class="mr-3">mdi-thermometer</v-icon>
            <div>
              <div class="text-subtitle2">Außentemperatur</div>
              <div class="text-h6">{{ outsideTemp }}°C</div>
            </div>
          </div>
        </v-card>
      </div>

      <!-- Raumtemperatur -->
      <div class="mb-4" v-if="roomTempSensor">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-icon color="orange" class="mr-3">mdi-home-thermometer</v-icon>
            <div>
              <div class="text-subtitle2">Raumtemperatur</div>
              <div class="text-h6">{{ roomTemp }}°C</div>
            </div>
          </div>
        </v-card>
      </div>

      <!-- Thermostat Steuerung -->
      <div class="mb-4" v-if="thermostat">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <v-icon :color="thermostatState ? 'red' : 'grey'" class="mr-3">
                {{ thermostatState ? 'mdi-radiator' : 'mdi-radiator-off' }}
              </v-icon>
              <div>
                <div class="text-subtitle2">Thermostat</div>
                <div class="text-body-2">{{ thermostatState ? 'Aktiv' : 'Aus' }}</div>
              </div>
            </div>
            <v-switch
              v-model="thermostatState"
              color="red"
              @change="setThermostat"
              hide-details
            />
          </div>
        </v-card>
      </div>

      <!-- Solltemperatur -->
      <div class="mb-4" v-if="targetTempSensor">
        <v-card variant="outlined" class="pa-3">
          <div class="text-subtitle2 mb-3">Solltemperatur</div>
          <div class="d-flex align-center gap-2">
            <v-text-field
              v-model.number="targetTemp"
              type="number"
              :min="10"
              :max="30"
              :step="0.5"
              suffix="°C"
              variant="outlined"
              density="compact"
              hide-details
              style="max-width: 120px;"
            />
            <v-btn
              color="primary"
              @click="setTargetTemp"
              :disabled="!isValidTemp(targetTemp)"
            >
              Setzen
            </v-btn>
          </div>
          <div class="text-caption mt-2 text-grey">
            Aktuell: {{ currentTargetTemp }}°C
          </div>
        </v-card>
      </div>

      <!-- Temperatur-Differenz Anzeige -->
      <div v-if="roomTempSensor && targetTempSensor" class="mb-4">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-icon :color="getTempDiffColor()" class="mr-3">
              {{ getTempDiffIcon() }}
            </v-icon>
            <div>
              <div class="text-subtitle2">Temperatur-Differenz</div>
              <div class="text-body-2" :style="{ color: getTempDiffColor() }">
                {{ getTempDiffText() }}
              </div>
            </div>
          </div>
        </v-card>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, computed, onUnmounted } from 'vue'

const props = defineProps({ room: String })

// Definition der Heizungsdaten pro Raum basierend auf devices.json
const roomHeatingData = {
  wohnzimmer: {
    roomTempId: 'tuya.0.bf2557b234ab753959fp8n.1',
    targetTempId: '0_userdata.0.Soll-Temperatur_Wohnzimmer',
    thermostatId: 'mqtt.0.HeizungThermostatWZ1.Relay.State'
  },
  schlafzimmer: {
    roomTempId: 'tuya.0.bf4ee5091e3b1689eer3nq.1',
    targetTempId: '0_userdata.0.Soll-Temperatur_Schlafzimmer',
    thermostatId: 'mqtt.0.HeizungThermostatSZ.Relay.State'
  },
  esszimmer: {
    roomTempId: 'mqtt.0.HeizungThermostatEZ.TempEZ',
    targetTempId: '0_userdata.0.Soll-Temperatur_Esszimmer',
    thermostatId: 'mqtt.0.HeizungThermostatEssz.Relay.State'
  }
}

// Außentemperatur (aus Garten)
const outsideTempId = 'tuya.0.bf283d959302c27ad7jkvl.1'

// Raumname für die Card-Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'wohnzimmer': return 'Wohnzimmer'
    case 'schlafzimmer': return 'Schlafzimmer'
    case 'esszimmer': return 'Esszimmer'
    default: return ''
  }
})

// Aktuelle Heizungsdaten für den Raum
const heatingData = computed(() => roomHeatingData[props.room] || {})
const roomTempSensor = computed(() => heatingData.value.roomTempId)
const targetTempSensor = computed(() => heatingData.value.targetTempId)
const thermostat = computed(() => heatingData.value.thermostatId)

// Temperatur-Werte
const outsideTemp = ref(0)
const roomTemp = ref(0)
const currentTargetTemp = ref(20)
const targetTemp = ref(20)
const thermostatState = ref(false)

// Timer für Live-Updates
let updateInterval = null

// Temperaturen laden
async function fetchTemperatures() {
  try {
    // Außentemperatur laden
    const outsideRes = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${outsideTempId}`)
    const outsideData = await outsideRes.json()
    outsideTemp.value = Math.round((outsideData.val || 0) * 10) / 10

    // Raumtemperatur laden
    if (roomTempSensor.value) {
      const roomRes = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${roomTempSensor.value}`)
      const roomData = await roomRes.json()
      roomTemp.value = Math.round((roomData.val || 0) * 10) / 10
    }

    // Solltemperatur laden
    if (targetTempSensor.value) {
      const targetRes = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${targetTempSensor.value}`)
      const targetData = await targetRes.json()
      currentTargetTemp.value = Math.round((targetData.val || 20) * 10) / 10
      if (!targetTemp.value || targetTemp.value === 20) {
        targetTemp.value = currentTargetTemp.value
      }
    }

    // Thermostat Status laden
    if (thermostat.value) {
      const thermostatRes = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${thermostat.value}`)
      const thermostatData = await thermostatRes.json()
      thermostatState.value = !!thermostatData.val
    }
  } catch (error) {
    console.error('Fehler beim Laden der Temperaturen:', error)
  }
}

// Thermostat ein/ausschalten
async function setThermostat() {
  if (!thermostat.value) return
  
  try {
    const value = thermostatState.value ? '1' : '0'
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${thermostat.value}&query=value=${value}`)
  } catch (error) {
    console.error('Fehler beim Setzen des Thermostats:', error)
    // Revert bei Fehler
    thermostatState.value = !thermostatState.value
  }
}

// Solltemperatur setzen
async function setTargetTemp() {
  if (!targetTempSensor.value || !isValidTemp(targetTemp.value)) return
  
  try {
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${targetTempSensor.value}&query=value=${targetTemp.value}`)
    currentTargetTemp.value = targetTemp.value
  } catch (error) {
    console.error('Fehler beim Setzen der Solltemperatur:', error)
  }
}

// Temperatur validieren
function isValidTemp(temp) {
  return temp >= 10 && temp <= 30
}

// Temperatur-Differenz berechnen und Farbe bestimmen
function getTempDiff() {
  return roomTemp.value - currentTargetTemp.value
}

function getTempDiffColor() {
  const diff = getTempDiff()
  if (Math.abs(diff) <= 0.5) return 'green'
  if (diff > 0) return 'orange'
  return 'blue'
}

function getTempDiffIcon() {
  const diff = getTempDiff()
  if (Math.abs(diff) <= 0.5) return 'mdi-check-circle'
  if (diff > 0) return 'mdi-thermometer-plus'
  return 'mdi-thermometer-minus'
}

function getTempDiffText() {
  const diff = getTempDiff()
  if (Math.abs(diff) <= 0.5) {
    return 'Zieltemperatur erreicht'
  } else if (diff > 0) {
    return `${Math.abs(diff).toFixed(1)}°C zu warm`
  } else {
    return `${Math.abs(diff).toFixed(1)}°C zu kalt`
  }
}

// Live-Updates starten
function startLiveUpdates() {
  updateInterval = setInterval(fetchTemperatures, 10000) // Alle 10 Sekunden aktualisieren
}

// Live-Updates stoppen
function stopLiveUpdates() {
  if (updateInterval) {
    clearInterval(updateInterval)
    updateInterval = null
  }
}

// Bei Raumwechsel neu laden
watch(() => props.room, () => {
  fetchTemperatures()
  // Reset der Werte
  targetTemp.value = 20
}, { immediate: true })

onMounted(() => {
  fetchTemperatures()
  startLiveUpdates()
})

onUnmounted(() => {
  stopLiveUpdates()
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>