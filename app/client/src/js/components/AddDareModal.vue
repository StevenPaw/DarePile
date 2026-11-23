<template>
  <div class="modal-overlay modal-overlay--top" @click.self="$emit('close')">
    <form class="modal-box" @submit.prevent="submit">
      <h3 class="modal-title">Aufgabe hinzufügen</h3>
      <textarea
        v-model="dare"
        class="input textarea"
        placeholder="Aufgabe eingeben …"
        rows="3"
        maxlength="500"
        aria-label="Aufgabe"
      />
      <div class="add-card-options">
        <div class="add-card-field">
          <label class="field-label" for="add-dare-level">Level</label>
          <select id="add-dare-level" v-model="level" class="input input--select">
            <option v-for="l in 10" :key="l" :value="l">Level {{ l }}</option>
          </select>
        </div>
        <div class="add-card-field add-card-field--toggle">
          <label class="toggle-label">
            <input v-model="adultsOnly" type="checkbox" class="toggle-input" aria-label="FSK 18">
            <span class="toggle-track"><span class="toggle-thumb" /></span>
            <span class="toggle-text">FSK 18</span>
          </label>
        </div>
      </div>
      <p v-if="error" class="error-msg">{{ error }}</p>
      <div class="modal-actions">
        <button type="button" class="btn btn--ghost" @click="$emit('close')">Abbrechen</button>
        <button type="submit" class="btn btn--primary" :disabled="!dare.trim() || saving">
          <span v-if="saving" class="spinner" />
          <span v-else>Hinzufügen</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { addCustomCard } from '../api/game.js'

const props = defineProps({
  hash: { type: String, required: true },
})

const emit = defineEmits(['close', 'added'])

const dare = ref('')
const level = ref(5)
const adultsOnly = ref(false)
const saving = ref(false)
const error = ref(null)

async function submit() {
  const text = dare.value.trim()
  if (!text) return
  saving.value = true
  error.value = null
  try {
    await addCustomCard(props.hash, text, level.value, adultsOnly.value)
    emit('added')
  } catch {
    error.value = 'Aufgabe konnte nicht hinzugefügt werden.'
  } finally {
    saving.value = false
  }
}
</script>
