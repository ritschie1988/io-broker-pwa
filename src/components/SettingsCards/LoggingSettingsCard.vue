<template>
  <v-card flat>
    <v-card-title>Logging & Debug</v-card-title>
    <v-card-text>
      <v-select
        v-model="logLevel"
        :items="['Fehler', 'Info', 'Debug']"
        label="Log-Level"
      />
      <v-btn @click="$emit('showLog')">Log-Datei anzeigen</v-btn>
      <v-btn @click="$emit('downloadLog')">Log-Datei herunterladen</v-btn>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue'
const props = defineProps({
  logLevel: String
})
const emit = defineEmits(['update:logLevel'])
const logLevel = ref(props.logLevel)
watch(logLevel, val => emit('update:logLevel', val))
</script>
