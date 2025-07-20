<template>
  <v-card>
    <v-card-title>
      Photovoltaik
      <v-spacer />
      <v-btn-toggle v-model="selectedPeriod" dense mandatory>
        <v-btn value="day">Tag</v-btn>
        <v-btn value="month">Monat</v-btn>
        <v-btn value="year">Jahr</v-btn>
        <v-btn value="total">Gesamt</v-btn>
      </v-btn-toggle>
    </v-card-title>
    <v-card-text>
      <div class="period-select-row">
        <template v-if="selectedPeriod === 'day'">
          <v-btn icon @click="prevDay"><v-icon>mdi-chevron-left</v-icon></v-btn>
          <span style="min-width:120px; text-align:center;">{{ selectedDayLabel }}</span>
          <v-btn icon @click="nextDay"><v-icon>mdi-chevron-right</v-icon></v-btn>
        </template>
        <template v-else-if="selectedPeriod === 'month'">
          <v-btn icon @click="prevMonth"><v-icon>mdi-chevron-left</v-icon></v-btn>
          <span style="min-width:120px; text-align:center;">{{ selectedMonthLabel }}</span>
          <v-btn icon @click="nextMonth"><v-icon>mdi-chevron-right</v-icon></v-btn>
        </template>
        <template v-else-if="selectedPeriod === 'year'">
          <v-btn icon @click="prevYear"><v-icon>mdi-chevron-left</v-icon></v-btn>
          <span style="min-width:80px; text-align:center;">{{ selectedYear }}</span>
          <v-btn icon @click="nextYear"><v-icon>mdi-chevron-right</v-icon></v-btn>
        </template>
        <template v-else-if="selectedPeriod === 'total'">
          <span>Gesamtdaten</span>
        </template>
      </div>



      <div class="donut-row">
        <div class="donut-label">Eigenverbrauch<br>
          <span style="font-size:1.2em;font-weight:bold;">{{ eigenverbrauchDisplay }}</span>
        </div>
        <DonutChart
          :value="getValueAndUnit(pvErtragDisplay).value"
          :unit="getValueAndUnit(pvErtragDisplay).unit"
          :segments="pvDonutSegments"
          style="margin: 0 16px;"
        />
        <div class="donut-label">Einspeisung<br>
          <span style="font-size:1.2em;font-weight:bold;">{{ einspeisungDisplay }}</span>
        </div>
      </div>
      <div class="donut-center-label">
        PV-Ertrag
      </div>

      <div class="donut-row">
        <div class="donut-label">
          Eigenverbrauch<br>
          <span style="font-size:1.2em;font-weight:bold;">{{ eigenverbrauchDisplay }}</span>
        </div>
        <DonutChart
          :value="getValueAndUnit(gesamtverbrauchDisplay).value"
          :unit="getValueAndUnit(gesamtverbrauchDisplay).unit"
          :segments="verbrauchDonutSegments"
          style="margin: 0 16px;"
        />

        <div class="donut-label">
          Netzbezug<br>
          <span style="font-size:1.2em;font-weight:bold;">{{ verbrauchDisplay }}</span>
        </div>
      </div>
      <div class="donut-center-label">
        Verbrauch:<br>
        <span style="font-size:1.2em;font-weight:bold;">{{ gesamtverbrauchDisplay }}</span>
      </div>

      <!-- Liniendiagramm und RAW-Daten entfernt -->
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import dayjs from 'dayjs'
import DonutChart from '../charts/DonutChart.vue'

// Hilfsfunktion: Trennt Wert und Einheit für die Donut-Anzeige
function getValueAndUnit(displayString) {
  if (typeof displayString !== 'string') return { value: '', unit: '' };
  const match = displayString.match(/^([\d.,-]+)\s*(\w+)$/);
  if (match) {
    return { value: match[1], unit: match[2] };
  }
  return { value: displayString, unit: '' };
}

// --- Werte für Donuts aus eigener Datenbank/REST-API ---
const pvErtrag = ref(NaN)
const einspeisung = ref(NaN)
const verbrauch = ref(NaN)
const eigenverbrauch = ref(NaN)
// Netzbezug ist jetzt direkt der Wert aus der API (verbrauch)
// netzbezug wird nicht mehr extra berechnet

