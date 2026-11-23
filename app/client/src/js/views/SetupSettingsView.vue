<template>
  <div class="setup-view">
    <header class="setup-header">
      <h2 class="setup-header__title">Schritt 1 / 3</h2>
      <p class="setup-header__sub">Einstellungen</p>
    </header>

    <section class="setup-section">
      <label class="field-label">Kartenfilter</label>
      <div class="radio-group">
        <label v-for="opt in adultOptions" :key="opt.value" class="radio-card">
          <input v-model="adultType" type="radio" :value="opt.value" :aria-label="opt.label">
          <span class="radio-card__label">{{ opt.label }}</span>
          <span class="radio-card__desc">{{ opt.desc }}</span>
        </label>
      </div>
    </section>

    <section class="setup-section">
      <label class="field-label">Spielmodus</label>
      <div class="radio-group">
        <label v-for="opt in modeOptions" :key="opt.value" class="radio-card">
          <input v-model="gameType" type="radio" :value="opt.value" :aria-label="opt.label">
          <span class="radio-card__label">{{ opt.label }}</span>
          <span class="radio-card__desc">{{ opt.desc }}</span>
        </label>
      </div>
    </section>


    <div class="setup-footer">
      <p v-if="error" class="error-msg">{{ error }}</p>
      <SetupNav :loading="loading" @back="router.push({ name: 'home' })" @next="next" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import SetupNav from '../components/SetupNav.vue'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const adultType = ref('No Adult Dares')
const gameType = ref('Cardpile')
const error = ref(null)
const loading = ref(false)

const adultOptions = [
  { value: 'No Adult Dares', label: 'Nur U18', desc: 'Keine expliziten Inhalte' },
  { value: 'Misc Dares', label: 'Gemischt', desc: 'U18 und Ü18 Karten' },
  { value: 'Adult Dares Only', label: 'Nur Ü18', desc: 'Nur explizite Inhalte' },
]

const modeOptions = [
  { value: 'Cardpile', label: 'Kartenstapel', desc: 'Karten aufdecken und Aufgaben erledigen' },
  { value: 'Turns', label: 'Reihum', desc: 'Alle ziehen nacheinander in fester Reihenfolge' },
  { value: 'Hand', label: 'Handkarten', desc: 'Alle spielen mit eigenen Karten auf dem Handy und stellen sich gegenseitig Aufgaben' },
]

onMounted(async () => {
  if (!store.game || store.hash !== route.params.hash) {
    await store.loadGame(route.params.hash)
  }
  // Coming back to an existing game: keep its settings
  if (store.game) {
    adultType.value = store.game.adultType ?? adultType.value
    gameType.value = store.game.gameType ?? gameType.value
  }
})

async function next() {
  loading.value = true
  error.value = null
  try {
    await store.setup({
      adultType: adultType.value,
      gameType: gameType.value,
    })
    router.push({ name: 'setup-players', params: { hash: route.params.hash } })
  } catch {
    error.value = 'Fehler beim Speichern. Bitte erneut versuchen.'
  } finally {
    loading.value = false
  }
}
</script>
