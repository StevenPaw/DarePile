<template>
  <div class="card-overlay" @click.self="$emit('skip')">

    <div v-if="flipped" class="player-select-bar">
      <p class="action-panel__label">Wer macht's?</p>
      <div class="player-select">
        <button
          v-for="p in players"
          :key="p.id"
          class="player-btn"
          :class="{ 'player-btn--active': selectedPlayer === p.id }"
          @click="selectedPlayer = p.id"
        >
          {{ p.name }}
        </button>
      </div>
    </div>

    <div class="drawn-card" :class="{ 'drawn-card--flipped': flipped }">
      <div class="drawn-card__inner">
        <div class="drawn-card__back" />
        <div class="drawn-card__front">
          <div class="drawn-card__dare">
            <template v-for="(seg, i) in resolvedDare" :key="i">
              <mark v-if="seg.highlighted" class="dare-highlight">{{ seg.text }}</mark><!--
              --><span v-else>{{ seg.text }}</span>
            </template>
          </div>
          <div v-if="card.adultsOnly" class="drawn-card__badge">18+</div>
        </div>
      </div>
    </div>

    <div v-if="flipped" class="action-panel">
      <div class="action-panel__buttons">
        <button
          class="btn btn--success btn--lg"
          :disabled="!selectedPlayer"
          @click="$emit('complete', selectedPlayer)"
        >
          ✓ Ausgeführt
        </button>
        <button
          class="btn btn--danger btn--lg"
          :disabled="!selectedPlayer"
          @click="$emit('fail', selectedPlayer)"
        >
          ✗ Nicht ausgeführt
        </button>
      </div>

      <div class="action-panel__secondary">
        <button class="btn btn--ghost btn--sm" @click="$emit('return-card')">
          ↩ Zurück in Stapel
        </button>
        <button class="btn btn--ghost btn--sm" @click="$emit('skip')">
          Überspringen
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { resolvePlaceholders } from '../utils/placeholders.js'

const props = defineProps({
  card: { type: Object, required: true },
  players: { type: Array, default: () => [] },
})

defineEmits(['complete', 'fail', 'return-card', 'skip'])

const flipped = ref(false)
const selectedPlayer = ref(null)
const resolvedDare = ref([])

function resolveCard(excludePlayerId = null) {
  const context = {
    players: excludePlayerId
      ? props.players.filter((p) => p.id !== excludePlayerId)
      : props.players,
  }
  const preview = excludePlayerId === null
  resolvedDare.value = resolvePlaceholders(props.card.dare, context, { preview })
}

watch(selectedPlayer, (playerId) => resolveCard(playerId))

onMounted(() => {
  resolveCard()
  setTimeout(() => { flipped.value = true }, 50)
})
</script>

