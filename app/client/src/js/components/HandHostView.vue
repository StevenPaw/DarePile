<template>
  <div class="game-view hand-host">
    <div class="game-topbar">
      <img :src="DarePileLogo" alt="DarePile" class="game-topbar__logo" />
      <router-link :to="{ name: 'legal' }" class="game-topbar__legal">Impressum & Datenschutz</router-link>
    </div>

    <header class="game-header">
      <button class="btn btn--ghost btn--sm" title="Statistiken" aria-label="Statistiken" @click="router.push({ name: 'stats', params: { hash } })">
        <span class="icon" v-html="StatisticsIcon" />
      </button>
      <span class="remaining-count">
        <template v-if="hand.poolCount !== null">{{ hand.poolCount }} Karten im Nachziehstapel</template>
      </span>
      <div class="game-header__actions">
        <button class="btn btn--ghost btn--sm" title="Karten bearbeiten" aria-label="Karten bearbeiten" @click="router.push({ name: 'setup-cards', params: { hash }, query: { edit: 'true' } })">
          <span class="icon" v-html="SettingsIcon" />
        </button>
        <button class="btn btn--ghost btn--sm" title="Beitreten per QR-Code" aria-label="Beitreten per QR-Code" @click="showQr = true">
          <span class="icon" v-html="QrIcon" />
        </button>
      </div>
    </header>

    <main class="hand-host__stage">
      <!-- Before the deal: choose where the first hands come from -->
      <section v-if="phase === 'Lobby'" class="hand-host__panel">
        <h2 class="hand-host__title">Karten austeilen</h2>
        <p class="hand-host__text">
          Jede Person bekommt {{ handSize }} Karten auf die Hand.
          Wähle unten den Level-Bereich, aus dem die ersten Karten kommen.
        </p>
        <p v-if="cardsInRange < neededForDeal" class="hand-host__warn">
          Im Bereich {{ store.levelMin }}–{{ store.levelMax }} gibt es nur {{ cardsInRange }} Karten,
          für volle Hände bräuchtet ihr {{ neededForDeal }}.
        </p>
        <button class="btn btn--primary btn--lg" :disabled="busy || players.length < 2" @click="deal">
          <span v-if="busy" class="spinner" />
          <span v-else>Karten austeilen</span>
        </button>
      </section>

      <!-- Dice round -->
      <section v-else-if="phase === 'Rolling'" class="hand-host__panel">
        <h2 v-if="hand.rollsComplete && result.tie" class="hand-host__title">
          Gleichstand! {{ joinNames(result.winners) }} würfeln nochmal.
        </h2>
        <h2 v-else-if="hand.rollsComplete" class="hand-host__title">
          <strong>{{ result.winners[0]?.name }}</strong> hat die höchste Zahl!
        </h2>
        <h2 v-else class="hand-host__title">Würfelt auf euren Handys!</h2>
        <p v-if="!hand.rollsComplete" class="hand-host__text">
          <template v-if="isTieBreak">Gleichstand! Die Höchsten würfeln nochmal.</template>
          <template v-else>Die höchste Zahl darf die nächste Aufgabe stellen.</template>
        </p>
        <button v-if="!hand.rollsComplete" class="btn btn--ghost btn--sm" :disabled="busy" @click="rollMissing">
          Für alle würfeln, die noch fehlen
        </button>
      </section>

      <!-- Giver is choosing -->
      <section v-else-if="phase === 'Choosing'" class="hand-host__panel">
        <h2 class="hand-host__title"><strong>{{ giver?.name }}</strong> sucht eine Aufgabe aus …</h2>
        <p v-if="hand.failStreak > 0" class="hand-host__text">
          Versuch {{ hand.failStreak + 1 }} von {{ hand.maxFails }} – danach wird neu gewürfelt.
        </p>
      </section>

      <!-- Active dare, large for everybody -->
      <section v-else-if="phase === 'Pending' && hand.activeCard" class="hand-host__panel">
        <p class="hand-host__assignment">
          <strong>{{ giver?.name }}</strong> hat <strong>{{ target?.name }}</strong> diese Aufgabe gestellt:
        </p>
        <div class="hand-host__card">
          <p class="hand-host__dare">
            <template v-for="(seg, i) in hand.activeCard.segments" :key="i">
              <mark v-if="seg.highlighted" class="dare-highlight">{{ seg.text }}</mark><!--
              --><span v-else>{{ seg.text }}</span>
            </template>
          </p>
          <span v-if="hand.activeCard.adultsOnly" class="drawn-card__badge">18+</span>
        </div>
        <p class="hand-host__text">{{ giver?.name }} bestätigt auf dem Handy, ob es geklappt hat.</p>
      </section>

      <!-- Pool is empty -->
      <section v-if="phase !== 'Lobby' && hand.poolCount === 0" class="hand-host__notice">
        <p>
          Der Nachziehstapel ist leer. Ihr könnt die gespielten Karten neu mischen
          oder unten den Level-Bereich ändern.
        </p>
        <button class="btn btn--secondary btn--sm" :disabled="busy" @click="reshuffle">
          Gespielte Karten neu mischen
        </button>
      </section>

      <HandPlayerList
        :players="players"
        :giver-id="hand.giverId"
        :target-id="hand.targetId"
        :show-dice="phase === 'Rolling'"
      />

      <p v-if="phase === 'Lobby' && players.length < 2" class="hand-host__text">
        Es fehlen noch Mitspielende – über den QR-Code oben kann man beitreten.
      </p>
    </main>

    <p v-if="error" class="error-msg">{{ error }}</p>

    <QRCodeModal v-if="showQr" :hash="hash" mode="join" @close="showQr = false" />

    <LevelRangeSlider />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore.js'
