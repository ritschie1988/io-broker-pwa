<template>
  <div class="line-chart">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup>
import { Line } from 'vue-chartjs'
import { computed } from 'vue'
import { Chart, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler } from 'chart.js'
Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler)

const props = defineProps({
  labels: Array, // Zeitachsen-Labels
  datasets: Array // [{label, data, borderColor, backgroundColor, fill}]
})

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets
}))

const chartOptions = {
  responsive: true,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true }
  },
  scales: {
    x: {
      grid: { color: '#eee' },
      ticks: { color: '#888' }
    },
    y: {
      grid: { color: '#eee' },
      ticks: { color: '#888' },
      beginAtZero: true
    }
  }
}
</script>

<style scoped>
.line-chart {
  width: 100%;
  min-height: 180px;
}
</style>
