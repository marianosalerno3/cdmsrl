<script setup>
import { onMounted, computed } from 'vue'
import { useCustomer } from '@/stores/customer'

const { state, selected, load, select } = useCustomer()

const options = computed(() => state.list)

onMounted(() => load())

function onChange(e) {
  select(e.target.value || null)
}
</script>

<template>
  <div>
    <label class="label">Cliente</label>
    <select class="field" :value="selected?.id ?? ''" :disabled="state.loading" @change="onChange">
      <option value="">— Seleziona un cliente —</option>
      <option v-for="c in options" :key="c.id" :value="c.id">
        {{ c.denominazione }}<template v-if="c.partita_iva"> · {{ c.partita_iva }}</template>
      </option>
    </select>
    <p v-if="selected" class="mt-1 text-xs text-zinc-500">
      Listino <span class="font-semibold">{{ selected.listino }}</span>
      <template v-if="selected.contrassegno_abilitato"> · contrassegno abilitato</template>
    </p>
  </div>
</template>
