<template>
  <button
    type="button"
    class="dice"
    :class="[`dice--${size}`, { 'dice--clickable': clickable, 'dice--flying': flying }]"
    :disabled="!clickable"
    :aria-label="value ? `Gewürfelt: ${value}` : 'Würfeln'"
    @click="clickable && $emit('roll')"
  >
    <div class="dice__cube" :style="cubeStyle">
      <div v-for="face in FACES" :key="face.value" class="dice__face" :class="`dice__face--${face.side}`">
        <span v-for="(pip, i) in PIPS[face.value]" :key="i" class="dice__pip" :style="{ gridArea: pip }" />
      </div>
    </div>
  </button>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  // Rolled value 1–6, or 0/null while not rolled
  value: { type: Number, default: 0 },
  clickable: { type: Boolean, default: false },
  size: { type: String, default: 'lg' }, // 'lg' | 'sm'
})

defineEmits(['roll'])

// Which side of the cube shows which number (opposite sides add up to 7)
const FACES = [
  { value: 1, side: 'front' },
  { value: 6, side: 'back' },
  { value: 3, side: 'right' },
  { value: 4, side: 'left' },
  { value: 5, side: 'top' },
  { value: 2, side: 'bottom' },
]

// Cube rotation that brings each number to the front
const ORIENTATION = {
  1: [0, 0],
  6: [0, 180],
  3: [0, -90],
  4: [0, 90],
  5: [-90, 0],
  2: [90, 0],
}

// Pip positions in a 3×3 grid (row / column)
const PIPS = {
  1: ['2 / 2'],
  2: ['1 / 1', '3 / 3'],
  3: ['1 / 1', '2 / 2', '3 / 3'],
  4: ['1 / 1', '1 / 3', '3 / 1', '3 / 3'],
  5: ['1 / 1', '1 / 3', '2 / 2', '3 / 1', '3 / 3'],
  6: ['1 / 1', '1 / 3', '2 / 1', '2 / 3', '3 / 1', '3 / 3'],
}

// Full extra turns added on each roll so the die visibly tumbles
const spins = ref(0)
const flying = ref(false)
let flyTimer = null

const cubeStyle = computed(() => {
  const [x, y] = ORIENTATION[props.value] ?? [-25, 35]
  const turns = spins.value * 720
  return { transform: `rotateX(${x + turns}deg) rotateY(${y + turns}deg)` }
})

watch(() => props.value, (value, old) => {
  if (!value || value === old) return
  spins.value++
  flying.value = true
  clearTimeout(flyTimer)
  flyTimer = setTimeout(() => (flying.value = false), 1200)
})
</script>
