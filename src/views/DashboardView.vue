<template>
  <NotificationBar
    :messages="notificationMessages"
    :visible="notificationMessages.length > 0"
  />
  <div style="max-width: 600px; margin: 2rem auto;">
    <VoiceAssistant />
  </div>
  <v-toolbar class="room-toolbar" flat>
    <v-slide-group
      v-model="selectedRoom"
      class="room-menu"
      show-arrows
      center-active
      prev-icon="mdi-chevron-left"
      next-icon="mdi-chevron-right"
      mobile-breakpoint="0"
    >
      <v-slide-group-item
        v-for="room in rooms"
        :key="room.id"
        :value="room.id"
      >
        <v-btn
          :color="selectedRoom === room.id ? 'primary' : 'default'"
          class="mx-1"
          @click="selectedRoom = room.id"
          rounded
          text
        >
          {{ room.name }}
        </v-btn>
      </v-slide-group-item>
    </v-slide-group>
  </v-toolbar>
  <v-row class="dashboard-cards">
    <template v-for="cardName in roomConfigs[selectedRoom]" :key="cardName">
      <v-col cols="12" md="6" lg="4">
        <component :is="cardComponents[cardName]" :room="selectedRoom" />
      </v-col>
    </template>
  </v-row>
</template>

<script setup>
import { ref, computed, onMounted, defineProps } from 'vue'
import NotificationBar from '../components/NotificationBar.vue'
import VoiceAssistant from '../components/VoiceAssistant.vue'
import EnergyMonthCard from '../components/DashboardCards/EnergyMonthCard.vue'
import EnergyTodayCard from '../components/DashboardCards/EnergyTodayCard.vue'
import ClimateCard from '../components/DashboardCards/ClimateCard.vue'
import LightCard from '../components/DashboardCards/LightCard.vue'
import HeizungCard from '../components/DashboardCards/HeizungCard.vue'
import WarmwasserCard from '../components/DashboardCards/WarmwasserCard.vue'
import WarmwasserTempCard from '../components/DashboardCards/WarmwasserTempCard.vue'
import WWSolarCard from '../components/DashboardCards/WWSolarCard.vue'
import PVRoom from '../components/DashboardCards/PVRoom.vue'

const props = defineProps({
  notifications: {
    type: Boolean,
    default: true
  }
})

const hasWWSolarConnectionError = ref(false)
const bartelsErrorText = ref('')
const hasIoBrokerError = ref(false)
const ioBrokerErrorText = ref('')

async function pollBartelsStatus() {
  try {
    const res = await fetch('/iobroker/api/Von_Bartels_Daten/status.json', { cache: 'no-store' })
    if (!res.ok) {
      hasWWSolarConnectionError.value = true;
      bartelsErrorText.value = `Verbindung zur WW-Solaranlage (vonBartels) kann nicht geprüft werden! (HTTP ${res.status})`;
      return;
    }
    const status = await res.json();
    if (status && status.ok === true && status.server_alive === true) {
      hasWWSolarConnectionError.value = false;
      bartelsErrorText.value = '';
    } else if (status && status.ok === true && status.server_alive === false) {
      hasWWSolarConnectionError.value = true;
      bartelsErrorText.value = 'Letzter Serverkontakt älter als 30 Minuten!';
    } else {
      hasWWSolarConnectionError.value = true;
      bartelsErrorText.value = status.error || 'Unbekannter Fehler bei der Verbindung zur WW-Solaranlage (vonBartels)!';
    }
  } catch (e) {
    hasWWSolarConnectionError.value = true;
    bartelsErrorText.value = 'Verbindung zur WW-Solaranlage (vonBartels) kann nicht geprüft werden!';
  }
}

async function pollIoBrokerStatus() {
  try {
    const res = await fetch('/iobroker/api/iobroker-proxy.php?endpoint=get/system.host.iO-Broker01.alive', { cache: 'no-store' })
    if (!res.ok) {
      hasIoBrokerError.value = true;
      ioBrokerErrorText.value = `ioBroker nicht erreichbar! (HTTP ${res.status})`;
      return;
    }
    const data = await res.json();
    if (data && data.val === true) {
      hasIoBrokerError.value = false;
      ioBrokerErrorText.value = '';
    } else {
      hasIoBrokerError.value = true;
      ioBrokerErrorText.value = 'ioBroker nicht erreichbar!';
    }
  } catch (e) {
    hasIoBrokerError.value = true;
    ioBrokerErrorText.value = 'ioBroker nicht erreichbar!';
  }
}

onMounted(() => {
  pollBartelsStatus()
  pollIoBrokerStatus()
  setInterval(pollBartelsStatus, 10000)
  setInterval(pollIoBrokerStatus, 10000)
})

const notificationMessages = computed(() => {
  if (!props.notifications) return []
  const msgs = []
  if (hasWWSolarConnectionError.value) {
    msgs.push({ id: 'wwsolar', text: bartelsErrorText.value || 'Keine Verbindung zur WW-Solaranlage (vonBartels)!' })
  }
  if (hasIoBrokerError.value) {
    msgs.push({ id: 'iobroker', text: ioBrokerErrorText.value || 'ioBroker nicht erreichbar!' })
  }
  return msgs
})

const rooms = [
  { id: 'wohnzimmer', name: 'Wohnzimmer' },
  { id: 'schlafzimmer', name: 'Schlafzimmer' },
  { id: 'kueche', name: 'Küche/Esszimmer' },
  { id: 'vorraum', name: 'Vorraum' },
  { id: 'bad', name: 'Bad' },
  { id: 'pv', name: 'Photovoltaik' },
  { id: 'ww', name: 'Warmwasser' },
]
const selectedRoom = ref(rooms[0].id)

const roomConfigs = {
  wohnzimmer: ['ClimateCard', 'LightCard', 'HeizungCard', 'EnergyMonthCard', 'EnergyTodayCard'],
  schlafzimmer: ['ClimateCard', 'LightCard', 'HeizungCard'],
  kueche: ['ClimateCard', 'LightCard', 'HeizungCard'],
  bad: ['HeizungCard', 'EnergyMonthCard', 'EnergyTodayCard'],
  vorraum: ['LightCard'],
  pv: ['PVRoom'],
  ww: ['WarmwasserCard', 'WWSolarCard', 'WarmwasserTempCard', 'EnergyMonthCard', 'EnergyTodayCard'],
}

const cardComponents = {
  EnergyMonthCard,
  EnergyTodayCard,
  ClimateCard,
  LightCard,
  HeizungCard,
  WarmwasserCard,
  WarmwasserTempCard,
  WWSolarCard,
  PVRoom,
}
</script>

