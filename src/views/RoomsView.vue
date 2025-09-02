<template>
  <div>
    <v-card class="mb-6" color="surface" elevation="2">
      <v-card-title class="font-weight-bold" style="color: var(--v-theme-primary);">Räume verwalten</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="addOrUpdateRoom">
          <v-row>
            <v-col cols="12" sm="6" md="4">
              <v-text-field v-model="roomForm.name" label="Raumname" color="primary" required />
            </v-col>
            <v-col cols="12" sm="6" md="8">
              <v-select v-model="roomForm.cards" :items="availableCards" label="Cards zuweisen" color="secondary" multiple chips />
            </v-col>
          </v-row>
          <v-row v-if="roomForm.cards.length">
            <v-col cols="12">
              <label class="font-weight-bold" style="color: var(--v-theme-primary);">Reihenfolge der Cards:</label>
              <v-list density="compact" class="mb-2">
                <v-list-item v-for="(card, idx) in roomForm.cards" :key="card">
                  <template #prepend>
                    <v-icon color="primary">mdi-view-dashboard</v-icon>
                  </template>
                  <v-list-item-title>{{ card }}</v-list-item-title>
                  <template #append>
                    <v-btn icon size="x-small" @click="moveCard(idx, -1)" :disabled="idx === 0" color="primary"><v-icon>mdi-arrow-up</v-icon></v-btn>
                    <v-btn icon size="x-small" @click="moveCard(idx, 1)" :disabled="idx === roomForm.cards.length-1" color="primary"><v-icon>mdi-arrow-down</v-icon></v-btn>
                  </template>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12" sm="6" md="4">
              <v-btn color="primary" type="submit" class="mr-2">{{ roomForm.editIndex === null ? 'Hinzufügen' : 'Speichern' }}</v-btn>
              <v-btn v-if="roomForm.editIndex !== null" color="grey" @click="resetForm">Abbrechen</v-btn>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
    </v-card>

    <v-card color="surface" elevation="2">
      <v-card-title class="font-weight-bold" style="color: var(--v-theme-primary);">Vorhandene Räume</v-card-title>
      <v-card-text>
        <v-table density="comfortable" class="room-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Cards</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(room, idx) in rooms" :key="room.id">
              <td><span class="font-weight-bold" style="color: var(--v-theme-primary);">{{ room.name }}</span></td>
              <td>
                <v-chip-group>
                  <v-chip v-for="card in room.cards" :key="card" color="secondary" class="mr-1">{{ card }}</v-chip>
                </v-chip-group>
              </td>
              <td>
                <v-btn color="info" size="small" @click="editRoom(idx)" class="mr-2">Bearbeiten</v-btn>
                <v-btn color="error" size="small" @click="deleteRoom(idx)">Löschen</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'

// Cards aus DashboardCards importieren (Namen wie in DashboardView)
const availableCards = [
  'EnergyMonthCard',
  'EnergyTodayCard',
  'ClimateCard',
  'LightCard',
  'HeizungCard',
  'WarmwasserCard',
  'WarmwasserTempCard',
  'WWSolarCard',
  'PVRoom',
  'ShutterCard',
  'VentilationCard',
  'DoorbellCard',
]


const rooms = ref([])

async function loadRooms() {
  try {
    const res = await fetch('/iobroker/api/rooms.php')
    if (res.ok) {
      rooms.value = await res.json()
    } else {
      rooms.value = []
    }
  } catch (e) {
    rooms.value = []
  }
}

async function saveRooms() {
  try {
    await fetch('/iobroker/api/rooms.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(rooms.value)
    })
  } catch (e) {}
}

onMounted(loadRooms)

const roomForm = reactive({
  name: '',
  cards: [],
  editIndex: null,
})

function resetForm() {
  roomForm.name = ''
  roomForm.cards = []
  roomForm.editIndex = null
}

async function addOrUpdateRoom() {
  if (!roomForm.name.trim()) return
  if (roomForm.editIndex === null) {
    // Neuen Raum anlegen
    rooms.value.push({
      id: roomForm.name.toLowerCase().replace(/\s+/g, '_'),
      name: roomForm.name,
      cards: [...roomForm.cards],
    })
  } else {
    // Raum bearbeiten
    rooms.value[roomForm.editIndex].name = roomForm.name
    rooms.value[roomForm.editIndex].cards = [...roomForm.cards]
  }
  await saveRooms()
  resetForm()
}

function editRoom(idx) {
  const r = rooms.value[idx]
  roomForm.name = r.name
  roomForm.cards = [...r.cards]
  roomForm.editIndex = idx
}

function moveCard(idx, dir) {
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= roomForm.cards.length) return
  const arr = roomForm.cards
  const tmp = arr[idx]
  arr[idx] = arr[newIdx]
  arr[newIdx] = tmp
}

async function deleteRoom(idx) {
  if (confirm('Diesen Raum wirklich löschen?')) {
    rooms.value.splice(idx, 1)
    await saveRooms()
    resetForm()
  }
}
</script>

<style scoped>
.room-table th, .room-table td {
  padding: 0.5em 1em;
  text-align: left;
}
</style>