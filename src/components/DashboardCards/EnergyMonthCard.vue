<template>
  <v-card>
  <v-card-title>
    Energieverbrauch – Monat ({{ props.room }})
      <v-spacer />
      <v-select
        :items="months"
        v-model="selectedMonth"
        label="Monat"
        dense
        hide-details
        style="max-width: 120px"
      />
    </v-card-title>
    <v-card-text>
      <BarChart :data="chartData" />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import BarChart from '../charts/BarChart.vue'
import dayjs from 'dayjs'

const props = defineProps({ room: String })

// Datenbank-API pro Raum
const apiByRoom = {
  wohnzimmer: '/iobroker/api/energy-history-wz.php',
  bad: '/iobroker/api/energy-history-bad.php',
  ww: '/iobroker/api/energy-history-ww.php'
  // weitere Räume später ergänzen
}

const months = ref([])
const selectedMonth = ref('')
const chartData = ref([])

const apiUrl = computed(() => apiByRoom[props.room] || apiByRoom['wohnzimmer'])

async function fetchMonths() {
  // Hole alle Monate aus der Datenbank (über API)
  const url = `${apiUrl.value}?allMonths=1`
  const res = await fetch(url)
  const data = await res.json()
  // Extrahiere eindeutige Monate im Format YYYY-MM
  const monthSet = new Set(data.map(entry => entry.date.slice(0, 7)))
  months.value = Array.from(monthSet).sort().reverse()
  if (months.value.length > 0) {
    selectedMonth.value = months.value[0]
  }
}

async function fetchData() {
  if (!selectedMonth.value) return
  const url = `${apiUrl.value}?month=${selectedMonth.value}`
  const res = await fetch(url)
  const data = await res.json()
  chartData.value = data.map(entry => ({
    day: Number(entry.date.split('-')[2]),
    value: entry.value
  }))
}

watch([() => props.room], fetchMonths, { immediate: true })
watch([selectedMonth], fetchData, { immediate: true })
</script>