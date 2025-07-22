<template>
  <NotificationBar
    :messages="notificationMessages"
    :visible="notificationMessages.length > 0"
  />
  <!-- Floating Chat Button unten links -->
  <div class="chat-fab" @click="showChat = true">
    <svg xmlns="http://www.w3.org/2000/svg" height="32" viewBox="0 0 24 24" width="32"><path fill="#fff" d="M2 2v20l4-4h16V2H2zm16 12H6v-2h12v2zm0-4H6V8h12v2zm0-4H6V4h12v2z"/></svg>
  </div>
  <transition name="chat-bubble">
    <div v-if="showChat" class="chat-bubble-overlay">
      <div class="chat-bubble">
        <button class="chat-close" @click="showChat = false">×</button>
        <!-- Flache Struktur: VoiceAssistant direkt, keine weitere Box -->
        <VoiceAssistant />
      </div>
    </div>
  </transition>
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
    <template v-for="cardName in roomConfigs[selectedRoom] || []" :key="cardName">
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

const rooms = ref([])
const selectedRoom = ref('')
const roomConfigs = ref({})

async function loadRoomsConfig() {
  try {
    const res = await fetch('/iobroker/api/rooms.php')
    if (res.ok) {
      const data = await res.json()
      rooms.value = data
      if (data.length > 0) {
        selectedRoom.value = data[0].id
      }
      // roomConfigs als Objekt: { id: [cards] }
      const configs = {}
      data.forEach(r => { configs[r.id] = r.cards })
      roomConfigs.value = configs
    }
  } catch (e) {
    rooms.value = []
    roomConfigs.value = {}
  }
}

onMounted(() => {
  pollBartelsStatus()
  pollIoBrokerStatus()
  setInterval(pollBartelsStatus, 10000)
  setInterval(pollIoBrokerStatus, 10000)
  loadRoomsConfig()
})

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

const showChat = ref(false)

</script>
<style scoped>
/* Floating Chat Button */
.chat-fab {
  position: fixed;
  right: 32px;
  bottom: 32px;
  z-index: 1001;
  width: 56px;
  height: 56px;
  background: #1976d2;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0,0,0,0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
}
.chat-fab:hover {
  background: #1565c0;
}

/* Chat Bubble Overlay */
.chat-bubble-overlay {
  position: fixed;
  right: 32px;
  bottom: 100px;
  z-index: 1002;
  display: flex;
  align-items: flex-end;
}
/* Flache Chat-Bubble, Scrollbarkeit für VoiceAssistant-Komponente */
/* Flache Chat-Bubble, keine eigene Scrollbarkeit, Höhe für VoiceAssistant-Komponente optimiert */
.chat-bubble {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.18);
  padding: 32px 24px 24px 24px;
  min-width: 320px;
  max-width: 95vw;
  width: 400px;
  /*height: 480px;*/
  position: relative;
  animation: chat-pop 0.2s;
  display: flex;
  flex-direction: column;
  /* Keine eigene Scrollbarkeit, alles an VoiceAssistant-Komponente */
}

@media (max-width: 900px) {
  .chat-bubble {
    width: 95vw;
    height: 60vh;
    min-width: 0;
    max-width: 95vw;
    min-height: 180px;
  }
  }
.chat-close {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  font-size: 22px;
  color: #1976d2;
  cursor: pointer;
}
@keyframes chat-pop {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
.chat-bubble-enter-active, .chat-bubble-leave-active {
  transition: opacity 0.2s;
}
.chat-bubble-enter-from, .chat-bubble-leave-to {
  opacity: 0;
}
</style>
