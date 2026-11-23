<template>
  <div class="qr-code">
    <component
      :is="copyable ? 'button' : 'div'"
      :type="copyable ? 'button' : undefined"
      class="qr-canvas-wrap"
      :class="{ 'qr-canvas-wrap--copyable': copyable }"
      :aria-label="copyable ? 'Link kopieren' : undefined"
      @click="copyable && copy()"
    >
      <canvas ref="canvasEl" />
    </component>
    <p v-if="copyable" class="qr-code__hint" aria-live="polite">
      {{ copied ? 'Link kopiert!' : copyFailed ? 'Kopieren nicht möglich' : 'Antippen, um den Link zu kopieren' }}
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  url: { type: String, required: true },
  size: { type: Number, default: 220 },
  // Click copies the URL to the clipboard
  copyable: { type: Boolean, default: false },
})

const canvasEl = ref(null)
const copied = ref(false)
const copyFailed = ref(false)
let resetTimer = null

async function render() {
  if (!canvasEl.value) return
  await QRCode.toCanvas(canvasEl.value, props.url, {
    width: props.size,
    margin: 2,
    color: { dark: '#1a1a2e', light: '#f5edd6' },
  })
}

// Fallback for browsers without the async clipboard API (or non-HTTPS)
function copyLegacy(text) {
  const area = document.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.opacity = '0'
  document.body.appendChild(area)
  area.select()
  const ok = document.execCommand('copy')
  document.body.removeChild(area)
  return ok
}

async function copy() {
  let ok = false
  try {
    await navigator.clipboard.writeText(props.url)
    ok = true
  } catch {
    try {
      ok = copyLegacy(props.url)
    } catch {
      ok = false
    }
  }
  copied.value = ok
  copyFailed.value = !ok
  clearTimeout(resetTimer)
  resetTimer = setTimeout(() => {
    copied.value = false
    copyFailed.value = false
  }, 2000)
}

onMounted(render)
onUnmounted(() => clearTimeout(resetTimer))
watch(() => props.url, render)
</script>
