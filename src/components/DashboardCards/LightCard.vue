<template>
  <v-card>
    <v-card-title>
      <v-icon class="mr-2" color="primary">mdi-lightbulb</v-icon>
      Licht {{ roomName }}
    </v-card-title>
    <v-card-text>
      <template v-for="light in lights" :key="light.id">
        <div class="mb-4">
          <v-card 
            variant="outlined" 
            class="pa-3 light-card-item"
            :class="{ 'light-active': lightStates[light.id] }"
            color="surface"
          >
            <div class="d-flex align-center justify-space-between">
              <!-- Licht-Visualisierung -->
              <div class="d-flex align-center">
                <div class="light-bulb-container mr-3">
                  <div 
                    class="light-bulb"
                    :class="{ 'bulb-on': lightStates[light.id] }"
                  >
                    <v-icon 
                      size="32"
                      :color="lightStates[light.id] ? 'warning' : 'surface-variant'"
                      class="bulb-icon"
                    >
                      mdi-lightbulb
                    </v-icon>
                    <!-- Glow-Effekt wenn an -->
                    <div 
                      v-if="lightStates[light.id]" 
                      class="light-glow"
                    ></div>
                  </div>
                </div>
                
                <!-- Licht-Info -->
                <div>
                  <div class="text-subtitle1 font-weight-medium">{{ light.label }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ lightStates[light.id] ? 'Eingeschaltet' : 'Ausgeschaltet' }}
                  </div>
                </div>
              </div>
              
              <!-- Switch -->
              <v-switch
                v-model="lightStates[light.id]"
                color="primary"
                @change="setState(light)"
                hide-details
                class="light-switch"
              />
            </div>
            
            <!-- Zusätzliche Licht-Effekte nur bei eingeschalteten Lichtern -->
            <div v-if="lightStates[light.id]" class="light-rays">
              <div class="ray ray-1"></div>
              <div class="ray ray-2"></div>
              <div class="ray ray-3"></div>
            </div>
          </v-card>
        </div>
      </template>
      
      <!-- Raumzusammenfassung - nur anzeigen wenn mehr als 1 Licht -->
      <v-card 
        v-if="lights.length > 1"
        variant="tonal" 
        class="pa-3 mt-4" 
        color="surface-variant"
      >
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <v-icon class="mr-2" color="primary">mdi-home-lightbulb</v-icon>
            <div>
              <div class="text-subtitle2">{{ roomName }} Beleuchtung</div>
              <div class="text-caption text-medium-emphasis">
                {{ activeLightsCount }} von {{ lights.length }} Lichtern eingeschaltet
              </div>
            </div>
          </div>
          
          <!-- Alle an/aus Buttons -->
          <div class="d-flex flex-column flex-sm-row ga-2">
            <v-btn 
              size="small" 
              color="primary" 
              @click="setAllLights(true)"
              :disabled="activeLightsCount === lights.length"
              class="text-none"
            >
              <v-icon left size="16">mdi-lightbulb-on</v-icon>
              Alle an
            </v-btn>
            <v-btn 
              size="small" 
              color="surface-variant" 
              @click="setAllLights(false)"
              :disabled="activeLightsCount === 0"
              class="text-none"
            >
              <v-icon left size="16">mdi-lightbulb-off</v-icon>
              Alle aus
            </v-btn>
          </div>
        </div>
      </v-card>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'

const props = defineProps({ room: String })

// Definition aller Lichter pro Raum (flexibel - nicht jeder Raum hat alle Arten)
const roomLights = {
  wohnzimmer: [
    { id: 'sonoff.0.WohnzimmerLicht.POWER', label: 'Hauptlicht' },
    { id: '0_userdata.0.WohnzimmerAmbientePower', label: 'Fernsehlicht' }
  ],
  schlafzimmer: [
    { id: 'sonoff.0.SZLightMain.POWER', label: 'Hauptlicht' },
    { id: 'sonoff.0.SchlafzimmerNachtlicht.ENERGY_Power', label: 'Nachtlicht' }
  ],
  kueche: [
    { id: 'sonoff.0.KuecheLicht.POWER', label: 'Hauptlicht' },
    { id: 'alias.0.shelly.0.shellyplus1pm80646FE2A22C.Relay0.Switch', label: 'Kochlicht 1' },
    { id: 'alias.0.shelly.0.shellyplus178ee4ccd3f64.Relay0.Switch', label: 'Kochlicht 2' }
  ],
  vorraum: [
    { id: 'sonoff.0.VorzimmerLicht.POWER', label: 'Licht' }
  ],
  garten: [
    { id: 'shelly.0.shellyplus1pm#4855199c0d9c#1.Relay0.Switch', label: 'Terrassenlicht' }
  ]
}

