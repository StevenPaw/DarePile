<template>
  <div class="hand-player">
    <header class="hand-player__header">
      <img :src="DarePileLogo" alt="DarePile" class="hand-player__logo" />
      <span v-if="me" class="hand-player__me">{{ me.name }}</span>
      <button v-if="me" class="btn btn--ghost btn--sm" @click="showAddDare = true">
        + Aufgabe
      </button>
    </header>

    <!-- Not joined yet -->
    <form v-if="!token" class="hand-player__join" @submit.prevent="join">
      <h1 class="hand-player__title">Mitspielen</h1>
      <label class="field-label" for="join-name">Dein Name</label>
      <input
        id="join-name"
        v-model="name"
        class="input"
        type="text"
        maxlength="40"
        autocomplete="nickname"
        placeholder="Name eingeben …"
      >
      <button type="submit" class="btn btn--primary btn--lg" :disabled="!name.trim() || busy">
        <span v-if="busy" class="spinner" />
        <span v-else>Beitreten</span>
      </button>
      <p v-if="error" class="error-msg">{{ error }}</p>
    </form>

    <p v-else-if="!game" class="hand-player__status hand-player__status--center">Verbinde …</p>

    <template v-else>
      <!-- Status of the game; controls pop up over the bottom of this area -->
      <main class="hand-player__stage">
        <!-- Only this part scrolls; the stage clips, so the sliding panel adds no scrollbar -->
        <div class="hand-player__stage-content">
          <div class="hand-player__status-block">
            <template v-if="phase === 'Lobby'">
              <h2 class="hand-player__title">Du bist dabei!</h2>
              <p class="hand-player__status">Warte, bis das Spiel startet …</p>
            </template>
            <h2 v-else-if="phase === 'Rolling'" class="hand-player__title">Würfelrunde</h2>
            <h2 v-else-if="phase === 'Choosing' && !isGiver" class="hand-player__title">
              <strong>{{ giver?.name }}</strong> sucht eine Aufgabe aus …
            </h2>
            <h2 v-else-if="phase === 'Pending' && !isGiver && !isTarget" class="hand-player__title">
              <strong>{{ giver?.name }}</strong> hat <strong>{{ target?.name }}</strong> eine Aufgabe gestellt
            </h2>

            <p v-if="error" class="error-msg">{{ error }}</p>
          </div>

          <HandPlayerList
            class="hand-player__players"
            :players="players"
            :giver-id="hand.giverId"
            :target-id="hand.targetId"
            :show-dice="phase === 'Rolling'"
          />
        </div>

        <Transition name="sheet">
          <!-- Dice round -->
          <section v-if="sheet === 'roll'" key="roll" class="hand-sheet">
            <h3 class="hand-sheet__title">
              <template v-if="hand.rollsComplete && result.tie">
                Gleichstand! {{ joinNames(result.winners) }} würfeln nochmal.
              </template>
              <template v-else-if="hand.rollsComplete && result.winners[0]?.id === me?.id">
                Du hast die höchste Zahl!
              </template>
              <template v-else-if="hand.rollsComplete">
                <strong>{{ result.winners[0]?.name }}</strong> hat die höchste Zahl!
              </template>
              <template v-else-if="canRoll && isTieBreak">Gleichstand! Nochmal würfeln</template>
              <template v-else-if="canRoll">Tippe auf den Würfel!</template>
              <template v-else-if="myPlayer?.rollEligible">Warte auf die anderen …</template>
              <template v-else>Diesmal nicht – warte auf das Stechen …</template>
            </h3>
            <DiceRoller :value="shownRoll" :clickable="canRoll && !busy" @roll="roll" />
          </section>

          <!-- My turn: prompt to pick a card from the hand below -->
          <section v-else-if="sheet === 'choose'" key="choose" class="hand-sheet hand-sheet--alert">
            <h3 class="hand-sheet__title hand-sheet__title--big">Du darfst eine Aufgabe stellen!</h3>
            <p class="hand-sheet__lead">Wähle die nächste Aufgabe:</p>
            <p v-if="hand.failStreak > 0" class="hand-player__status">
              Versuch {{ hand.failStreak + 1 }} von {{ hand.maxFails }} – danach wird neu gewürfelt.
            </p>
            <span class="hand-sheet__pointer" aria-hidden="true">↓</span>
          </section>

          <!-- Card chosen: pick who has to do it -->
          <section v-else-if="sheet === 'target'" key="target" class="hand-sheet">
            <h3 class="hand-sheet__title">Wer soll diese Aufgabe machen?</h3>
            <div class="player-select">
              <button
                v-for="p in otherPlayers"
                :key="p.id"
                class="player-btn"
                :class="{ 'player-btn--active': selectedTargetId === p.id }"
                @click="selectedTargetId = p.id"
              >
                {{ p.name }} <span class="player-btn__score">✓ {{ p.completionCount }}</span>
              </button>
            </div>
            <div class="hand-sheet__actions">
              <button class="btn btn--back" @click="selectedCardId = null">Andere Karte</button>
              <button
                class="btn btn--next"
                :disabled="!selectedTargetId || busy"
                @click="assign"
              >
                <span v-if="busy" class="spinner" />
                <span v-else>Aufgabe stellen</span>
              </button>
            </div>
          </section>

          <!-- I gave the dare: confirm the outcome -->
          <section v-else-if="sheet === 'resolve'" key="resolve" class="hand-sheet">
            <h3 class="hand-sheet__title">Hat <strong>{{ target?.name }}</strong> die Aufgabe geschafft?</h3>
            <p v-if="hand.activeCard" class="hand-sheet__dare">
              <template v-for="(seg, i) in hand.activeCard.segments" :key="i">
                <mark v-if="seg.highlighted" class="dare-highlight">{{ seg.text }}</mark><!--
                --><span v-else>{{ seg.text }}</span>
              </template>
            </p>
            <div class="action-panel__buttons">
              <button class="btn btn--success btn--lg" :disabled="busy" @click="resolve('complete')">
                ✓ Ausgeführt
              </button>
              <button class="btn btn--danger btn--lg" :disabled="busy" @click="resolve('fail')">
                ✗ Nicht ausgeführt
              </button>
            </div>
          </section>

          <!-- I have to do the dare -->
          <section v-else-if="sheet === 'assigned'" key="assigned" class="hand-sheet hand-sheet--alert">
            <h3 class="hand-sheet__title hand-sheet__title--big">
              <strong>{{ giver?.name }}</strong> hat dir eine Aufgabe zugeteilt!
            </h3>
            <p class="hand-player__status">Schau auf den Hauptbildschirm.</p>
          </section>
        </Transition>
      </main>

      <!-- My hand: always at the bottom, swipe sideways -->
      <section v-if="phase !== 'Lobby'" class="hand-player__hand" aria-label="Deine Hand">
        <p class="hand-player__hand-label">
          Deine Hand ({{ myHand.length }})
          <span v-if="canChoose" class="hand-player__hand-tip">– tippe eine Karte an</span>
        </p>
        <div class="hand-strip">
          <HandCard
            v-for="card in myHand"
            :key="card.id"
            :card="card"
            :selectable="canChoose"
            :selected="selectedCardId === card.id"
            @select="toggleCard(card.id)"
          />
          <p v-if="!myHand.length" class="hand-strip__empty">Keine Karten auf der Hand</p>
        </div>
      </section>
    </template>

    <AddDareModal v-if="showAddDare" :hash="hash" @close="showAddDare = false" @added="onDareAdded" />
    <p v-if="hint" class="hint hand-player__hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { getGame, handAction } from '../api/game.js'
