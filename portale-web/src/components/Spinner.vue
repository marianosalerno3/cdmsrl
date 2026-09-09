<script setup>
defineProps({ label: { type: String, default: '' } })
</script>

<template>
  <div class="flex items-center justify-center gap-3 py-12 text-sm text-zinc-500">
    <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
    </svg>
    <span v-if="label">{{ label }}</span>
  </div>
</template>
