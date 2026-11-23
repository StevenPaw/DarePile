<template>
  <div class="setup-nav">
    <button type="button" class="btn btn--back btn--lg" @click="$emit('back')">
      ← Zurück
    </button>
    <button
      type="button"
      class="btn btn--next btn--lg"
      :disabled="disabled || loading"
      @click="$emit('next')"
    >
      <span v-if="loading" class="spinner" />
      <span v-else>{{ nextLabel }}</span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  nextLabel: { type: String, default: 'Weiter' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

defineEmits(['back', 'next'])
</script>
