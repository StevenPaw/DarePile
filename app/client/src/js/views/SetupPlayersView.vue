<template>
  <div class="setup-view">
    <header class="setup-header">
      <h2 class="setup-header__title">Schritt 1 / 2</h2>
      <p class="setup-header__sub">Spieler & Einstellungen</p>
    </header>

    <section class="setup-section">
      <label class="field-label">Spieler hinzufügen</label>
      <div class="player-input">
        <input
          v-model="newPlayer"
          class="input"
          type="text"
          placeholder="Name eingeben …"
          maxlength="40"
          @keyup.enter="addPlayer"
        >
        <button class="btn btn--icon" :disabled="!newPlayer.trim()" @click="addPlayer">+</button>
      </div>

      <div class="player-chips">
        <div v-for="(p, i) in players" :key="i" class="chip">
          {{ p }}
          <button class="chip__remove" @click="removePlayer(i)">×</button>
        </div>
      </div>
    </section>

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
        <label class="radio-card">
          <input v-model="gameType" type="radio" value="Cardpile" aria-label="Kartenstapel">
          <span class="radio-card__label">Kartenstapel</span>
          <span class="radio-card__desc">Karten aufdecken und Aufgaben erledigen</span>
        </label>
        <label class="radio-card radio-card--disabled">
          <input type="radio" disabled aria-label="Verwaltet (demnächst verfügbar)">
          <span class="radio-card__label">Verwaltet <span class="badge--soon">Bald</span></span>
          <span class="radio-card__desc">Reihum Aufgaben zuweisen</span>
        </label>
      </div>
    </section>

    <div class="setup-footer">
      <p v-if="error" class="error-msg">{{ error }}</p>
      <button
        class="btn btn--primary btn--lg"
        :disabled="players.length < 2 || loading"
        @click="next"
      >
        <span v-if="loading" class="spinner" />
        <span v-else>Weiter → Karten wählen</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const newPlayer = ref('')
const players = ref([])
const adultType = ref('No Adult Dares')
const gameType = ref('Cardpile')
const error = ref(null)
const loading = ref(false)

const adultOptions = [
  { value: 'No Adult Dares', label: 'Nur U18', desc: 'Keine expliziten Inhalte' },
  { value: 'Misc Dares', label: 'Gemischt', desc: 'U18 und Ü18 Karten' },
  { value: 'Adult Dares Only', label: 'Nur Ü18', desc: 'Nur explizite Inhalte' },
]

function addPlayer() {
  const name = newPlayer.value.trim()
  if (name && !players.value.includes(name)) {
    players.value.push(name)
  }
  newPlayer.value = ''
}

function removePlayer(i) {
  players.value.splice(i, 1)
}

onMounted(async () => {
  if (!store.game || store.hash !== route.params.hash) {
    await store.loadGame(route.params.hash)
  }
})

async function next() {
  if (players.value.length < 2) return
  loading.value = true
  error.value = null
  try {
    await store.setup(adultType.value, gameType.value, players.value)
    router.push({ name: 'setup-cards', params: { hash: route.params.hash } })
  } catch (e) {
    error.value = 'Fehler beim Speichern. Bitte erneut versuchen.'
  } finally {
    loading.value = false
  }
}
</script>

