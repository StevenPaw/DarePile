<template>
  <div class="home">
    <div class="home__logo">
      <img :src="DarePileLogo" alt="DarePile Logo" class="home__logo-icon" />
      <h1 class="home__title">DarePile</h1>
      <p class="home__subtitle">Das Mutproben-Kartenspiel</p>
    </div>

    <div class="home__actions">
      <button class="btn btn--primary btn--lg" :disabled="loading" @click="startGame">
        <span v-if="loading" class="spinner" />
        <span v-else>Neues Spiel starten</span>
      </button>

      <button
        v-if="resumeTarget"
        class="btn btn--secondary btn--lg"
        @click="resumeGame"
      >
        Spiel fortsetzen
      </button>

      <button
        class="btn btn--secondary btn--lg"
        @click="router.push({ name: 'card-overview' })"
      >
        Alle Karten ansehen
      </button>
    </div>

    <p v-if="error" class="error-msg">{{ error }}</p>

    <router-link :to="{ name: 'legal' }" class="home__legal-link">
      Impressum & Datenschutz
    </router-link>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import { getGame } from '../api/game.js'
import DarePileLogo from '../../../icons/darepile_logo.svg'

const router = useRouter()
const store = useGameStore()
const savedHash = ref(null)
// Route name to resume into, or null if there is nothing to resume
const resumeTarget = ref(null)
const loading = ref(false)
const error = ref(null)

onMounted(async () => {
  const hash = localStorage.getItem('darepile_hash')
  if (!hash) return

  try {
    const game = await getGame(hash)
    savedHash.value = hash
    if (!game.players?.length) {
      resumeTarget.value = 'setup-settings'
    } else if (!game.cards?.length) {
      resumeTarget.value = 'setup-cards'
    } else {
      resumeTarget.value = 'game'
    }
  } catch (e) {
    // Game no longer exists on the server: forget it.
    // On network errors keep the hash so it can be resumed once online again.
    if (e.status === 404) store.clearGame()
  }
})

async function startGame() {
  loading.value = true
  error.value = null
  try {
    const hash = await store.createGame()
    router.push({ name: 'setup-settings', params: { hash } })
  } catch (e) {
    error.value = 'Spiel konnte nicht erstellt werden. Bitte prüfe die Verbindung.'
  } finally {
    loading.value = false
  }
}

async function resumeGame() {
  router.push({ name: resumeTarget.value, params: { hash: savedHash.value } })
}
</script>
