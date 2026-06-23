<template>
  <div class="stats-view">
    <header class="stats-header">
      <button class="btn btn--ghost btn--sm" @click="router.back()">← Zurück</button>
      <h2 class="stats-header__title">Statistiken</h2>
      <span></span>
    </header>

    <div v-if="loading" class="loading-center">
      <span class="spinner spinner--lg" />
    </div>

    <div v-else class="stats-list">
      <div
        v-for="player in sortedPlayers"
        :key="player.id"
        class="player-stat"
      >
        <div class="player-stat__avatar">{{ player.name.charAt(0).toUpperCase() }}</div>
        <div class="player-stat__info">
          <span class="player-stat__name">{{ player.name }}</span>
          <div class="player-stat__bar-row">
            <div
              class="player-stat__bar player-stat__bar--success"
              :style="{ width: barWidth(player.completionCount, maxCount) }"
            />
          </div>
          <div class="player-stat__bar-row">
            <div
              class="player-stat__bar player-stat__bar--fail"
              :style="{ width: barWidth(player.failureCount, maxCount) }"
            />
          </div>
        </div>
        <div class="player-stat__numbers">
          <span class="stat-done">✓ {{ player.completionCount }}</span>
          <span class="stat-fail">✗ {{ player.failureCount }}</span>
          <span class="stat-rate">
            {{
              player.completionCount + player.failureCount > 0
                ? Math.round((player.completionCount / (player.completionCount + player.failureCount)) * 100)
                : 0
            }}%
          </span>
        </div>
      </div>

      <p v-if="!sortedPlayers.length" class="empty-state">
        Noch keine Spieler oder Ergebnisse vorhanden.
      </p>
    </div>

    <div class="stats-footer">
      <button class="btn btn--secondary" @click="router.push({ name: 'game', params: { hash: route.params.hash } })">
        Weiter spielen
      </button>
      <button class="btn btn--ghost" @click="router.push({ name: 'home' })">
        Neues Spiel
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const loading = computed(() => store.loading)

const sortedPlayers = computed(() =>
  [...store.players].sort(
    (a, b) => b.completionCount - a.completionCount
  )
)

const maxCount = computed(() =>
  Math.max(1, ...store.players.map((p) => p.completionCount + p.failureCount))
)

function barWidth(val, max) {
  return `${Math.round((val / max) * 100)}%`
}

onMounted(async () => {
  if (!store.game || store.hash !== route.params.hash) {
    await store.loadGame(route.params.hash)
  }
})
</script>

