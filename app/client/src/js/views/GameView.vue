<template>
  <div class="game-view">
    <header class="game-header">
      <button class="btn btn--ghost btn--sm" @click="router.push({ name: 'stats', params: { hash } })" title="Statistiken" aria-label="Statistiken">
        <span class="icon" v-html="StatisticsIcon" />
      </button>
      <span class="remaining-count">
        {{ availableCards.length }} Karten übrig
      </span>
      <div class="game-header__actions">
        <button class="btn btn--ghost btn--sm" :disabled="reshuffling" @click="onReshuffle" title="Stapel neu mischen" aria-label="Stapel neu mischen">
          <span class="icon" v-html="ShuffleIcon" />
        </button>
        <button class="btn btn--ghost btn--sm" @click="router.push({ name: 'setup-cards', params: { hash }, query: { edit: 'true' } })" title="Karten bearbeiten" aria-label="Karten bearbeiten">
          <span class="icon" v-html="SettingsIcon" />
        </button>
        <button class="btn btn--ghost btn--sm" @click="showAddCard = true" title="Karte hinzufügen" aria-label="Karte hinzufügen">
          <span class="icon" v-html="AddIcon" />
        </button>
      </div>
    </header>

    <div class="game-area" @click.self="skipCard">
      <CardPile
        :count="availableCards.length"
        :finished="availableCards.length === 0"
        @draw="onDraw"
      />

      <div v-if="availableCards.length === 0 && !drawnCard" class="all-done">
        <template v-if="noCardsInRange">
          <p>Keine Karten in Level {{ store.levelMin }}–{{ store.levelMax }}</p>
          <p class="all-done__sub">Passe den Bereich unten an.</p>
        </template>
        <template v-else>
          <p><span class="icon" v-html="ConfettiIcon" /> Alle Karten im Bereich gespielt!</p>
          <p v-if="unusedBelow || unusedAbove" class="all-done__sub">
            Noch
            <template v-if="unusedBelow">{{ unusedBelow }} {{ unusedBelow === 1 ? 'Karte' : 'Karten' }} unter Level {{ store.levelMin }}</template>
            <template v-if="unusedBelow && unusedAbove"> und </template>
            <template v-if="unusedAbove">{{ unusedAbove }} {{ unusedAbove === 1 ? 'Karte' : 'Karten' }} über Level {{ store.levelMax }}</template>
            verfügbar.
          </p>
          <div class="section_buttons">
            <button class="btn btn--secondary" style="margin-top:1rem" :disabled="reshuffling" @click="onReshuffle">
                <span v-if="reshuffling" class="spinner" /> Neu mischen
            </button>
            <button class="btn btn--primary" style="margin-top:1rem" @click="router.push({ name: 'stats', params: { hash } })">
                Statistiken ansehen
            </button>
          </div>
        </template>
      </div>
    </div>

    <p v-if="hint" class="hint">{{ hint }}</p>

    <DrawnCardModal
      v-if="drawnCard"
      :card="drawnCard"
      :players="players"
      @complete="onComplete"
      @fail="onFail"
      @return-card="onReturn"
      @skip="onSkip"
    />

    <div v-if="showAddCard" class="modal-overlay" @click.self="showAddCard = false">
      <div class="modal-box">
        <h3 class="modal-title">Karte hinzufügen</h3>
        <textarea
          v-model="newDare"
          class="input textarea"
          placeholder="Aufgabe eingeben …"
          rows="3"
          maxlength="500"
        />
        <div class="modal-actions">
          <button class="btn btn--ghost" @click="showAddCard = false">Abbrechen</button>
          <button class="btn btn--primary" :disabled="!newDare.trim() || addingCard" @click="submitNewCard">
            <span v-if="addingCard" class="spinner" />
            <span v-else>Hinzufügen</span>
          </button>
        </div>
        <hr class="divider">
        <p class="modal-qr-hint">Oder per QR-Code von anderen hinzufügen lassen:</p>
        <button class="btn btn--secondary btn--sm" @click="showQr = true; showAddCard = false">
          <span class="icon" v-html="QrIcon" /> QR-Code anzeigen
        </button>
      </div>
    </div>

    <QRCodeModal v-if="showQr" :hash="hash" @close="showQr = false" />

    <LevelRangeSlider />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import CardPile from '../components/CardPile.vue'
import DrawnCardModal from '../components/DrawnCardModal.vue'
import QRCodeModal from '../components/QRCodeModal.vue'
import LevelRangeSlider from '../components/LevelRangeSlider.vue'
import StatisticsIcon from '../../../icons/action_statistics.svg?raw'
import ShuffleIcon from '../../../icons/action_shuffle.svg?raw'
import SettingsIcon from '../../../icons/action_settings.svg?raw'
import AddIcon from '../../../icons/action_add.svg?raw'
import QrIcon from '../../../icons/action_qr.svg?raw'
import ConfettiIcon from '../../../icons/confetti.svg?raw'

const route = useRoute()
const router = useRouter()
const store = useGameStore()

const hash = computed(() => route.params.hash)
const players = computed(() => store.players)
const availableCards = computed(() => store.availableCards)

const noCardsInRange = computed(() =>
    store.cards.filter(
        (c) => c.level >= store.levelMin && c.level <= store.levelMax
    ).length === 0
)

const unusedBelow = computed(() =>
    store.cards.filter(
        (c) => !store.usedCardIds.includes(c.id) && c.level < store.levelMin
    ).length
)

const unusedAbove = computed(() =>
    store.cards.filter(
        (c) => !store.usedCardIds.includes(c.id) && c.level > store.levelMax
    ).length
)

const drawnCard = ref(null)
const showAddCard = ref(false)
const showQr = ref(false)
const newDare = ref('')
const addingCard = ref(false)
const reshuffling = ref(false)
const hint = ref(null)

function showHint(msg, timeout = 2000) {
  hint.value = msg
  setTimeout(() => (hint.value = null), timeout)
}

function onDraw() {
  if (availableCards.value.length === 0) return
  const idx = Math.floor(Math.random() * availableCards.value.length)
  drawnCard.value = availableCards.value[idx]
  store.draw(drawnCard.value.id)
}

function skipCard() {
  if (!drawnCard.value) return
  drawnCard.value = null
  showHint('Karte übersprungen — ohne Wertung.')
}

async function onComplete(playerId) {
  await store.doAction(drawnCard.value.id, playerId, 'complete')
  drawnCard.value = null
}

async function onFail(playerId) {
  await store.doAction(drawnCard.value.id, playerId, 'fail')
  drawnCard.value = null
}

async function onReturn() {
  await store.doAction(drawnCard.value.id, null, 'return')
  drawnCard.value = null
  showHint('Karte zurück in den Stapel gemischt.')
}

function onSkip() {
  drawnCard.value = null
  showHint('Karte übersprungen — ohne Wertung.')
}

async function onReshuffle() {
  reshuffling.value = true
  try {
    await store.resetDeck()
    showHint('Stapel neu gemischt — alle Karten sind wieder im Spiel!')
  } catch {
    showHint('Fehler beim Neu-Mischen.')
  } finally {
    reshuffling.value = false
  }
}

async function submitNewCard() {
  const dare = newDare.value.trim()
  if (!dare) return
  addingCard.value = true
  try {
    await store.addCard(dare)
    newDare.value = ''
    showAddCard.value = false
    showHint('Karte hinzugefügt!')
  } catch {
    showHint('Fehler beim Hinzufügen.')
  } finally {
    addingCard.value = false
  }
}

onMounted(async () => {
  if (!store.game || store.hash !== hash.value) {
    await store.loadGame(hash.value)
  }
})
</script>

