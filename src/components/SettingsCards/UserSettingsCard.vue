<template>
  <v-card flat>
    <v-card-title>Benutzerverwaltung</v-card-title>
    <v-card-text>
      <v-text-field
        v-model="newPassword"
        label="Neues Passwort"
        type="password"
      />
      <v-btn @click="$emit('changePassword', newPassword)">Passwort ändern</v-btn>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref } from 'vue'
const newPassword = ref('')
</script>
