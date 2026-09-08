<script setup>
defineProps({
  title: { type: String, default: 'Nessun elemento' },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="card flex flex-col items-center gap-2 px-6 py-14 text-center">
    <p class="text-base font-semibold">{{ title }}</p>
    <p v-if="subtitle" class="max-w-sm text-sm text-zinc-500">{{ subtitle }}</p>
    <slot />
  </div>
</template>