import { resolvePlaceholders } from '../utils/placeholders.js'
import { rollResult, joinNames } from '../utils/dice.js'
import DiceRoller from '../components/DiceRoller.vue'
import HandCard from '../components/HandCard.vue'
import HandPlayerList from '../components/HandPlayerList.vue'
import AddDareModal from '../components/AddDareModal.vue'
import DarePileLogo from '../../../icons/darepile_logo.svg'

const POLL_MS = 2000

const route = useRoute()
const hash = computed(() => route.params.hash)
const storageKey = computed(() => `darepile_player_${hash.value}`)

function loadToken() {
  try {
    return localStorage.getItem(storageKey.value)
  } catch {
    return null
  }
}

function saveToken(value) {
  try {
    if (value) localStorage.setItem(storageKey.value, value)
    else localStorage.removeItem(storageKey.value)
  } catch { /* storage unavailable: token lives only in memory */ }
}

const token = ref(loadToken())
const game = ref(null)
const name = ref('')
const busy = ref(false)
const error = ref(null)
const hint = ref(null)
const showAddDare = ref(false)
const selectedCardId = ref(null)
const selectedTargetId = ref(null)

const hand = computed(() => game.value?.hand ?? {})
const phase = computed(() => hand.value.phase)
const players = computed(() => game.value?.players ?? [])
const me = computed(() => game.value?.me ?? null)
const myHand = computed(() => me.value?.hand ?? [])
const myPlayer = computed(() => players.value.find((p) => p.id === me.value?.id) ?? null)
const otherPlayers = computed(() => players.value.filter((p) => p.id !== me.value?.id))
const giver = computed(() => players.value.find((p) => p.id === hand.value.giverId) ?? null)
const target = computed(() => players.value.find((p) => p.id === hand.value.targetId) ?? null)
const isGiver = computed(() => !!me.value && hand.value.giverId === me.value.id)
const isTarget = computed(() => !!me.value && hand.value.targetId === me.value.id)
const canRoll = computed(() => !!myPlayer.value?.rollEligible && !myPlayer.value?.diceRoll)
const canChoose = computed(() => phase.value === 'Choosing' && isGiver.value)
const result = computed(() => rollResult(players.value))
const isTieBreak = computed(() => players.value.some((p) => !p.rollEligible && p.diceRoll > 0))

