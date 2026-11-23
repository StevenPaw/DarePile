<template>
  <ul class="hand-players">
    <li
      v-for="p in players"
      :key="p.id"
      class="hand-players__item"
      :class="{
        'hand-players__item--giver': p.id === giverId,
        'hand-players__item--target': p.id === targetId,
      }"
    >
      <span class="hand-players__name">{{ p.name }}</span>
      <DiceRoller v-if="showDice && p.diceRoll" :value="p.diceRoll" size="sm" />
      <span v-else-if="showDice && p.rollEligible" class="hand-players__waiting">würfelt …</span>
      <span class="hand-players__score" :title="`${p.completionCount} Aufgaben erledigt`">
        ✓ {{ p.completionCount }}
      </span>
    </li>
  </ul>
</template>

<script setup>
import DiceRoller from './DiceRoller.vue'

defineProps({
  players: { type: Array, required: true },
  giverId: { type: Number, default: null },
  targetId: { type: Number, default: null },
  showDice: { type: Boolean, default: false },
})
</script>
