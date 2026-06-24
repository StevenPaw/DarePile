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
        v-if="savedHash"
        class="btn btn--secondary btn--lg"
        @click="resumeGame"
      >
        Spiel fortsetzen
      </button>
    </div>

    <p v-if="error" class="error-msg">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import DarePileLogo from '../../../icons/darepile_logo.svg'

const router = useRouter()
const store = useGameStore()
const savedHash = ref(null)
const loading = ref(false)
const error = ref(null)

onMounted(() => {
  savedHash.value = localStorage.getItem('darepile_hash')
})

async function startGame() {
  loading.value = true
  error.value = null
  try {
    const hash = await store.createGame()
    router.push({ name: 'setup-players', params: { hash } })
  } catch (e) {
    error.value = 'Spiel konnte nicht erstellt werden. Bitte prüfe die Verbindung.'
  } finally {
    loading.value = false
  }
}

async function resumeGame() {
  router.push({ name: 'game', params: { hash: savedHash.value } })
}
</script>

