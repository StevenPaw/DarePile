<template>
  <div class="level-range">
    <div class="level-range__header">
      <span class="level-range__label">Schwierigkeit</span>
      <span class="level-range__values">{{ store.levelMin }} – {{ store.levelMax }}</span>
    </div>
    <div class="level-range__slider-wrap">
      <div class="level-range__track" ref="trackEl" />
      <div
        class="level-range__fill"
        :class="{ 'level-range__fill--dragging': isDragging }"
        :style="fillStyle"
        @pointerdown="onFillPointerDown"
      />
      <input
        type="range"
        class="level-range__input"
        min="1" max="10" step="1"
        :value="store.levelMin"
        @input="onMinInput"
      />
      <input
        type="range"
        class="level-range__input level-range__input--max"
        min="1" max="10" step="1"
        :value="store.levelMax"
        @input="onMaxInput"
      />
    </div>
    <div class="level-range__ticks" aria-hidden="true">
      <span
        v-for="n in 10"
        :key="n"
        class="level-range__tick"
        :class="{ 'level-range__tick--active': n >= store.levelMin && n <= store.levelMax }"
      >{{ n }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useGameStore } from '../stores/gameStore.js'

const store = useGameStore()
const trackEl = ref(null)
const isDragging = ref(false)

// Not reactive — only needed during an active drag
let dragState = null

const fillStyle = computed(() => {
    const left = ((store.levelMin - 1) / 9) * 100
    const width = ((store.levelMax - store.levelMin) / 9) * 100
    return { left: left + '%', width: width + '%' }
})

function onMinInput(e) {
    store.levelMin = Math.min(parseInt(e.target.value), store.levelMax)
}

function onMaxInput(e) {
    store.levelMax = Math.max(parseInt(e.target.value), store.levelMin)
}

function onFillPointerDown(e) {
    e.preventDefault()
    e.stopPropagation()

    isDragging.value = true
    dragState = {
        startX:     e.clientX,
        minAtStart: store.levelMin,
        maxAtStart: store.levelMax,
        trackWidth: trackEl.value.getBoundingClientRect().width,
    }

    window.addEventListener('pointermove', onFillPointerMove)
    window.addEventListener('pointerup', onFillPointerUp, { once: true })
}

function onFillPointerMove(e) {
    if (!dragState) return

    const deltaX     = e.clientX - dragState.startX
    const pixPerStep = dragState.trackWidth / 9          // 9 gaps between levels 1–10
    const deltaSteps = Math.round(deltaX / pixPerStep)

    const rangeSize = dragState.maxAtStart - dragState.minAtStart
    let newMin = dragState.minAtStart + deltaSteps
    let newMax = dragState.maxAtStart + deltaSteps

    // Clamp so the range never leaves 1–10
    if (newMin < 1)  { newMin = 1;            newMax = 1 + rangeSize }
    if (newMax > 10) { newMax = 10;           newMin = 10 - rangeSize }

    store.levelMin = newMin
    store.levelMax = newMax
}

function onFillPointerUp() {
    isDragging.value = false
    dragState = null
    window.removeEventListener('pointermove', onFillPointerMove)
}
</script>
