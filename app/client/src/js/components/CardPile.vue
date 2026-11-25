<template>
  <div class="pile-wrapper" :class="variant && `pile-wrapper--${variant}`" @click="!finished && $emit('draw')">
    <p v-if="title" class="pile__title">{{ title }}</p>
    <transition name="card-pop" mode="out-in">
      <div v-if="!finished" key="pile" class="pile">
        <div
          v-for="n in Math.min(count, 5)"
          :key="n"
          class="pile__card"
          :style="cardStyle(n)"
        >
          <div class="pile__card-pattern" />
        </div>
        <p class="pile__label">{{ label }}</p>
      </div>
      <div v-else key="empty" class="pile pile--empty">
        <span class="pile__empty-icon icon" v-html="PlayingCardsIcon" />
        <p v-if="title" class="pile__label">{{ label }}</p>
      </div>
    </transition>
  </div>
</template>

<script setup>
import PlayingCardsIcon from '../../../icons/playing_cards.svg?raw'

defineProps({
  count: { type: Number, default: 0 },
  finished: { type: Boolean, default: false },
  label: { type: String, default: 'Tippen zum Aufdecken' },
  // "Wahrheit oder Pflicht": heading above the pile and colour ('truth' / 'dare')
  title: { type: String, default: '' },
  variant: { type: String, default: '' },
})
defineEmits(['draw'])

function cardStyle(n) {
  const rotations = [-4, 2, -1, 3, -2]
  const tx = [-5, 4, -2, 6, -3]
  const ty = [-4, 2, -1, 3, -2]
  const i = n - 1
  return {
    transform: `rotate(${rotations[i % rotations.length]}deg) translate(${tx[i % tx.length]}px, ${ty[i % ty.length]}px)`,
    zIndex: n,
  }
}
</script>