const selectedPeriod = ref('day')
const today = dayjs().startOf('day')
const selectedDay = ref(today)
const selectedMonth = ref(today.month() + 1)
const selectedYear = ref(today.year())

// Fix: computed property für Verbrauchswert, damit .toFixed(2) im Template immer funktioniert
const verbrauchForPeriod = computed(() => isNaN(verbrauch.value) ? 0 : verbrauch.value)
const gesamtverbrauch = computed(() => {
  const eigen = isNaN(eigenverbrauch.value) ? 0 : eigenverbrauch.value
  const netz = isNaN(verbrauch.value) ? 0 : verbrauch.value
  return eigen + netz
})

const selectedDayLabel = computed(() => selectedDay.value.format('DD.MM.YYYY'))
const selectedMonthLabel = computed(() => dayjs().month(selectedMonth.value - 1).format('MMMM YYYY'))

function prevDay() { selectedDay.value = selectedDay.value.subtract(1, 'day') }
function nextDay() { selectedDay.value = selectedDay.value.add(1, 'day') }
function prevMonth() { if (selectedMonth.value === 1) { selectedMonth.value = 12; selectedYear.value-- } else { selectedMonth.value-- } }
function nextMonth() { if (selectedMonth.value === 12) { selectedMonth.value = 1; selectedYear.value++ } else { selectedMonth.value++ } }
function prevYear() { selectedYear.value-- }
function nextYear() { selectedYear.value++ }

async function fetchPeriodStats() {
  let url = '/iobroker/api/pv_values_api.php?type=' + selectedPeriod.value
  if (selectedPeriod.value === 'day') url += '&day=' + selectedDay.value.format('YYYY-MM-DD')
  if (selectedPeriod.value === 'month') url += '&month=' + String(selectedMonth.value).padStart(2,'0') + '&year=' + selectedYear.value
  if (selectedPeriod.value === 'year') url += '&year=' + selectedYear.value
  // total braucht keine weiteren Parameter
  const res = await fetch(url)
  const data = await res.json()
  console.log('API-Response:', data)
  if (data && !data.error) {
    pvErtrag.value = data.produktion
    einspeisung.value = data.einspeisung
    verbrauch.value = data.verbrauch
    eigenverbrauch.value = data.produktion - data.einspeisung
    console.log('Set values:', {
      pvErtrag: pvErtrag.value,
      einspeisung: einspeisung.value,
      verbrauch: verbrauch.value,
      eigenverbrauch: eigenverbrauch.value
    })
  } else {
    pvErtrag.value = einspeisung.value = verbrauch.value = eigenverbrauch.value = NaN
    console.log('Set all values to NaN')
  }
}

onMounted(fetchPeriodStats)
watch([selectedPeriod, selectedDay, selectedMonth, selectedYear], fetchPeriodStats)

// --- Startwerte entfallen, da alles direkt aus Sourceanalytics kommt ---



// --- Donut-Segmente aus neuen Werten berechnen ---
const pvDonutSegments = computed(() => {
  const total = isNaN(pvErtrag.value) ? 0 : pvErtrag.value
  const eigen = isNaN(eigenverbrauch.value) ? 0 : eigenverbrauch.value
  const eins = isNaN(einspeisung.value) ? 0 : einspeisung.value
  return [
    { label: 'Einspeisung', value: eins, percent: Math.round(eins / (total || 1) * 100), color: '#a5d6a7' },
    { label: 'Eigenverbrauch', value: eigen, percent: Math.round(eigen / (total || 1) * 100), color: '#43a047' }
  ]
})
const verbrauchDonutSegments = computed(() => {
  const eigen = isNaN(eigenverbrauch.value) ? 0 : eigenverbrauch.value
  const netz = isNaN(verbrauch.value) ? 0 : verbrauch.value
  const gesamt = eigen + netz
  return [
    { label: 'Netzbezug', value: netz, percent: Math.round(netz / (gesamt || 1) * 100), color: '#ffe082' },
    { label: 'Eigenverbrauch', value: eigen, percent: Math.round(eigen / (gesamt || 1) * 100), color: '#ff7043' }
  ]
})

