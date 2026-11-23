<template>
  <div class="setup-view">
    <header class="setup-header">
      <h2 class="setup-header__title">Schritt 2 / 3</h2>
      <p class="setup-header__sub">Spieler</p>
    </header>

    <!-- "Handkarten": players join on their own phones -->
    <section v-if="isHand" class="setup-section">
      <label class="field-label">Mit dem Handy beitreten</label>
      <p class="setup-hint">Alle Mitspielenden scannen den QR-Code und geben ihren Namen ein.</p>
      <div class="join-qr">
        <QRCode :url="joinLink" copyable />
      </div>

      <label class="field-label" for="hand-size">Karten auf der Hand</label>
      <div class="stepper">
        <button
          class="stepper__btn"
          :disabled="handSize <= MIN_HAND"
          aria-label="Weniger Karten"
          @click="handSize--"
        >−</button>
        <output id="hand-size" class="stepper__value">{{ handSize }}</output>
        <button
          class="stepper__btn"
          :disabled="handSize >= MAX_HAND"
          aria-label="Mehr Karten"
          @click="handSize++"
        >+</button>
      </div>

      <label class="field-label">Beigetreten ({{ joinedPlayers.length }})</label>
      <div class="player-chips">
        <div v-for="p in joinedPlayers" :key="p.id" class="chip">
          {{ p.name }}
          <button class="chip__remove" aria-label="Spieler entfernen" @click="removeJoined(p)">×</button>
        </div>
        <p v-if="!joinedPlayers.length" class="setup-hint">Noch niemand da …</p>
      </div>
    </section>

    <!-- "Kartenstapel" and "Reihum": names are entered here -->
    <section v-else class="setup-section">
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
        <button class="btn btn--icon" :disabled="!newPlayer.trim()" aria-label="Spieler hinzufügen" @click="addPlayer">+</button>
      </div>

      <div v-if="!isTurns" class="player-chips">
        <div v-for="(p, i) in players" :key="p" class="chip">
          {{ p }}
          <button class="chip__remove" aria-label="Spieler entfernen" @click="removePlayer(i)">×</button>
        </div>
      </div>

      <template v-else>
        <p v-if="players.length" class="setup-hint">In dieser Reihenfolge wird gezogen:</p>
        <ol class="player-order">
          <li v-for="(p, i) in players" :key="p" class="player-order__item">
            <span class="player-order__pos">{{ i + 1 }}.</span>
            <span class="player-order__name">{{ p }}</span>
            <button
              class="player-order__btn"
              :disabled="i === 0"
              aria-label="Nach oben"
              @click="movePlayer(i, -1)"
            >↑</button>
            <button
              class="player-order__btn"
              :disabled="i === players.length - 1"
              aria-label="Nach unten"
              @click="movePlayer(i, 1)"
            >↓</button>
            <button class="player-order__btn" aria-label="Spieler entfernen" @click="removePlayer(i)">×</button>
          </li>
        </ol>
        <button
          v-if="players.length > 1"
          class="btn btn--ghost btn--sm"
          @click="shufflePlayers"
        >
          Reihenfolge zufällig mischen
        </button>
      </template>
    </section>

    <div class="setup-footer">
      <p v-if="error" class="error-msg">{{ error }}</p>
      <p v-if="playerCount < 2" class="setup-hint">Mindestens 2 Spieler, um weiterzumachen.</p>
      <SetupNav
        :disabled="playerCount < 2"
        :loading="loading"
        @back="back"
        @next="next"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import QRCode from '../components/QRCode.vue'
import SetupNav from '../components/SetupNav.vue'
import { joinUrl } from '../utils/links.js'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const hash = computed(() => route.params.hash)
const isHand = computed(() => store.gameType === 'Hand')
const isTurns = computed(() => store.gameType === 'Turns')
const joinLink = computed(() => joinUrl(hash.value))

// Names entered on this device ("Kartenstapel" / "Reihum")
const newPlayer = ref('')
const players = ref([])
// Players who joined with their own phone ("Handkarten")
const joinedPlayers = computed(() => store.players)

// "Handkarten": cards per hand
const MIN_HAND = 2
const MAX_HAND = 6
const handSize = ref(4)

const playerCount = computed(() => (isHand.value ? joinedPlayers.value.length : players.value.length))

const error = ref(null)
const loading = ref(false)
let pollInterval = null

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

function movePlayer(i, direction) {
  const target = i + direction
  if (target < 0 || target >= players.value.length) return
  const list = players.value
  ;[list[i], list[target]] = [list[target], list[i]]
}

function shufflePlayers() {
  const list = players.value
  for (let i = list.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[list[i], list[j]] = [list[j], list[i]]
  }
}

async function removeJoined(player) {
  if (!confirm(`${player.name} aus dem Spiel entfernen?`)) return
  try {
    await store.handAction('remove', { playerId: player.id })
  } catch {
    error.value = 'Spieler konnte nicht entfernt werden.'
  }
}

onMounted(async () => {
  if (!store.game || store.hash !== hash.value) {
    await store.loadGame(hash.value)
  }
  // Coming back to an existing game: keep its players
  if (!isHand.value && store.players.length) {
    players.value = store.players.map((p) => p.name)
  }
  if (isHand.value) {
    handSize.value = store.game?.handSize ?? handSize.value
    pollInterval = setInterval(() => store.refreshGame(hash.value, { full: true }), 2000)
  }
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})

// Keep entered names when going back to the settings
async function back() {
  try {
    if (isHand.value) {
      await store.setup({ handSize: handSize.value })
    } else if (players.value.length) {
      await store.setup({ players: players.value })
    }
  } catch { /* not critical: entries just are not kept */ }
  router.push({ name: 'setup-settings', params: { hash: hash.value } })
}

async function next() {
  if (playerCount.value < 2) return
  loading.value = true
  error.value = null
  try {
    if (isHand.value) {
      await store.setup({ handSize: handSize.value })
    } else {
      await store.setup({ players: players.value })
    }
    router.push({ name: 'setup-cards', params: { hash: hash.value } })
  } catch {
    error.value = 'Fehler beim Speichern. Bitte erneut versuchen.'
  } finally {
    loading.value = false
  }
}
</script>
