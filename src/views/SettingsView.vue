
<template>
  <v-container>
    <v-toolbar class="settings-toolbar" flat>
      <v-slide-group
        v-model="selectedSection"
        class="settings-menu"
        show-arrows
        center-active
        prev-icon="mdi-chevron-left"
        next-icon="mdi-chevron-right"
        mobile-breakpoint="0"
      >
        <v-slide-group-item
          v-for="section in settingsSections"
          :key="section.id"
          :value="section.id"
        >
          <v-btn
            :color="selectedSection === section.id ? 'primary' : 'default'"
            class="mx-1"
            @click="selectedSection = section.id"
            rounded
            text
          >
            {{ section.name }}
          </v-btn>
        </v-slide-group-item>
      </v-slide-group>
    </v-toolbar>
    <v-row class="settings-cards">
      <template v-for="cardName in sectionConfigs[selectedSection]" :key="cardName">
        <v-col cols="12" md="8" lg="6">
          <component
            :is="settingsCardComponents[cardName]"
            v-bind="getCardProps(cardName)"
            v-on="getCardEvents(cardName)"
          />
        </v-col>
      </template>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import GeneralSettingsCard from '../components/SettingsCards/GeneralSettingsCard.vue'
import PVHeizstabSettingsCard from '../components/SettingsCards/PVHeizstabSettingsCard.vue'
import ConnectionSettingsCard from '../components/SettingsCards/ConnectionSettingsCard.vue'
import LoggingSettingsCard from '../components/SettingsCards/LoggingSettingsCard.vue'
import UserSettingsCard from '../components/SettingsCards/UserSettingsCard.vue'

const settingsSections = [
  { id: 'general', name: 'Allgemein' },
  { id: 'pvheizstab', name: 'PV-Heizstab' },
  { id: 'connection', name: 'Verbindung' },
  { id: 'logging', name: 'Logging' },
  { id: 'user', name: 'Benutzer' },
]
const selectedSection = ref(settingsSections[0].id)

const sectionConfigs = {
  general: ['GeneralSettingsCard'],
  pvheizstab: ['PVHeizstabSettingsCard'],
  connection: ['ConnectionSettingsCard'],
  logging: ['LoggingSettingsCard'],
  user: ['UserSettingsCard'],
}

const settingsCardComponents = {
  GeneralSettingsCard,
  PVHeizstabSettingsCard,
  ConnectionSettingsCard,
  LoggingSettingsCard,
  UserSettingsCard,
}


// Props für zentrale Settings
import { defineProps, defineEmits } from 'vue'
const props = defineProps({
  darkTheme: Boolean,
  notifications: Boolean
})
const emit = defineEmits(['update:darkTheme', 'update:notifications'])

// Lokale States für andere Settings
const pvThreshold = ref(3500)
const tempMin = ref(50)
const tempMax = ref(60)
const override = ref(false)
const apiUrl = ref('')
const logLevel = ref('Info')

function showLog() {
  // Log-Datei anzeigen
}
function downloadLog() {
  // Log-Datei herunterladen
}
function changePassword(newPassword) {
  // Passwort ändern
}

// Props und Events je Card
function getCardProps(cardName) {
  switch (cardName) {
    case 'GeneralSettingsCard':
      return { darkTheme: props.darkTheme, notifications: props.notifications }
    case 'PVHeizstabSettingsCard':
      return { pvThreshold: pvThreshold.value, tempMin: tempMin.value, tempMax: tempMax.value, override: override.value }
    case 'ConnectionSettingsCard':
      return { apiUrl: apiUrl.value }
    case 'LoggingSettingsCard':
      return { logLevel: logLevel.value }
    default:
      return {}
  }
}
function getCardEvents(cardName) {
  switch (cardName) {
    case 'GeneralSettingsCard':
      return {
        'update:darkTheme': val => emit('update:darkTheme', val),
        'update:notifications': val => emit('update:notifications', val),
      }
    case 'PVHeizstabSettingsCard':
      return {
        'update:pvThreshold': val => (pvThreshold.value = val),
        'update:tempMin': val => (tempMin.value = val),
        'update:tempMax': val => (tempMax.value = val),
        'update:override': val => (override.value = val),
      }
    case 'ConnectionSettingsCard':
      return {
        'update:apiUrl': val => (apiUrl.value = val),
      }
    case 'LoggingSettingsCard':
      return {
        'update:logLevel': val => (logLevel.value = val),
        showLog,
        downloadLog,
      }
    case 'UserSettingsCard':
      return {
        changePassword,
      }
    default:
      return {}
  }
}
</script>

<style scoped>
.settings-menu {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  overflow-x: auto;
  max-width: 100vw;
}
.settings-toolbar {
  flex-wrap: wrap;
  min-height: unset;
  position: sticky;
  top: var(--v-app-bar-height, 56px);
  z-index: 2;
  background: white;
  margin-top: 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.03);
}
.settings-cards {
  margin-top: 2rem;
}
</style>