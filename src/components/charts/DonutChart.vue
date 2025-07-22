<template>
  <div class="donut-chart">
    <Doughnut :data="chartData" :options="chartOptions" />
    <div class="donut-center">
      <div class="donut-value">{{ value }}</div>
      <div class="donut-unit">{{ unit }}</div>
    </div>
  </div>
</template>

<script setup>
import { Doughnut } from 'vue-chartjs'
import { computed } from 'vue'
import { Chart, ArcElement, Tooltip, Legend } from 'chart.js'
Chart.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  value: Number,
  segments: Array, // [{label, value, percent, color}]
  unit: String
})

const chartData = computed(() => ({
  labels: props.segments.map(s => s.label),
  datasets: [
    {
      data: props.segments.map(s => s.value),
      backgroundColor: props.segments.map(s => s.color),
      borderWidth: 0
    }
  ]
}))

const chartOptions = {
  cutout: '70%',
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: ctx => `${ctx.label}: ${ctx.parsed} kWh (${props.segments[ctx.dataIndex].percent}%)`
      }
    }
  }
}
</script>

<style scoped>
.donut-chart {
  position: relative;
  width: 180px;
  height: 180px;
  margin: 0 auto;
}
.donut-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%,-50%);
  text-align: center;
}
.donut-value {
  font-size: 2.1rem;
  font-weight: bold;
}
.donut-unit {
  font-size: 1rem;
}
</style>
