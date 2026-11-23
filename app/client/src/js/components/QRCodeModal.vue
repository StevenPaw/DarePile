<template>
  <div class="qr-modal-overlay" @click.self="$emit('close')">
    <div class="qr-box">
      <h3 class="qr-title">{{ texts.title }}</h3>
      <p class="qr-desc">{{ texts.desc }}</p>

      <QRCode :url="url" :copyable="mode === 'join'" />

      <p class="qr-url">{{ url }}</p>

      <button class="btn btn--ghost" @click="$emit('close')">Schließen</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import QRCode from './QRCode.vue'
import { addCardUrl, joinUrl } from '../utils/links.js'

const props = defineProps({
  hash: { type: String, required: true },
  // 'add-card': others add dares; 'join': players join a "Hand" mode game
  mode: { type: String, default: 'add-card' },
})

defineEmits(['close'])

const url = computed(() => (props.mode === 'join' ? joinUrl(props.hash) : addCardUrl(props.hash)))

const texts = computed(() => (props.mode === 'join'
  ? {
      title: 'Dem Spiel beitreten',
      desc: 'Scannt den QR-Code mit eurem Handy, um mitzuspielen.',
    }
  : {
      title: 'Karten per Handy hinzufügen',
      desc: 'Anderen Spielern diesen QR-Code zeigen, damit sie über ihr Handy Karten hinzufügen können.',
    }))
</script>
