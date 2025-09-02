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
  
  <div class="dashboard-grid">
    <div
      v-for="cardName in roomConfigs[selectedRoom] || []"
      :key="cardName"
      :class="['card-tile', { expanded: expandedCards[cardName] }]"
    >
      <div class="tile-header" @click="!expandedCards[cardName] && toggleCard(cardName)">
        <h3>{{ getCardTitle(cardName) }}</h3>
      </div>
      
      <!-- Miniaturansicht (immer sichtbar) -->
      <div v-if="!expandedCards[cardName]" class="tile-preview">
        <component :is="cardComponents[cardName]" :room="selectedRoom" />
        <!-- Overlay für Klick-Interaktion -->
        <div class="preview-overlay" @click="toggleCard(cardName)">
          <v-icon class="expand-icon">mdi-fullscreen</v-icon>
        </div>
      </div>
      
      <!-- Vollansicht (nur wenn expandiert) -->
      <div v-if="expandedCards[cardName]" class="tile-content" @click.stop>
        <component :is="cardComponents[cardName]" :room="selectedRoom" />
        <v-btn 
          class="close-btn"
          icon="mdi-close"
          size="small"
          @click="toggleCard(cardName)"
        />
      </div>
    </div>
  </div>
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
import ShutterCard from '../components/DashboardCards/ShutterCard.vue'
import PVRoom from '../components/DashboardCards/PVRoom.vue'
import VentilationCard from '../components/DashboardCards/VentilationCard.vue'
import DoorbellCard from '../components/DashboardCards/DoorbellCard.vue'

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
  ShutterCard,
  VentilationCard,
  DoorbellCard,
}

function getCardTitle(cardName) {
  // Optional: Individuelle Titel pro Card
  const titles = {
    EnergyMonthCard: 'Energie Monat',
    EnergyTodayCard: 'Energie Heute',
    ClimateCard: 'Klima',
    LightCard: 'Licht',
    HeizungCard: 'Heizung',
    WarmwasserCard: 'Warmwasser',
    WarmwasserTempCard: 'Warmwasser Temperatur',
    WWSolarCard: 'WW Solar',
    PVRoom: 'PV Raum',
    ShutterCard: 'Rollläden',
    VentilationCard: 'Lüftung',
    DoorbellCard: 'Türspionkamera',
  }
  return titles[cardName] || cardName
}

const showChat = ref(false)
const expandedCards = ref({})

function toggleCard(cardName) {
  expandedCards.value[cardName] = !expandedCards.value[cardName]
}

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

/* Dashboard Grid Layout */
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
  gap: 16px;
  padding: 16px;
  max-width: 100%;
}

/* Card Tiles */
.card-tile {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  
  /* Kleine Tile standardmäßig */
  width: 20rem;
  height: 20rem;
  min-width: 20rem;
  min-height: 20rem;
}

.card-tile:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  transform: translateY(-2px);
}

/* Expanded Tile */
.card-tile.expanded {
  width: 40rem;
  height: 40rem;
  z-index: 10;
  position: relative;
  grid-column: span 2; /* Nimmt mehrere Grid-Spalten ein */
  grid-row: span 2;    /* Nimmt mehrere Grid-Zeilen ein */
}

/* Tile Header */
.tile-header {
  padding: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e0e0e0;
  background: #f5f5f5;
  height: 48px;
  box-sizing: border-box;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.tile-header:hover {
  background: #e8e8e8;
}

.card-tile.expanded .tile-header {
  cursor: default;
  background: #f5f5f5;
}

.card-tile.expanded .tile-header:hover {
  background: #f5f5f5;
}

.tile-header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-tile.expanded .tile-header h3 {
  font-size: 18px;
}

/* Tile Content */
.tile-content {
  padding: 16px;
  height: calc(100% - 48px);
  overflow: auto;
  position: relative;
}

/* Miniaturansicht */
.tile-preview {
  position: relative;
  height: calc(100% - 48px);
  overflow: hidden;
  transform: scale(0.3);
  transform-origin: top left;
  width: 333.33%; /* 100% / 0.3 um den Scale-Effekt auszugleichen */
  height: 333.33%; /* 100% / 0.3 um den Scale-Effekt auszugleichen */
  pointer-events: none; /* Verhindert Interaktion mit der Miniatur */
}

/* Overlay für Klick-Interaktion */
.preview-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: all; /* Ermöglicht Klicks auf das Overlay */
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.tile-preview:hover .preview-overlay {
  opacity: 1;
}

.expand-icon {
  color: #1976d2;
  font-size: 3rem !important;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

/* Close Button für expandierte Cards */
.close-btn {
  position: absolute !important;
  top: 8px;
  right: 8px;
  z-index: 10;
  background: rgba(255, 255, 255, 0.9) !important;
}

/* Responsive Layout */
@media (max-width: 768px) {
  .dashboard-grid {
    grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
    gap: 12px;
    padding: 12px;
  }
  
  .card-tile {
    width: 15rem;
    height: 15rem;
    min-width: 15rem;
    min-height: 15rem;
  }
  
  .card-tile.expanded {
    width: calc(100vw - 32px);
    height: 400px;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    grid-column: unset;
    grid-row: unset;
  }
  
  .tile-header h3 {
    font-size: 12px;
  }
}
</style>
