<template>
  <div class="add-card-view">
    <div class="add-card-box">
      <div class="add-card-icon">🃏</div>
      <h1 class="add-card-title">DarePile</h1>
      <p class="add-card-sub">Karte zu einem Spiel hinzufügen</p>

      <form v-if="!submitted" class="add-card-form" @submit.prevent="submit">
        <label class="field-label" for="dare-input">Deine Aufgabe</label>
        <textarea
          id="dare-input"
          v-model="dare"
          class="input textarea"
          placeholder="Beschreibe die Aufgabe …"
          rows="4"
          maxlength="500"
          required
        />
        <p class="char-count">{{ dare.length }} / 500</p>

        <button
          type="submit"
          class="btn btn--primary btn--lg"
          :disabled="!dare.trim() || loading"
        >
          <span v-if="loading" class="spinner" />
          <span v-else>Karte hinzufügen</span>
        </button>

        <p v-if="error" class="error-msg">{{ error }}</p>
      </form>

      <div v-else class="add-card-success">
        <span class="success-icon">✅</span>
        <p class="success-msg">Karte wurde hinzugefügt!</p>
        <button class="btn btn--secondary" @click="reset">Weitere Karte hinzufügen</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { addCustomCard } from '../api/game.js'

const route = useRoute()
const dare = ref('')
const loading = ref(false)
const error = ref(null)
const submitted = ref(false)

async function submit() {
  const text = dare.value.trim()
  if (!text) return
  loading.value = true
  error.value = null
  try {
    await addCustomCard(route.params.hash, text)
    submitted.value = true
  } catch (e) {
    error.value = 'Fehler beim Hinzufügen. Bitte erneut versuchen.'
  } finally {
    loading.value = false
  }
}

function reset() {
  dare.value = ''
  submitted.value = false
}
</script>