const wochentage = [
  '01_Monday', '02_Tuesday', '03_Wednesday', '04_Thursday', '05_Friday', '06_Saturday', '07_Sunday'
]
const chartLabels = computed(() => [
  'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'
])

const verbrauchWoche = ref(Array(7).fill(null))
const einspeisungWoche = ref(Array(7).fill(null))


async function fetchWerteWoche() {
  // Verbrauch und Einspeisung der Woche aus Sourceanalytics
  // Nur 2023 vorhanden laut JSON
  const baseV = 'sourceanalytix.0.0_userdata__0__Huawei__Meter__Active_Power.2023.consumed.weeks.'
  const baseE = 'sourceanalytix.0.0_userdata__0__Huawei__Inverter__Daily_Energy_Yield.2023.earnings.weeks.'
  const urlsV = wochentage.map((_, idx) => `/iobroker/api/iobroker-proxy.php?endpoint=get/${baseV}${String(idx+1).padStart(2,'0')}`)
  const urlsE = wochentage.map((_, idx) => `/iobroker/api/iobroker-proxy.php?endpoint=get/${baseE}${String(idx+1).padStart(2,'0')}`)
  const [resultsV, resultsE] = await Promise.all([
    Promise.all(urlsV.map(url => fetch(url).then(r => r.json()).catch(() => null))),
    Promise.all(urlsE.map(url => fetch(url).then(r => r.json()).catch(() => null)))
  ])
  verbrauchWoche.value = resultsV.map(r => (r && typeof r.val === 'number') ? r.val : null)
  einspeisungWoche.value = resultsE.map(r => (r && typeof r.val === 'number') ? r.val : null)
}

onMounted(fetchWerteWoche)
watch([selectedPeriod], () => {
  if (selectedPeriod.value === 'day' || selectedPeriod.value === 'week') fetchWerteWoche()
})

const lineChartDatasets = computed(() => {
  if (selectedPeriod.value === 'week') {
    return [
      { label: 'Netzbezug (kWh)', data: verbrauchWoche.value, borderColor: '#ff9800', backgroundColor: 'rgba(255,152,0,0.1)', fill: true },
      { label: 'Einspeisung (kWh)', data: einspeisungWoche.value, borderColor: '#43a047', backgroundColor: 'rgba(67,160,71,0.1)', fill: true }
    ]
  }
  return []
})

// --- Computed Properties für Anzeige als Text ---
function formatEnergy(val) {
  if (typeof val !== 'number' || isNaN(val)) return '–';
  if (val >= 1_000_000) return (val / 1_000_000).toFixed(2) + ' GW';
  if (val >= 1_000) return (val / 1_000).toFixed(2) + ' MW';
  return val.toFixed(2) + ' kWh';
}

const pvErtragDisplay = computed(() => formatEnergy(pvErtrag.value))
const einspeisungDisplay = computed(() => formatEnergy(einspeisung.value))
const eigenverbrauchDisplay = computed(() => formatEnergy(eigenverbrauch.value))
const netzbezugDisplay = computed(() => formatEnergy(verbrauch.value))
const verbrauchDisplay = computed(() => formatEnergy(verbrauchForPeriod.value))
const gesamtverbrauchDisplay = computed(() => formatEnergy(gesamtverbrauch.value))
</script>

<style scoped>
.period-select-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  align-items: center;
}
.donut-row {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 1.5rem 0 0.5rem 0;
}
.donut-label {
  min-width: 80px;
  text-align: center;
  font-weight: 500;
  color: #555;
}
.donut-center-label {
  text-align: center;
  font-size: 1.1rem;
  color: #333;
  margin-bottom: 0.5rem;
}
.linechart-row {
  margin: 2rem 0 1rem 0;
}
.pv-raw-values {
  margin-top: 2rem;
  color: #888;
  font-size: 0.95em;
}
</style>
