<template>
  <button
    type="button"
    class="hand-card"
    :class="{ 'hand-card--selected': selected, 'hand-card--selectable': selectable }"
    :disabled="!selectable"
    :aria-pressed="selectable ? selected : undefined"
    @click="selectable && $emit('select')"
  >
    <span class="hand-card__level">Level {{ card.level }}</span>
    <span class="hand-card__dare">
      <template v-for="(seg, i) in preview" :key="i">
        <mark v-if="seg.highlighted" class="dare-highlight">{{ seg.text }}</mark><!--
        --><span v-else>{{ seg.text }}</span>
      </template>
    </span>
    <span v-if="card.adultsOnly" class="hand-card__badge">18+</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { resolvePlaceholders } from '../utils/placeholders.js'

const props = defineProps({
  card: { type: Object, required: true },
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
})

defineEmits(['select'])

// Placeholders stay "???" until the dare is assigned to someone
const preview = computed(() => resolvePlaceholders(props.card.dare, {}, { preview: true }))
</script>