import HandPlayerList from './HandPlayerList.vue'
import { rollResult, joinNames } from '../utils/dice.js'
import QRCodeModal from './QRCodeModal.vue'
import LevelRangeSlider from './LevelRangeSlider.vue'
import DarePileLogo from '../../../icons/darepile_logo.svg'
import StatisticsIcon from '../../../icons/action_statistics.svg?raw'
import SettingsIcon from '../../../icons/action_settings.svg?raw'
import QrIcon from '../../../icons/action_qr.svg?raw'

const POLL_MS = 2000

const props = defineProps({
  hash: { type: String, required: true },
})

const router = useRouter()
const store = useGameStore()

const hand = computed(() => store.game?.hand ?? {})
const phase = computed(() => hand.value.phase)
const players = computed(() => store.players)
const handSize = computed(() => store.game?.handSize ?? 4)
const giver = computed(() => players.value.find((p) => p.id === hand.value.giverId) ?? null)
const target = computed(() => players.value.find((p) => p.id === hand.value.targetId) ?? null)

// Second dice round after a tie: someone is already out with a roll shown
const result = computed(() => rollResult(players.value))
const isTieBreak = computed(() => players.value.some((p) => !p.rollEligible && p.diceRoll > 0))

const cardsInRange = computed(() =>
  store.cards.filter((c) => c.level >= store.levelMin && c.level <= store.levelMax).length
)
const neededForDeal = computed(() => players.value.length * handSize.value)

const showQr = ref(false)
const busy = ref(false)
const error = ref(null)

async function run(action, body = {}) {
  busy.value = true
  error.value = null
  try {
    await store.handAction(action, body)
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

const deal = () => run('deal', { min: store.levelMin, max: store.levelMax })
const rollMissing = () => run('roll-missing')
const reshuffle = () => run('reshuffle')

// The level slider sets the refill range once the cards are dealt
let rangeTimer = null
watch(() => [store.levelMin, store.levelMax], ([min, max]) => {
  if (phase.value === 'Lobby') return
  clearTimeout(rangeTimer)
  rangeTimer = setTimeout(() => run('range', { min, max }), 400)
})

let pollInterval = null
onMounted(() => {
  pollInterval = setInterval(() => store.refreshGame(props.hash, { full: true }), POLL_MS)
})
onUnmounted(() => {
  clearInterval(pollInterval)
  clearTimeout(rangeTimer)
})
</script>
