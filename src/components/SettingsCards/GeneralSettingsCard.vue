<template>
  <v-card flat>
    <v-card-title>Allgemeine Einstellungen</v-card-title>
    <v-card-text>
      <v-switch v-model="darkTheme" label="Dunkles Theme aktivieren" />
      <v-switch v-model="notifications" label="Benachrichtigungen anzeigen" />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue'
const props = defineProps({
  darkTheme: Boolean,
  notifications: Boolean
})
const emit = defineEmits(['update:darkTheme', 'update:notifications'])

const darkTheme = ref(props.darkTheme)
const notifications = ref(props.notifications)

// Lokale Refs synchron halten, wenn sich Props ändern
watch(() => props.darkTheme, val => { darkTheme.value = val })
watch(() => props.notifications, val => { notifications.value = val })

watch(darkTheme, val => emit('update:darkTheme', val))
watch(notifications, val => emit('update:notifications', val))
</script>
