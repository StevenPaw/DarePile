<template>
  <HandHostView v-if="store.gameType === 'Hand'" :hash="hash" />

  <div v-else class="game-view">
    <div class="game-topbar">
      <img :src="DarePileLogo" alt="DarePile" class="game-topbar__logo" />
      <router-link :to="{ name: 'legal' }" class="game-topbar__legal">Impressum & Datenschutz</router-link>
    </div>

    <header class="game-header">
      <button class="btn btn--ghost btn--sm" @click="router.push({ name: 'stats', params: { hash } })" title="Statistiken" aria-label="Statistiken">
        <span class="icon" v-html="StatisticsIcon" />
      </button>
      <span class="remaining-count">
        <template v-if="isTruthOrDare">{{ availableTruths.length }} Wahrheit · {{ availableDares.length }} Pflicht</template>
        <template v-else>{{ availableCards.length }} Karten übrig</template>
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
      <p v-if="turnPlayer && availableCards.length > 0" class="turn-banner">
        <template v-if="isTruthOrDare"><strong>{{ turnPlayer.name }}</strong>, Wahrheit oder Pflicht?</template>
        <template v-else><strong>{{ turnPlayer.name }}</strong>, ziehe deine Karte</template>
      </p>

      <div v-if="isTruthOrDare && availableCards.length > 0" class="pile-duo">
        <CardPile
          variant="truth"
          title="Wahrheit"
          :label="availableTruths.length ? 'Frage ziehen' : 'Keine Fragen mehr'"
          :count="availableTruths.length"
          :finished="availableTruths.length === 0"
          @draw="onDraw('Truth')"
        />
        <CardPile
          variant="dare"
          title="Pflicht"
          :label="availableDares.length ? 'Aufgabe ziehen' : 'Keine Aufgaben mehr'"
          :count="availableDares.length"
          :finished="availableDares.length === 0"
          @draw="onDraw('Dare')"
        />
      </div>

      <CardPile
        v-else
        :count="availableCards.length"
        :finished="availableCards.length === 0"
        @draw="onDraw()"
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
      :fixed-player="turnPlayer"
      :show-type="isTruthOrDare"
      @complete="onComplete"
      @fail="onFail"
      @return-card="onReturn"
      @skip="onSkip"
    />

    <div v-if="showAddCard" class="modal-overlay modal-overlay--top" @click.self="showAddCard = false">
      <div class="modal-box">
        <h3 class="modal-title">Karte hinzufügen</h3>
        <div v-if="isTruthOrDare" class="type-toggle" role="group" aria-label="Kartentyp">
          <button
            v-for="opt in TYPE_OPTIONS"
            :key="opt.value"
            type="button"
            class="type-toggle__btn"
            :class="{ 'type-toggle__btn--active': newType === opt.value }"
            @click="newType = opt.value"
          >{{ opt.label }}</button>
        </div>
        <textarea
          v-model="newDare"
          class="input textarea"
          :placeholder="newType === 'Truth' ? 'Frage eingeben …' : 'Aufgabe eingeben …'"
          rows="3"
          maxlength="500"
        />
        <div class="add-card-options">
          <div class="add-card-field">
            <label class="field-label" for="card-level">Level</label>
            <select id="card-level" v-model="newLevel" class="input input--select" aria-label="Level auswählen">
              <option v-for="l in 10" :key="l" :value="l">Level {{ l }}</option>
            </select>
          </div>
          <div class="add-card-field add-card-field--toggle">
            <label class="toggle-label">
              <input type="checkbox" v-model="newAdultsOnly" class="toggle-input" aria-label="FSK 18" />
              <span class="toggle-track"><span class="toggle-thumb" /></span>
              <span class="toggle-text">FSK 18</span>
            </label>
          </div>
        </div>
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
import HandHostView from '../components/HandHostView.vue'
import DarePileLogo from '../../../icons/darepile_logo.svg'
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
// "Wahrheit oder Pflicht": two piles, the player picks one
const isTruthOrDare = computed(() => store.isTruthOrDare)
const availableTruths = computed(() => store.availableTruths)
const availableDares = computed(() => store.availableDares)
// "Turns" / "TruthOrDare" mode: whose turn it is (null in "Cardpile" mode)
const turnPlayer = computed(() => store.currentPlayer)

const TYPE_OPTIONS = [
  { value: 'Dare', label: 'Pflicht' },
  { value: 'Truth', label: 'Wahrheit' },
]

const noCardsInRange = computed(() =>
    store.playableCards.filter(
        (c) => c.level >= store.levelMin && c.level <= store.levelMax
    ).length === 0
)

const unusedBelow = computed(() =>
    store.playableCards.filter(
        (c) => !store.usedCardIds.includes(c.id) && c.level < store.levelMin
    ).length
)

const unusedAbove = computed(() =>
    store.playableCards.filter(
        (c) => !store.usedCardIds.includes(c.id) && c.level > store.levelMax
    ).length
)

const drawnCard = ref(null)
const showAddCard = ref(false)
const showQr = ref(false)
const newDare = ref('')
const newLevel = ref(5)
const newAdultsOnly = ref(false)
const newType = ref('Dare')
const addingCard = ref(false)
const reshuffling = ref(false)
const hint = ref(null)

function showHint(msg, timeout = 2000) {
  hint.value = msg
  setTimeout(() => (hint.value = null), timeout)
}

// type: 'Truth' / 'Dare' draws from that pile ("Wahrheit oder Pflicht"), otherwise from all cards
function onDraw(type = null) {
  const pile = type === 'Truth'
    ? availableTruths.value
    : type === 'Dare' ? availableDares.value : availableCards.value
  if (pile.length === 0) return
  const idx = Math.floor(Math.random() * pile.length)
  drawnCard.value = pile[idx]
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
    await store.addCard(dare, newLevel.value, newAdultsOnly.value, isTruthOrDare.value ? newType.value : 'Dare')
    newDare.value = ''
    newLevel.value = 5
    newAdultsOnly.value = false
    newType.value = 'Dare'
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
