<script setup>
import { onMounted, computed, watch } from 'vue'
import { useCustomer } from '@/stores/customer'
import { useAuth } from '@/stores/auth'
import { listinoLabel } from '@/lib/format'

const { state, selected, load, select } = useCustomer()
const { isCliente } = useAuth()

const options = computed(() => state.list)

// Un cliente B2B ordina sempre per sé: /agent/customers restituisce solo lui e lo selezioniamo in automatico.
function autoSelect() {
  if (isCliente.value && state.list.length && String(state.list[0].id) !== String(state.selectedId)) {
    select(state.list[0].id)
  }
}

onMounted(async () => {
  await load()
  autoSelect()
})
watch(() => state.list, autoSelect)

function onChange(e) {
  select(e.target.value || null)
}
</script>

<template>
  <div v-if="!isCliente">
    <label class="label">Cliente</label>
    <select class="field" :value="selected?.id ?? ''" :disabled="state.loading" @change="onChange">
      <option value="">— Seleziona un cliente —</option>
      <option v-for="c in options" :key="c.id" :value="c.id">
        {{ c.denominazione }}<template v-if="c.partita_iva"> · {{ c.partita_iva }}</template>
      </option>
    </select>
    <p v-if="selected" class="mt-1 text-xs text-zinc-500">
      Listino <span class="font-semibold">{{ listinoLabel(selected.listino) }}</span>
      <template v-if="selected.contrassegno_abilitato"> · contrassegno abilitato</template>
    </p>
  </div>
</template>
