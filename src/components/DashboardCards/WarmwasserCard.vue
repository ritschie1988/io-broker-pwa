<template>
  <v-card>
    <v-card-title>Warmwasser</v-card-title>
    <v-card-text>
      <v-row>
        <!-- Liniendiagramm entfernt -->
        <v-col cols="6" md="6">
          <v-switch
            v-model="power"
            label="Warmwasser EIN/AUS"
            @change="setState('power')"
            class="mb-4"
          />
          <v-text-field
            :model-value="temperatureDisplay"
            label="Warmwassertemperatur oben"
            readonly
            class="mb-4"
          />
          <v-switch
            v-model="override"
            label="Override"
            :true-value="true"
            :false-value="false"
            @change="setState('override')"
          />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'

const powerId = 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Switch'
const tempId = 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben'
const overrideId = '0_userdata.0.OverrideWarmwasser'
const tempLogApi = '/iobroker/api/energy-warmwasser.php?days=7'

const power = ref(false)
const temperature = ref(null)
const override = ref(false)

const temperatureDisplay = computed(() =>
  temperature.value !== null ? `${temperature.value} °C` : '...'
)

async function fetchStates() {
  // Power
  const resPower = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${powerId}`)
  const dataPower = await resPower.json()
  power.value = !!dataPower.val

  // Temperatur
  const resTemp = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${tempId}`)
  const dataTemp = await resTemp.json()
  temperature.value = dataTemp.val

  // Override
  const resOverride = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${overrideId}`)
  const dataOverride = await resOverride.json()
  override.value = !!dataOverride.val

}

async function setState(which) {
  let id, value
  if (which === 'power') {
    id = powerId
    value = power.value ? 'true' : 'false'
  } else if (which === 'override') {
    id = overrideId
    value = override.value ? 'true' : 'false'
  }
  await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${id}&query=value=${value}`)
}

onMounted(fetchStates)
</script>