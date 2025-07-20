<template>
  <v-card>
  <v-card-title>Energieverbrauch – Heute ({{ props.room }})</v-card-title>
    <v-card-text>
      <LineChart :data="chartData" />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import LineChart from './LineChart.vue'
import dayjs from 'dayjs'

const props = defineProps({ room: String })

const apiByRoom = {
  wohnzimmer: '/iobroker/api/energy-today-wz.php',
  bad: '/iobroker/api/energy-today-bad.php',
  ww: '/iobroker/api/energy-today-ww.php'
  // weitere Räume später ergänzen
}

const chartData = ref([])

const apiUrl = computed(() => apiByRoom[props.room] || apiByRoom['wohnzimmer'])

async function fetchData() {
  const today = dayjs().format('YYYY-MM-DD')
  const url = `${apiUrl.value}?date=${today}`
  const res = await fetch(url)
  const data = await res.json()
  chartData.value = data.map(entry => ({
    hour: entry.hour,
    value: entry.value
  }))
}

watch(() => props.room, fetchData, { immediate: true })
</script>