<template>
  <div class="setup-view setup-view--cards">
    <header class="setup-header">
      <button v-if="isEdit" class="btn btn--ghost btn--sm setup-header__back" @click="router.push({ name: 'game', params: { hash: route.params.hash } })">
        ← Zurück
      </button>
      <h2 class="setup-header__title">{{ isEdit ? 'Spiel' : 'Schritt 2 / 2' }}</h2>
      <p class="setup-header__sub">{{ isEdit ? 'Karten bearbeiten' : 'Karten auswählen' }}</p>
    </header>

    <div class="card-count-bar">
      <span :class="['count-badge', selectedIds.size >= 10 ? 'count-badge--ok' : 'count-badge--warn']">
        {{ selectedIds.size }} / mind. 10 Karten
      </span>
      <div class="card-count-bar__actions">
        <button class="btn btn--ghost btn--sm" :disabled="loadingCards" @click="toggleAll">
          <span v-if="loadingAll" class="spinner" />
          <span v-else>{{ allSelected ? 'Alle abwählen' : 'Alle auswählen' }}</span>
        </button>
        <button class="btn btn--ghost btn--sm" @click="showQr = true"><span class="icon" v-html="QrIcon" /> QR-Code</button>
      </div>
    </div>

    <div class="search-row">
      <input
        v-model="search"
        class="input"
        type="search"
        placeholder="Karten durchsuchen …"
        @input="debouncedSearch"
      >
    </div>

    <div class="cards-list" ref="listEl" @scroll="onScroll">
      <template v-for="item in groupedCards" :key="item.type === 'header' ? `h-${item.level}` : item.card.id">
        <div v-if="item.type === 'header'" class="level-group-header">
          <span class="level-group-header__label">Level {{ item.level }}</span>
          <span class="level-group-header__dot" :style="{ background: levelColor(item.level) }" />
        </div>
        <label
          v-else
          class="card-row"
          :class="{ 'card-row--selected': selectedIds.has(item.card.id) }"
        >
          <input
            type="checkbox"
            :checked="selectedIds.has(item.card.id)"
            @change="toggleCard(item.card.id)"
          >
          <span class="card-row__dare">{{ item.card.dare }}</span>
          <span v-if="item.card.adultsOnly" class="badge badge--adult">18+</span>
        </label>
      </template>

      <div v-if="loadingCards" class="list-loading">
        <span class="spinner" /> Lade Karten…
      </div>

      <p v-if="!loadingCards && officialCards.length === 0" class="list-empty">
        Keine Karten gefunden.
      </p>
    </div>

    <div class="custom-card-add">
      <button class="btn btn--secondary btn--sm" @click="showCustom = !showCustom">
        + Eigene Karte
      </button>
      <div v-if="showCustom" class="custom-card-form">
        <input
          v-model="customDare"
          class="input"
          type="text"
          placeholder="Aufgabe eingeben …"
          maxlength="500"
          @keyup.enter="addCustom"
        >
        <button class="btn btn--primary btn--sm" :disabled="!customDare.trim()" @click="addCustom">
          Hinzufügen
        </button>
      </div>
      <div v-if="customCards.length" class="custom-cards-preview">
        <div v-for="(c, i) in customCards" :key="i" class="chip">
          {{ c.length > 50 ? c.slice(0, 50) + '…' : c }}
          <button class="chip__remove" @click="customCards.splice(i, 1)">×</button>
        </div>
      </div>
    </div>

    <div class="setup-footer">
      <p v-if="error" class="error-msg">{{ error }}</p>
      <button
        class="btn btn--primary btn--lg"
        :disabled="selectedIds.size + customCards.length < 10 || saving"
        @click="startGame"
      >
        <span v-if="saving" class="spinner" />
        <span v-else>{{ isEdit ? 'Speichern' : 'Spiel starten 🎮' }}</span>
      </button>
    </div>

    <QRCodeModal v-if="showQr" :hash="route.params.hash" @close="showQr = false" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import { fetchCards } from '../api/cards.js'
import QRCodeModal from '../components/QRCodeModal.vue'
import QrIcon from '../../../icons/action_qr.svg?raw'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const officialCards = ref([])
const selectedIds = ref(new Set())
const customCards = ref([])
const customDare = ref('')
const showCustom = ref(false)
const showQr = ref(false)
const search = ref('')
const loadingCards = ref(false)
const loadingAll = ref(false)
const saving = ref(false)
const error = ref(null)
const page = ref(1)
const hasMore = ref(true)
const listEl = ref(null)

const isEdit = computed(() => route.query.edit === 'true')

const allSelected = computed(() =>
    officialCards.value.length > 0 && officialCards.value.every((c) => selectedIds.value.has(c.id))
)

const groupedCards = computed(() => {
    const items = []
    let currentLevel = null
    for (const card of officialCards.value) {
        if (card.level !== currentLevel) {
            items.push({ type: 'header', level: card.level })
            currentLevel = card.level
        }
        items.push({ type: 'card', card })
    }
    return items
})

function levelColor(level) {
    if (level <= 3) return 'var(--color-success)'
    if (level <= 6) return 'var(--color-warn)'
    return 'var(--color-accent)'
}

let debounceTimer = null

function debouncedSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    officialCards.value = []
    page.value = 1
    hasMore.value = true
    loadMore()
  }, 300)
}

async function loadMore() {
  if (loadingCards.value || !hasMore.value) return
  loadingCards.value = true
  try {
    const adult = store.game?.adultType === 'Adult Dares Only'
      ? true
      : store.game?.adultType === 'No Adult Dares'
        ? false
        : null
    const res = await fetchCards({ search: search.value, adult, page: page.value, limit: 30 })
    officialCards.value.push(...res.cards)
    hasMore.value = officialCards.value.length < res.total
    page.value++
  } catch (e) {
    error.value = 'Karten konnten nicht geladen werden.'
  } finally {
    loadingCards.value = false
  }
}

async function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
    return
  }
  if (hasMore.value) {
    loadingAll.value = true
    try {
      while (hasMore.value) {
        await loadMore()
      }
    } finally {
      loadingAll.value = false
    }
  }
  selectedIds.value = new Set(officialCards.value.map((c) => c.id))
}

function onScroll() {
  if (!listEl.value) return
  const { scrollTop, scrollHeight, clientHeight } = listEl.value
  if (scrollHeight - scrollTop - clientHeight < 100) loadMore()
}

function toggleCard(id) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

function addCustom() {
  const dare = customDare.value.trim()
  if (dare) {
    customCards.value.push(dare)
    customDare.value = ''
    showCustom.value = false
  }
}

async function startGame() {
  saving.value = true
  error.value = null
  try {
    await store.saveCards([...selectedIds.value], customCards.value)
    router.push({ name: 'game', params: { hash: route.params.hash } })
  } catch (e) {
    error.value = 'Fehler beim Speichern der Karten.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!store.game || store.hash !== route.params.hash) {
    await store.loadGame(route.params.hash)
  }
  if (isEdit.value && store.game) {
    const gameCards = store.game.cards ?? []
    selectedIds.value = new Set(
      gameCards.filter((c) => c.official).map((c) => c.id)
    )
    customCards.value = gameCards
      .filter((c) => !c.official)
      .map((c) => c.dare)
  }
  loadMore()
})
</script>

