<template>
  <div class="pile-wrapper" @click="!finished && $emit('draw')">
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
        <p class="pile__label">Tippen zum Aufdecken</p>
      </div>
      <div v-else key="empty" class="pile pile--empty">
        <span class="pile__empty-icon">🎴</span>
      </div>
    </transition>
  </div>
</template>

<script setup>
defineProps({
  count: { type: Number, default: 0 },
  finished: { type: Boolean, default: false },
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

