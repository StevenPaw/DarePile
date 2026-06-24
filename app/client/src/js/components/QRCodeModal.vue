<template>
  <div class="qr-modal-overlay" @click.self="$emit('close')">
    <div class="qr-box">
      <h3 class="qr-title">Karten per Handy hinzufügen</h3>
      <p class="qr-desc">Anderen Spielern diesen QR-Code zeigen, damit sie über ihr Handy Karten hinzufügen können.</p>

      <div class="qr-canvas-wrap">
        <canvas ref="canvasEl" />
      </div>

      <p class="qr-url">{{ addCardUrl }}</p>

      <button class="btn btn--ghost" @click="$emit('close')">Schließen</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  hash: { type: String, required: true },
})

defineEmits(['close'])

const canvasEl = ref(null)

const addCardUrl = computed(() => {
  const base = window.location.origin + window.location.pathname
  return `${base}#/add-card/${props.hash}`
})

onMounted(async () => {
  if (canvasEl.value) {
    await QRCode.toCanvas(canvasEl.value, addCardUrl.value, {
      width: 220,
      margin: 2,
      color: { dark: '#1a1a2e', light: '#f5edd6' },
    })
  }
})
</script>