// Raumname für die Card-Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'wohnzimmer': return 'Wohnzimmer'
    case 'schlafzimmer': return 'Schlafzimmer'
    case 'kueche': return 'Küche/Esszimmer'
    case 'vorraum': return 'Vorraum'
    case 'garten': return 'Garten'
    default: return ''
  }
})

// Aktuelle Lichter für den Raum (leer wenn Raum nicht definiert)
const lights = computed(() => roomLights[props.room] || [])

// Status der Lichter
const lightStates = ref({})

// Anzahl aktiver Lichter
const activeLightsCount = computed(() => {
  return Object.values(lightStates.value).filter(state => state).length
})

// Werte laden
async function fetchStates() {
  const states = {}
  for (const light of lights.value) {
    try {
      const res = await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=get/${light.id}`)
      const data = await res.json()
      states[light.id] = !!data.val
    } catch (error) {
      console.error(`Fehler beim Laden des Lichtstatus für ${light.label}:`, error)
      states[light.id] = false
    }
  }
  lightStates.value = states
}

// Wert setzen
async function setState(light) {
  try {
    const value = lightStates.value[light.id] ? 'true' : 'false'
    await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${light.id}&query=value=${value}`)
  } catch (error) {
    console.error(`Fehler beim Schalten von ${light.label}:`, error)
    // Bei Fehler den Switch zurücksetzen
    lightStates.value[light.id] = !lightStates.value[light.id]
  }
}

// Alle Lichter an/aus
async function setAllLights(state) {
  for (const light of lights.value) {
    lightStates.value[light.id] = state
    try {
      const value = state ? 'true' : 'false'
      await fetch(`/iobroker/api/iobroker-proxy.php?endpoint=set/${light.id}&query=value=${value}`)
    } catch (error) {
      console.error(`Fehler beim Schalten von ${light.label}:`, error)
    }
  }
}

// Bei Raumwechsel oder Mount neu laden
watch(() => props.room, fetchStates, { immediate: true })
</script>

<style scoped>
.light-card-item {
  position: relative;
  transition: all 0.3s ease;
  overflow: hidden;
}

.light-card-item.light-active {
  background: rgba(var(--v-theme-surface-variant), 0.3) !important;
}

.light-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-shadow-key-umbra-opacity, 0), var(--v-theme-shadow-key-umbra-opacity, 0), var(--v-theme-shadow-key-umbra-opacity, 0), 0.1);
}

.light-bulb-container {
  position: relative;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.light-bulb {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.4s ease;
  overflow: visible;
}

.light-bulb.bulb-on {
  background: radial-gradient(circle, 
    rgba(255, 248, 220, 0.3) 0%, 
    rgba(254, 244, 199, 0.1) 50%, 
    transparent 70%);
  box-shadow: 
    0 0 15px rgba(255, 248, 220, 0.4),
    inset 0 0 8px rgba(255, 248, 220, 0.2);
}

.bulb-icon {
  transition: all 0.3s ease;
}

.light-bulb.bulb-on .bulb-icon {
  filter: drop-shadow(0 0 6px rgba(255, 248, 220, 0.6));
}

.light-glow {
  position: absolute;
  top: -10px;
  left: -10px;
  right: -10px;
  bottom: -10px;
  border-radius: 50%;
  background: radial-gradient(circle, 
    rgba(255, 248, 220, 0.15) 0%, 
    rgba(254, 244, 199, 0.08) 30%, 
    transparent 60%);
  animation: gentle-pulse 2s ease-in-out infinite;
  pointer-events: none;
}

.light-rays {
  position: absolute;
  top: 50%;
  left: 60px;
  transform: translateY(-50%);
  pointer-events: none;
}

.ray {
  position: absolute;
  width: 30px;
  height: 1px;
  background: linear-gradient(90deg, 
    rgba(255, 248, 220, 0.4) 0%, 
    transparent 100%);
  transform-origin: left center;
  animation: ray-shimmer 1.5s ease-in-out infinite;
}

.ray-1 {
  top: -8px;
  animation-delay: 0s;
}

.ray-2 {
  top: 0px;
  animation-delay: 0.5s;
  width: 40px;
}

.ray-3 {
  top: 8px;
  animation-delay: 1s;
}

.light-switch {
  min-width: 60px;
}

/* Animationen */
@keyframes gentle-pulse {
  0%, 100% {
    opacity: 0.6;
    transform: scale(1);
  }
  50% {
    opacity: 1;
    transform: scale(1.05);
  }
}

@keyframes ray-shimmer {
  0%, 100% {
    opacity: 0.2;
    transform: scaleX(0.8);
  }
  50% {
    opacity: 0.6;
    transform: scaleX(1.2);
  }
}

/* Responsive Verbesserungen */
@media (max-width: 600px) {
  .ray {
    width: 20px;
  }
  
  .ray-2 {
    width: 25px;
  }
}
</style>