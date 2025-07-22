
<template>
  <div>
    <h2>Geräteverwaltung</h2>
    <v-card class="mb-6" color="surface" elevation="2">
      <v-card-title class="font-weight-bold">Neues Gerät anlegen</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newDevice.name" label="Name" color="primary" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="newDevice.type" :items="[
              { title: 'Schalter', value: 'switch' },
              { title: 'Sensor', value: 'sensor' },
              { title: 'Zielwert', value: 'target' }
            ]" label="Typ" color="primary" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newDevice.id" label="IO-Broker ID" color="primary" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="newDevice.room" :items="roomNames" label="Raum" color="primary" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newDevice.synonyms" label="Synonyme (Komma getrennt)" color="secondary" placeholder="z.B. Licht, Lampe" />
          </v-col>
          <v-col cols="12" sm="6" v-if="newDevice.type === 'switch'">
            <v-text-field v-model="newDevice.onValue" label="onValue" color="accent" />
            <v-text-field v-model="newDevice.offValue" label="offValue" color="accent" />
          </v-col>
          <v-col cols="12" sm="6" v-if="newDevice.type === 'sensor' || newDevice.type === 'target'">
            <v-text-field v-model="newDevice.unit" label="Einheit" color="info" placeholder="z.B. °C, kWh, %" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newDevice.actions" label="Aktionen (Komma getrennt)" color="secondary" placeholder="z.B. schalte, lese" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" type="submit" @click="addDevice">Gerät hinzufügen</v-btn>
      </v-card-actions>
    </v-card>

    <h3>Bestehende Geräte</h3>
    <v-row class="mb-4">
      <v-col cols="12" sm="6" md="4">
        <v-select v-model="selectedRoom" :items="roomNames" label="Raum filtern" color="primary" clearable />
      </v-col>
    </v-row>
    <div v-for="room in roomNames" :key="room">
      <div v-if="!selectedRoom || selectedRoom === room">
        <h4 class="mt-6 mb-2" style="color: var(--v-theme-primary);">{{ room }}</h4>
        <v-row>
          <v-col v-for="device in devices.filter(d => d.room === room)" :key="device.id" cols="12" sm="6" md="4">
            <v-card class="mb-4" color="surface" elevation="2">
              <v-card-title class="d-flex align-center" style="gap: 8px;">
                <v-icon color="primary" v-if="device.type === 'switch'">mdi-lightbulb</v-icon>
                <v-icon color="info" v-else-if="device.type === 'sensor'">mdi-thermometer</v-icon>
                <v-icon color="accent" v-else-if="device.type === 'target'">mdi-target</v-icon>
                <span class="font-weight-bold">{{ device.name }}</span>
                <span class="ml-auto" style="opacity:0.7">({{ device.type }})</span>
              </v-card-title>
              <v-card-text>
                <div><strong>ID:</strong> <span style="opacity:0.8">{{ device.id }}</span></div>
                <div v-if="device.synonyms && device.synonyms.length"><strong>Synonyme:</strong> <span style="opacity:0.8">{{ device.synonyms.join(', ') }}</span></div>
                <div v-if="device.actions && device.actions.length"><strong>Aktionen:</strong> <span style="opacity:0.8">{{ device.actions.join(', ') }}</span></div>
                <div v-if="device.unit"><strong>Einheit:</strong> <span style="opacity:0.8">{{ device.unit }}</span></div>
                <div v-if="device.onValue !== undefined"><strong>onValue:</strong> <span style="opacity:0.8">{{ device.onValue }}</span></div>
                <div v-if="device.offValue !== undefined"><strong>offValue:</strong> <span style="opacity:0.8">{{ device.offValue }}</span></div>
              </v-card-text>
              <v-card-actions>
                <v-btn color="info" size="small" @click="openEdit(device)">Bearbeiten</v-btn>
                <v-btn color="error" size="small" @click="deleteDevice(device)">Löschen</v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </div>
    </div>

    <!-- Edit Dialog -->
    <v-dialog v-model="editDialog" max-width="600px">
      <v-card>
        <v-card-title class="font-weight-bold">Gerät bearbeiten</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editDevice.name" label="Name" color="primary" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select v-model="editDevice.type" :items="[
                { title: 'Schalter', value: 'switch' },
                { title: 'Sensor', value: 'sensor' },
                { title: 'Zielwert', value: 'target' }
              ]" label="Typ" color="primary" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editDevice.id" label="IO-Broker ID" color="primary" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select v-model="editDevice.room" :items="roomNames" label="Raum" color="primary" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editDevice.synonyms" label="Synonyme (Komma getrennt)" color="secondary" />
            </v-col>
            <v-col cols="12" sm="6" v-if="editDevice.type === 'switch'">
              <v-text-field v-model="editDevice.onValue" label="onValue" color="accent" />
              <v-text-field v-model="editDevice.offValue" label="offValue" color="accent" />
            </v-col>
            <v-col cols="12" sm="6" v-if="editDevice.type === 'sensor' || editDevice.type === 'target'">
              <v-text-field v-model="editDevice.unit" label="Einheit" color="info" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editDevice.actions" label="Aktionen (Komma getrennt)" color="secondary" />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" @click="saveEdit">Speichern</v-btn>
          <v-btn color="grey" @click="editDialog = false">Abbrechen</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      devices: [],
      selectedRoom: '',
      editDialog: false,
      editDevice: {
        name: '', type: '', id: '', room: '', synonyms: '', onValue: '', offValue: '', unit: '', actions: ''
      },
      newDevice: {
        name: '',
        type: '',
        id: '',
        room: '',
        synonyms: '',
        onValue: '',
        offValue: '',
        unit: '',
        actions: ''
      },
      roomNames: [],
      categories: []
    }
  },
  methods: {
    async fetchDevices() {
      try {
        const res = await fetch('/iobroker/devices.json');
        const data = await res.json();
        // Flaches Array erzeugen
        let devices = [];
        for (const room in data) {
          for (const dev of data[room]) {
            devices.push({ ...dev, room });
          }
        }
        this.devices = devices;
        this.roomNames = Object.keys(data);
        if (this.newDevice.room) {
          this.updateCategories();
        }
      } catch (e) {
        this.devices = [];
      }
    },
    updateCategories() {
      // Kategorien für den gewählten Raum auslesen
      const devs = this.devices.filter(d => d.room === this.newDevice.room);
      this.categories = [...new Set(devs.map(d => d.category).filter(Boolean))];
      this.newDevice.category = '';
    },
    async addDevice() {
      try {
        const dev = {
          id: this.newDevice.id,
          name: this.newDevice.name,
          type: this.newDevice.type,
          synonyms: this.newDevice.synonyms ? this.newDevice.synonyms.split(',').map(s => s.trim()) : [],
          actions: this.newDevice.actions ? this.newDevice.actions.split(',').map(a => a.trim()) : [],
        };
        if (this.newDevice.type === 'switch') {
          dev.onValue = this.newDevice.onValue;
          dev.offValue = this.newDevice.offValue;
        }
        if (this.newDevice.type === 'sensor' || this.newDevice.type === 'target') {
          dev.unit = this.newDevice.unit;
        }
        await fetch('/iobroker/api/devices.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ...dev, room: this.newDevice.room })
        });
        this.newDevice = { name: '', type: '', id: '', room: '', synonyms: '', onValue: '', offValue: '', unit: '', actions: '' };
        this.fetchDevices();
      } catch (e) {
        alert('Fehler beim Speichern!');
      }
    },
    openEdit(device) {
      this.editDevice = {
        ...device,
        synonyms: device.synonyms ? device.synonyms.join(', ') : '',
        actions: device.actions ? device.actions.join(', ') : ''
      };
      this.editDialog = true;
    },
    async saveEdit() {
      try {
        const dev = {
          id: this.editDevice.id,
          name: this.editDevice.name,
          type: this.editDevice.type,
          synonyms: this.editDevice.synonyms ? this.editDevice.synonyms.split(',').map(s => s.trim()) : [],
          actions: this.editDevice.actions ? this.editDevice.actions.split(',').map(a => a.trim()) : [],
        };
        if (this.editDevice.type === 'switch') {
          dev.onValue = this.editDevice.onValue;
          dev.offValue = this.editDevice.offValue;
        }
        if (this.editDevice.type === 'sensor' || this.editDevice.type === 'target') {
          dev.unit = this.editDevice.unit;
        }
        await fetch('/iobroker/api/devices.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ...dev, room: this.editDevice.room, oldId: this.editDevice.id, oldRoom: this.editDevice.room })
        });
        this.editDialog = false;
        this.fetchDevices();
      } catch (e) {
        alert('Fehler beim Bearbeiten!');
      }
    },
    async deleteDevice(device) {
      if (!confirm('Gerät wirklich löschen?')) return;
      try {
        await fetch('/iobroker/api/devices.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: device.id, room: device.room })
        });
        this.fetchDevices();
      } catch (e) {
        alert('Fehler beim Löschen!');
      }
    }
  },
  mounted() {
    this.fetchDevices();
  }
}
</script>