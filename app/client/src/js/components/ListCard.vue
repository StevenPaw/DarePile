<template>
  <div
    class="list-card"
    :class="{ 'list-card--on': selected }"
    role="button"
    :aria-pressed="selected"
    @click="$emit('toggle')"
  >
    <div class="list-card__body">
      <span class="list-card__dare">{{ dare }}</span>
    </div>
    <div class="list-card__meta">
      <span v-if="adultsOnly" class="badge badge--adult">18+</span>
      <span v-if="isCustom" class="badge badge--custom">Eigene</span>
      <span class="list-card__level">Lvl {{ level }}</span>
      <span class="list-card__indicator" :class="{ 'list-card__indicator--on': selected }" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  dare:       { type: String,  required: true },
  level:      { type: Number,  default: 1 },
  adultsOnly: { type: Boolean, default: false },
  isCustom:   { type: Boolean, default: false },
  selected:   { type: Boolean, default: false },
})
defineEmits(['toggle'])
</script>
