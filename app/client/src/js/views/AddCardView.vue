<template>
  <div class="add-card-view">
    <div class="add-card-box">
      <img :src="DarePileLogo" alt="DarePile Logo" class="add-card-icon" />
      <h1 class="add-card-title">DarePile</h1>
      <p class="add-card-sub">Karte zu einem Spiel hinzufügen</p>

      <form v-if="!submitted" class="add-card-form" @submit.prevent="submit">
        <div v-if="isTruthOrDare" class="type-toggle" role="group" aria-label="Kartentyp">
          <button
            v-for="opt in TYPE_OPTIONS"
            :key="opt.value"
            type="button"
            class="type-toggle__btn"
            :class="{ 'type-toggle__btn--active': type === opt.value }"
            @click="type = opt.value"
          >{{ opt.label }}</button>
        </div>
        <label class="field-label" for="dare-input">{{ type === 'Truth' ? 'Deine Frage' : 'Deine Aufgabe' }}</label>
        <textarea
          id="dare-input"
          v-model="dare"
          class="input textarea"
          :placeholder="type === 'Truth' ? 'Stelle eine Frage, die ehrlich beantwortet werden muss …' : 'Beschreibe die Aufgabe …'"
          rows="4"
          maxlength="500"
          required
        />
        <p class="char-count">{{ dare.length }} / 500</p>

        <div class="add-card-options">
          <div class="add-card-field">
            <label class="field-label" for="add-level">Level</label>
            <select id="add-level" v-model="level" class="input input--select" aria-label="Level auswählen">
              <option v-for="l in 10" :key="l" :value="l">Level {{ l }}</option>
            </select>
          </div>
          <div class="add-card-field add-card-field--toggle">
            <label class="toggle-label">
              <input type="checkbox" v-model="adultsOnly" class="toggle-input" aria-label="FSK 18" />
              <span class="toggle-track"><span class="toggle-thumb" /></span>
              <span class="toggle-text">FSK 18</span>
            </label>
          </div>
        </div>

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
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { addCustomCard, getGame } from '../api/game.js'
import DarePileLogo from '../../../icons/darepile_logo.svg'

const route = useRoute()
const dare = ref('')
const level = ref(5)
const adultsOnly = ref(false)
// "Wahrheit oder Pflicht": questions can be added as well
const isTruthOrDare = ref(false)
const type = ref('Dare')
const TYPE_OPTIONS = [
  { value: 'Dare', label: 'Pflicht' },
  { value: 'Truth', label: 'Wahrheit' },
]
const loading = ref(false)
const error = ref(null)
const submitted = ref(false)

async function submit() {
  const text = dare.value.trim()
  if (!text) return
  loading.value = true
  error.value = null
  try {
    await addCustomCard(route.params.hash, text, level.value, adultsOnly.value, isTruthOrDare.value ? type.value : 'Dare')
    submitted.value = true
  } catch (e) {
    error.value = 'Fehler beim Hinzufügen. Bitte erneut versuchen.'
  } finally {
    loading.value = false
  }
}

function reset() {
  dare.value = ''
  level.value = 5
  adultsOnly.value = false
  submitted.value = false
}

onMounted(async () => {
  try {
    const game = await getGame(route.params.hash)
    isTruthOrDare.value = game.gameType === 'TruthOrDare'
  } catch { /* without the game type only dares can be added */ }
})
</script>