// Keep showing my last roll until the next round starts
const shownRoll = computed(() => myPlayer.value?.diceRoll || 0)

// Which control pops up above the hand (null = none)
const sheet = computed(() => {
  if (phase.value === 'Rolling') return 'roll'
  if (canChoose.value) return selectedCardId.value ? 'target' : 'choose'
  if (phase.value === 'Pending' && isGiver.value) return 'resolve'
  if (phase.value === 'Pending' && isTarget.value) return 'assigned'
  return null
})

function showHint(msg) {
  hint.value = msg
  setTimeout(() => (hint.value = null), 2500)
}

function toggleCard(id) {
  selectedCardId.value = selectedCardId.value === id ? null : id
}

async function refresh() {
  if (!token.value) return
  try {
    const data = await getGame(hash.value, token.value)
    if (!data.me) {
      // Token unknown (e.g. removed by the host): join again
      token.value = null
      saveToken(null)
      game.value = null
      return
    }
    game.value = data
  } catch (e) {
    if (e.status === 404) {
      error.value = 'Dieses Spiel gibt es nicht mehr.'
    }
    // Network hiccups: keep the last state, the next poll retries
  }
}

async function act(action, body = {}) {
  busy.value = true
  error.value = null
  try {
    game.value = await handAction(hash.value, action, { token: token.value, ...body })
  } catch (e) {
    error.value = e.message
    await refresh()
  } finally {
    busy.value = false
  }
}

async function join() {
  busy.value = true
  error.value = null
  try {
    const result = await handAction(hash.value, 'join', { name: name.value.trim() })
    token.value = result.token
    saveToken(result.token)
    game.value = result.game
  } catch (e) {
    error.value = e.status === 404 ? 'Dieses Spiel gibt es nicht.' : 'Beitreten hat nicht geklappt.'
  } finally {
    busy.value = false
  }
}

const roll = () => act('roll')

async function assign() {
  const card = myHand.value.find((c) => c.id === selectedCardId.value)
  if (!card) return
  // Resolve placeholders once here, so every screen shows the same names and times
  const segments = resolvePlaceholders(card.dare, {
    players: players.value.filter((p) => p.id !== selectedTargetId.value),
  })
  await act('assign', { cardId: card.id, targetId: selectedTargetId.value, segments })
}

const resolve = (type) => act('resolve', { type })

function onDareAdded() {
  showAddDare.value = false
  showHint('Aufgabe zum Spiel hinzugefügt!')
}

// Reset the selection whenever a new choice starts
// Separate sources, so each value is compared on its own: a getter returning a new
// array would count as "changed" on every poll and wipe the selection
watch([phase, () => hand.value.giverId, () => hand.value.failStreak], () => {
  selectedCardId.value = null
  selectedTargetId.value = null
})

let pollInterval = null
onMounted(() => {
  refresh()
  pollInterval = setInterval(refresh, POLL_MS)
})
onUnmounted(() => clearInterval(pollInterval))
</script>
