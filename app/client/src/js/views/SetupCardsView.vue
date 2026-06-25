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
      <span
        class="count-badge"
        :class="{ 'count-badge--ok': totalSelected >= 10 }"
        :style="totalSelected < 10 ? { '--fill': (totalSelected / 10 * 100) + '%' } : {}"
      >{{ totalSelected }} / mind. 10 Karten</span>
      <div class="card-count-bar__actions">
        <button class="btn btn--ghost btn--sm" :disabled="loadingCards" @click="toggleAll">
          <span v-if="loadingAll" class="spinner" />
          <span v-else>{{ allSelected ? 'Alle abwählen' : 'Alle auswählen' }}</span>
        </button>
        <button class="btn btn--ghost btn--sm" @click="showQr = true">
          <span class="icon" v-html="QrIcon" /> QR-Code
        </button>
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

    <!-- Alle Karten in einer gemeinsamen Liste, nach Level sortiert -->
    <div class="cards-list" ref="listEl" @scroll="onScroll">
      <template v-for="item in groupedCards" :key="item.type === 'header' ? `h-${item.level}` : item.card._key">
        <div v-if="item.type === 'header'" class="level-group-header">
          <span class="level-group-header__label">Level {{ item.level }}</span>
          <span class="level-group-header__dot" :style="{ background: levelColor(item.level) }" />
        </div>
        <ListCard
          v-else
          :dare="item.card.dare"
          :level="item.card.level"
          :adults-only="item.card.adultsOnly"
          :is-custom="item.card.isCustom"
          :selected="getSelected(item.card)"
          @toggle="handleToggle(item.card)"
        />
      </template>

      <div v-if="loadingCards" class="list-loading">
        <span class="spinner" /> Lade Karten…
      </div>
      <p v-if="!loadingCards && officialCards.length === 0 && allCustomCards.length === 0" class="list-empty">
        Keine Karten gefunden.
      </p>
    </div>

    <!-- Eigene Karte hinzufügen -->
    <div class="custom-card-add">
      <button class="btn btn--secondary btn--sm" @click="showCustom = !showCustom">
        + Eigene Karte
      </button>
      <div v-if="showCustom" class="custom-card-form-expanded">
        <input
          v-model="customDare"
          class="input"
          type="text"
          placeholder="Aufgabe eingeben …"
          maxlength="500"
          @keyup.enter="addCustom"
        >
        <div class="add-card-options">
          <div class="add-card-field">
            <label class="field-label" for="setup-card-level">Level</label>
            <select id="setup-card-level" v-model="customLevel" class="input input--select" aria-label="Level auswählen">
              <option v-for="l in 10" :key="l" :value="l">Level {{ l }}</option>
            </select>
          </div>
          <div class="add-card-field add-card-field--toggle">
            <label class="toggle-label">
              <input type="checkbox" v-model="customAdultsOnly" class="toggle-input" aria-label="FSK 18" />
              <span class="toggle-track"><span class="toggle-thumb" /></span>
              <span class="toggle-text">FSK 18</span>
            </label>
          </div>
        </div>
        <button class="btn btn--primary btn--sm" :disabled="!customDare.trim()" @click="addCustom">
          Hinzufügen
        </button>
      </div>
    </div>

    <div class="setup-footer">
      <p v-if="error" class="error-msg">{{ error }}</p>
      <button
        class="btn btn--primary btn--lg"
        :disabled="totalSelected < 10 || saving"
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import { fetchCards } from '../api/cards.js'
import ListCard from '../components/ListCard.vue'
import QRCodeModal from '../components/QRCodeModal.vue'
import QrIcon from '../../../icons/action_qr.svg?raw'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const officialCards = ref([])
const selectedIds = ref(new Set())
// { dare, level, adultsOnly, selected }
const customCards = ref([])
const deselectedCustomIds = ref(new Set())
const customDare = ref('')
const customLevel = ref(5)
const customAdultsOnly = ref(false)
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
const polling = ref(false)

let pollInterval = null

const isEdit = computed(() => route.query.edit === 'true')

const savedCustomCards = computed(() =>
  (store.game?.cards ?? []).filter((c) => !c.official)
)

