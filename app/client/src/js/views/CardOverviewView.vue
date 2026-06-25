<template>
  <div class="card-overview">
    <header class="setup-header">
      <button class="btn btn--ghost btn--sm setup-header__back" @click="router.push({ name: 'home' })">
        ← Zurück
      </button>
      <h2 class="setup-header__title">Kartenübersicht</h2>
      <p class="setup-header__sub">Alle offiziellen Aufgaben</p>
    </header>

    <div class="overview-filter" role="group" aria-label="Altersfilter">
      <button
        v-for="opt in filterOptions"
        :key="opt.value"
        :class="['overview-filter__btn', { 'overview-filter__btn--active': filter === opt.value }]"
        @click="filter = opt.value"
      >
        {{ opt.label }}
      </button>
    </div>

    <div v-if="loading" class="overview-loading">
      <span class="spinner spinner--lg" />
    </div>

    <div v-else class="overview-content">
      <template v-for="group in groupedCards" :key="group.level">
        <div class="level-group-header overview-level-header">
          <span class="level-group-header__dot" :style="{ background: levelColor(group.level) }" />
          <span class="level-group-header__label">Level {{ group.level }}</span>
          <span class="overview-level-count">{{ group.cards.length }} Karten</span>
        </div>
        <div class="card-grid">
          <div
            v-for="card in group.cards"
            :key="card.id"
            class="card-tile"
          >
            <p class="card-tile__dare" v-html="highlightDare(card.dare)" />
            <span v-if="card.adultsOnly" class="badge badge--adult">18+</span>
          </div>
        </div>
      </template>

      <p v-if="!loading && groupedCards.length === 0" class="overview-empty">
        Keine Karten für diesen Filter gefunden.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchCards } from '../api/cards.js'

const router = useRouter()
const cards = ref([])
const loading = ref(false)
const filter = ref('all')

const filterOptions = [
  { value: 'all',  label: 'Alle' },
  { value: 'u18',  label: 'U18' },
  { value: 'ue18', label: 'Ü18' },
]

const filteredCards = computed(() => {
  if (filter.value === 'u18')  return cards.value.filter((c) => !c.adultsOnly)
  if (filter.value === 'ue18') return cards.value.filter((c) => c.adultsOnly)
  return cards.value
})

const groupedCards = computed(() => {
  const groups = {}
  for (const card of filteredCards.value) {
    if (!groups[card.level]) groups[card.level] = { level: card.level, cards: [] }
    groups[card.level].cards.push(card)
  }
  return Object.values(groups).sort((a, b) => a.level - b.level)
})

function escapeHtml(text) {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function highlightDare(text) {
  return escapeHtml(text).replace(
    /\[[^\]]+\]/g,
    (m) => `<mark class="placeholder">${m}</mark>`
  )
}

function levelColor(level) {
  if (level <= 3) return 'var(--color-success)'
  if (level <= 6) return 'var(--color-warn)'
  return 'var(--color-accent)'
}

async function loadAll() {
  loading.value = true
  try {
    let pg = 1
    let hasMore = true
    while (hasMore) {
      const res = await fetchCards({ page: pg, limit: 100 })
      cards.value.push(...res.cards)
      hasMore = cards.value.length < res.total
      pg++
    }
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)
</script>
