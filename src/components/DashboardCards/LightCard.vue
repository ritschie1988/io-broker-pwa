<template>
  <v-card>
    <v-card-title>Licht {{ roomName }}</v-card-title>
    <v-card-text>
      <template v-for="light in lights" :key="light.id">
        <v-switch
          v-model="lightStates[light.id]"
          :label="light.label"
          class="mb-4"
          @change="setState(light)"
        />
      </template>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'

const props = defineProps({ room: String })

// Definition aller Lichter pro Raum
const roomLights = {
  wohnzimmer: [
    { id: 'sonoff.0.WohnzimmerLicht.POWER', label: 'Hauptlicht' },
    { id: '0_userdata.0.WohnzimmerAmbientePower', label: 'Fernsehlicht' }
  ],
  schlafzimmer: [
    { id: 'sonoff.0.SZLightMain.POWER', label: 'Hauptlicht' },
    { id: 'sonoff.0.SchlafzimmerNachtlicht.ENERGY_Power', label: 'Nachtlicht' }
  ],
  kueche: [
    { id: 'sonoff.0.KuecheLicht.POWER', label: 'Hauptlicht' },
    { id: 'alias.0.shelly.0.shellyplus1pm80646FE2A22C.Relay0.Switch', label: 'Kochlicht 1' },
    { id: 'alias.0.shelly.0.shellyplus178ee4ccd3f64.Relay0.Switch', label: 'Kochlicht 2' }
  ],
  vorraum: [
    { id: 'sonoff.0.VorzimmerLicht.POWER', label: 'Licht' }
  ]
}

// Raumname für die Card-Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'wohnzimmer': return 'Wohnzimmer'
    case 'schlafzimmer': return 'Schlafzimmer'
    case 'kueche': return 'Küche/Esszimmer'
    case 'vorraum': return 'Vorraum'
    default: return ''
  }
})

// Aktuelle Lichter für den Raum
const lights = computed(() => roomLights[props.room] || [])

// Status der Lichter
const lightStates = ref({})

// Werte laden
async function fetchStates() {
  const states = {}
  for (const light of lights.value) {
    const res = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${light.id}`)
    const data = await res.json()
    states[light.id] = !!data.val
  }
  lightStates.value = states
}

// Wert setzen
async function setState(light) {
  const value = lightStates.value[light.id] ? 'true' : 'false'
  await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${light.id}&query=value=${value}`)
}

// Bei Raumwechsel oder Mount neu laden
watch(() => props.room, fetchStates, { immediate: true })
</script>