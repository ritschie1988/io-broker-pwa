<template>
  <Bar :data="barData" :options="options" />
</template>

<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'

// Skalen und Komponenten registrieren
Chart.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps({ data: Array })

const barData = computed(() => ({
  labels: props.data.map(d => d.day),
  datasets: [
    {
      label: 'kWh',
      backgroundColor: '#7c3aed',
      data: props.data.map(d => d.value),
    },
  ],
}))

const options = {
  responsive: true,
  plugins: { legend: { display: false } },
}
</script>