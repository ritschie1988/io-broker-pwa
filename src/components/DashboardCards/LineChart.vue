<template>
  <Line :data="lineData" :options="options" />
</template>

<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'

import {
  Chart,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'

Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Title, Tooltip, Legend, Filler)

const props = defineProps({ data: Array })

const lineData = computed(() => ({
  labels: props.data.map(d => d.hour + ':00'),
  datasets: [
    {
      label: '°C',
      borderColor: '#7c3aed',
      backgroundColor: '#ede9fe',
      data: props.data.map(d => d.value),
      fill: true,
      tension: 0.3,
    },
  ],
}))

const options = {
  responsive: true,
  plugins: { legend: { display: false } },
}
</script>