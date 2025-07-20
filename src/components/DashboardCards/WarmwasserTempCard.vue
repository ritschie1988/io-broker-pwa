<template>
  <v-card>
    <v-card-title>Warmwasser-Statistik</v-card-title>
    <v-card-text>
      <v-row>
        <v-col>
          <LineChart :data="chartRawData"/>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import LineChart from './LineChart.vue'

const tempId = 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben'

const tempLogApi = '/iobroker/api/energy-warmwasser.php?days=2'

const temperature = ref(null)
const chartRawData = ref([])

const temperatureDisplay = computed(() =>
  temperature.value !== null ? `${temperature.value} °C` : '...'
)

async function fetchStates() {
  // Temperatur
  const resTemp = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${tempId}`)
  const dataTemp = await resTemp.json()
  temperature.value = dataTemp.val

  // Temperatur-Log für Chart
  try {
    const resLog = await fetch(tempLogApi)
    const dataLog = await resLog.json()
    chartRawData.value = Array.isArray(dataLog) ? dataLog.map(e => ({ hour: e.date, value: e.value })) : []
  } catch (e) {
    chartRawData.value = []
  }
}

onMounted(fetchStates)
</script>