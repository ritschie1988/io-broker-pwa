<template>
  <div class="statistics-view">
    <!-- Header & Navigation -->
    <header class="stats-header">
      <button class="back-btn" @click="$router.back()">
        <span class="material-icons">arrow_back</span>
      </button>
      <h1>Statistik</h1>
    </header>

    <!-- Tab Switch -->
    <div class="stats-tabs">
      <button v-for="tab in tabs" :key="tab" :class="['tab-btn', { active: tab === selectedTab }]" @click="selectedTab = tab">
        {{ tab }}
      </button>
    </div>

    <!-- Datumsauswahl -->
    <div class="date-picker">
      <button @click="prevDate"><span class="material-icons">chevron_left</span></button>
      <span class="date-label">{{ formattedDate }}</span>
      <button @click="nextDate"><span class="material-icons">chevron_right</span></button>
    </div>

    <!-- Energie-Ertrag (Donut) -->
    <div class="card">
      <h2>Energie-Ertrag</h2>
      <DonutChart :value="pvYield" :segments="pvYieldSegments" :unit="'kWh'" />
      <div class="legend">
        <div v-for="seg in pvYieldSegments" :key="seg.label" class="legend-item">
          <span class="legend-dot" :style="{ background: seg.color }"></span>
          <span>{{ seg.label }}: {{ seg.value }} kWh ({{ seg.percent }}%)</span>
        </div>
      </div>
    </div>

    <!-- Verbrauch (Donut) -->
    <div class="card">
      <h2>Verbrauch</h2>
      <DonutChart :value="consumption" :segments="consumptionSegments" :unit="'kWh'" />
      <div class="legend">
        <div v-for="seg in consumptionSegments" :key="seg.label" class="legend-item">
          <span class="legend-dot" :style="{ background: seg.color }"></span>
          <span>{{ seg.label }}: {{ seg.value }} kWh ({{ seg.percent }}%)</span>
        </div>
      </div>
    </div>

    <!-- Zeitlicher Verlauf (Line Chart) -->
    <div class="card">
      <h2>Verlauf</h2>
      <LineChart :labels="timeLabels" :datasets="lineDatasets" />
      <div class="legend">
        <div v-for="ds in lineDatasets" :key="ds.label" class="legend-item">
          <span class="legend-dot" :style="{ background: ds.borderColor }"></span>
          <span>{{ ds.label }}</span>
        </div>
      </div>
    </div>

    <!-- Footer-Aktion -->
    <footer class="stats-footer">
      <div>Einnahmen: <span class="earnings">-- €</span></div>
      <button class="price-btn">Strompreiseinstellungen</button>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import DonutChart from '../components/charts/DonutChart.vue'
import LineChart from '../components/charts/LineChart.vue'

const tabs = ['Tag', 'Monat', 'Jahr', 'Lebensdauer']
const selectedTab = ref('Tag')
const today = new Date()
const selectedDate = ref(today)

const formattedDate = computed(() =>
  selectedDate.value.toLocaleDateString('de-DE')
)

function prevDate() {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() - 1)
  selectedDate.value = d
}
function nextDate() {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() + 1)
  selectedDate.value = d
}

// Dummy-Daten für Demo (später durch echte API-Daten ersetzen)
const pvYield = 46.4
const pvYieldSegments = [
  { label: 'Netzeinspeisung', value: 33.62, percent: 72.46, color: 'linear-gradient(90deg,#b2f7cc,#4caf50)' },
  { label: 'Eigenverbrauch', value: 12.78, percent: 27.54, color: 'linear-gradient(90deg,#4caf50,#388e3c)' }
]
const consumption = 15.66
const consumptionSegments = [
  { label: 'Netzbezug', value: 2.88, percent: 18.39, color: 'linear-gradient(90deg,#ffb74d,#ff9800)' },
  { label: 'Von PV', value: 12.78, percent: 81.61, color: 'linear-gradient(90deg,#ffe082,#ffd54f)' }
]
const timeLabels = [
  '00:00','04:00','08:00','12:00','16:00','20:00'
]
const lineDatasets = [
  {
    label: 'PV-Ausgabe',
    data: [0, 2, 8, 12, 6, 0],
    borderColor: '#4caf50',
    backgroundColor: 'rgba(76,175,80,0.2)',
    fill: true
  },
  {
    label: 'Leistungsaufnahme',
    data: [1, 1.5, 3, 5, 2, 1],
    borderColor: '#ff9800',
    backgroundColor: 'rgba(255,152,0,0.1)',
    fill: false
  }
]
</script>

<style scoped>
.statistics-view {
  background: #fff;
  min-height: 100vh;
  padding-bottom: 80px;
}
.stats-header {
  display: flex;
  align-items: center;
  padding: 16px 0 8px 0;
  border-bottom: 1px solid #eee;
}
.stats-header h1 {
  flex: 1;
  text-align: center;
  font-size: 1.5rem;
  font-weight: bold;
  margin: 0;
}
.back-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}
.stats-tabs {
  display: flex;
  justify-content: center;
  margin: 16px 0;
}
.tab-btn {
  border: none;
  background: #f5f5f5;
  color: #333;
  padding: 8px 20px;
  margin: 0 4px;
  border-radius: 20px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.tab-btn.active {
  background: #333;
  color: #fff;
}
.date-picker {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}
.date-label {
  font-size: 1.1rem;
  margin: 0 12px;
}
.card {
  background: #fafafa;
  border-radius: 18px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  margin: 16px 12px;
  padding: 18px 12px 12px 12px;
}
.card h2 {
  font-size: 1.1rem;
  font-weight: bold;
  margin-bottom: 8px;
}
.legend {
  display: flex;
  flex-direction: column;
  margin-top: 8px;
}
.legend-item {
  display: flex;
  align-items: center;
  font-size: 0.95rem;
  margin-bottom: 2px;
}
.legend-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  margin-right: 8px;
  display: inline-block;
}
.stats-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background: #fff;
  border-top: 1px solid #eee;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  z-index: 10;
}
.earnings {
  font-weight: bold;
  color: #1976d2;
  font-size: 1.1rem;
}
.price-btn {
  border: 1.5px solid #1976d2;
  background: none;
  color: #1976d2;
  border-radius: 20px;
  padding: 6px 18px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}
.price-btn:hover {
  background: #1976d2;
  color: #fff;
}
</style>
