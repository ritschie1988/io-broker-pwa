<template>
  <v-card>
    <v-card-title>Klimaanlage {{ roomName }}</v-card-title>
    <v-card-text>
      <div class="mt-2 mb-2" style="display: flex; align-items: center;">
        <v-slider
          v-model="targetTemperature"
          :min="17"
          :max="30"
          step="1"
          label="Solltemperatur"
          thumb-label="never"
          @update:modelValue="setState('targetTemperature', targetTemperature)"
          style="flex: 1;"
        />
        <v-chip color="primary" class="ml-4" label>
          {{ targetTemperature }} °C
        </v-chip>
      </div>
      <v-row>
        <v-col cols="12" md="6">
          <v-switch
            v-model="powerState"
            :label="powerState ? 'An' : 'Aus'"
            @change="setState('powerState', powerState)"
          />
          <v-switch
            v-model="turboMode"
            label="Turbo"
            @change="setState('turboMode', turboMode)"
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
            @update:modelValue="setState('fanSpeed', fanSpeed)"
          />
          <v-select
            v-model="operationalMode"
            :items="operationalModes"
            label="Modus"
            item-title="label"
            item-value="value"
            @update:modelValue="setState('operationalMode', operationalMode)"
          />
          <v-select
            v-model="swingMode"
            :items="swingModes"
            label="Swing"
            item-title="label"
            item-value="value"
            @update:modelValue="setState('swingMode', swingMode)"
          />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'

const props = defineProps({ room: String })

// IDs für jede Klimaanlage pro Raum
const climateIds = {
  wohnzimmer: 'midea.0.145135534992585.control.',
  schlafzimmer: 'midea.0.19791209303536.control.',
  esszimmer: 'midea.0.DEINE_ESSZIMMER_ID.control.' // <--- Hier später einfach die ID eintragen!
}

// Raumname für die Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'wohnzimmer': return 'Wohnzimmer'
    case 'schlafzimmer': return 'Schlafzimmer'
    case 'esszimmer': return 'Esszimmer'
    default: return ''
  }
})

const prefix = computed(() => climateIds[props.room] || climateIds['wohnzimmer'])

const powerState = ref(false)
const turboMode = ref(false)
const ecoMode = ref(false)
const targetTemperature = ref(22)
const fanSpeed = ref('')
const operationalMode = ref('')
const swingMode = ref('')

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

// Hilfsfunktion zum State-Setzen
async function setState(key, value) {
  let val = value
  if (typeof value === 'boolean') {
    val = value ? 'true' : 'false'
  } else if (['fanSpeed', 'operationalMode', 'swingMode'].includes(key)) {
    val = Number(value)
  }
  const url = `/iobroker/api/iobroker-proxy.php?endpoint=set/${prefix.value}${key}&query=value=${encodeURIComponent(val)}`
  await fetch(url)
}

// Werte laden
async function fetchStates() {
  const states = [
    { key: 'powerState', ref: powerState },
    { key: 'turboMode', ref: turboMode },
    { key: 'ecoMode', ref: ecoMode },
    { key: 'targetTemperature', ref: targetTemperature },
    { key: 'fanSpeed', ref: fanSpeed },
    { key: 'operationalMode', ref: operationalMode },
    { key: 'swingMode', ref: swingMode }
  ]
  for (const s of states) {
    const res = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${prefix.value}${s.key}`)
    const data = await res.json()
    if (['fanSpeed', 'operationalMode', 'swingMode'].includes(s.key)) {
      s.ref.value = data.val !== undefined && data.val !== null ? String(data.val) : ''
    } else {
      s.ref.value = data.val
    }
  }
}

// Bei Raumwechsel neu laden
watch(() => props.room, fetchStates, { immediate: true })
</script>