// Alle eigenen Karten als einheitliches Format für die Liste
const allCustomCards = computed(() => [
  ...savedCustomCards.value.map((c) => ({
    ...c,
    _key: `saved-${c.id}`,
    isCustom: true,
    isLocal: false,
  })),
  ...customCards.value.map((c, i) => ({
    ...c,
    id: `local-${i}`,
    _key: `local-${i}`,
    isCustom: true,
    isLocal: true,
    localIndex: i,
  })),
])

const activeCustomCount = computed(() =>
  savedCustomCards.value.filter((c) => !deselectedCustomIds.value.has(c.id)).length +
  customCards.value.filter((c) => c.selected).length
)

const totalSelected = computed(() =>
  selectedIds.value.size + activeCustomCount.value
)

const allSelected = computed(() =>
  officialCards.value.length > 0 &&
  officialCards.value.every((c) => selectedIds.value.has(c.id)) &&
  savedCustomCards.value.every((c) => !deselectedCustomIds.value.has(c.id)) &&
  customCards.value.every((c) => c.selected)
)

const groupedCards = computed(() => {
  const merged = [
    ...officialCards.value.map((c) => ({ ...c, _key: `off-${c.id}`, isCustom: false })),
    ...allCustomCards.value,
  ].sort((a, b) => a.level - b.level || (a.isCustom ? 1 : 0) - (b.isCustom ? 1 : 0))

  const items = []
  let currentLevel = null
  for (const card of merged) {
    if (card.level !== currentLevel) {
      items.push({ type: 'header', level: card.level })
      currentLevel = card.level
    }
    items.push({ type: 'card', card })
  }
  return items
})

function getSelected(card) {
  if (!card.isCustom) return selectedIds.value.has(card.id)
  if (card.isLocal) return customCards.value[card.localIndex]?.selected ?? true
  return !deselectedCustomIds.value.has(card.id)
}

function handleToggle(card) {
  if (!card.isCustom) return toggleCard(card.id)
  if (card.isLocal) {
    customCards.value[card.localIndex].selected = !customCards.value[card.localIndex].selected
    return
  }
  toggleSavedCustom(card.id)
}

function levelColor(level) {
  if (level <= 3) return 'var(--color-success)'
  if (level <= 6) return 'var(--color-warn)'
  return 'var(--color-accent)'
}

function toggleCard(id) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

function toggleSavedCustom(id) {
  if (deselectedCustomIds.value.has(id)) {
    deselectedCustomIds.value.delete(id)
  } else {
    deselectedCustomIds.value.add(id)
  }
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
  } catch {
    error.value = 'Karten konnten nicht geladen werden.'
  } finally {
    loadingCards.value = false
  }
}

async function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
    deselectedCustomIds.value = new Set(savedCustomCards.value.map((c) => c.id))
    customCards.value.forEach((c) => { c.selected = false })
    return
  }
  if (hasMore.value) {
    loadingAll.value = true
    try {
      while (hasMore.value) await loadMore()
    } finally {
      loadingAll.value = false
    }
  }
  selectedIds.value = new Set(officialCards.value.map((c) => c.id))
  deselectedCustomIds.value = new Set()
  customCards.value.forEach((c) => { c.selected = true })
}

function onScroll() {
  if (!listEl.value) return
  const { scrollTop, scrollHeight, clientHeight } = listEl.value
  if (scrollHeight - scrollTop - clientHeight < 100) loadMore()
}

function addCustom() {
  const dare = customDare.value.trim()
  if (!dare) return
  customCards.value.push({ dare, level: customLevel.value, adultsOnly: customAdultsOnly.value, selected: true })
  customDare.value = ''
  customLevel.value = 5
  customAdultsOnly.value = false
  showCustom.value = false
}

async function startGame() {
  saving.value = true
  error.value = null
  try {
    const allCustom = [
      ...savedCustomCards.value
        .filter((c) => !deselectedCustomIds.value.has(c.id))
        .map(({ dare, level, adultsOnly }) => ({ dare, level, adultsOnly })),
      ...customCards.value
        .filter((c) => c.selected)
        .map(({ dare, level, adultsOnly }) => ({ dare, level, adultsOnly })),
    ]
    await store.saveCards([...selectedIds.value], allCustom)
    router.push({ name: 'game', params: { hash: route.params.hash } })
  } catch {
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
    customCards.value = []
    deselectedCustomIds.value = new Set()
  }

  polling.value = true
  pollInterval = setInterval(() => store.refreshGame(route.params.hash), 5000)

  loadMore()
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
})
</script>
