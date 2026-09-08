<script setup>
import { reactive, computed } from 'vue'
import { money } from '@/lib/format'

const props = defineProps({
  product: { type: Object, required: true },
  variants: { type: Array, required: true }, // [{id, sku, taglia, colore, quantita, prezzo}]
})
const emit = defineEmits(['add'])

// quantità digitate: { [varianteId]: number }
const qty = reactive({})

const taglie = computed(() => uniq(props.variants.map((v) => v.taglia).filter(Boolean)))
const colori = computed(() => uniq(props.variants.map((v) => v.colore).filter(Boolean)))

function uniq(arr) {
  return [...new Set(arr)]
}

function variantOf(colore, taglia) {
  return props.variants.find((v) => v.colore === colore && v.taglia === taglia) || null
}

const selezione = computed(() =>
  Object.entries(qty)
    .map(([id, q]) => ({ id, q: Number(q) || 0 }))
    .filter((x) => x.q > 0),
)

const totalePezzi = computed(() => selezione.value.reduce((s, x) => s + x.q, 0))
const totaleImporto = computed(() =>
  selezione.value.reduce((s, x) => {
    const v = props.variants.find((vv) => String(vv.id) === String(x.id))
    return s + (v ? v.prezzo * x.q : 0)
  }, 0),
)

function clampInput(v, max) {
  const n = Math.max(0, Math.min(Number(v) || 0, max))
  return n
}

function aggiungi() {
  const lines = selezione.value.map((x) => {
    const v = props.variants.find((vv) => String(vv.id) === String(x.id))
    return {
      varianteId: v.id,
      prodottoId: props.product.id,
      codice: props.product.codice,
      nome: props.product.nome,
      taglia: v.taglia,
      colore: v.colore,
      sku: v.sku,
      prezzo: v.prezzo,
      quantita: x.q,
    }
  })
  emit('add', lines)
  Object.keys(qty).forEach((k) => delete qty[k])
}
</script>

<template>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full min-w-[520px] text-sm">
        <thead>
          <tr class="border-b border-zinc-100 text-left text-xs uppercase tracking-wide text-zinc-400">
            <th class="p-3">Colore \ Taglia</th>
            <th v-for="t in taglie" :key="t" class="p-3 text-center">{{ t }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in colori" :key="c" class="border-b border-zinc-50 last:border-0">
            <td class="p-3 font-medium">{{ c }}</td>
            <td v-for="t in taglie" :key="t" class="p-2 text-center">
              <template v-if="variantOf(c, t)">
                <input
                  type="number"
                  min="0"
                  :max="variantOf(c, t).quantita"
                  class="field !w-16 !px-2 !py-1.5 text-center"
                  :value="qty[variantOf(c, t).id] || ''"
                  :placeholder="String(variantOf(c, t).quantita)"
                  @input="
                    qty[variantOf(c, t).id] = clampInput($event.target.value, variantOf(c, t).quantita)
                  "
                />
              </template>
              <span v-else class="text-zinc-300">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 p-3">
      <p class="text-sm text-zinc-500">
        {{ totalePezzi }} pz · <span class="font-semibold text-ink">{{ money(totaleImporto) }}</span>
      </p>
      <button class="btn-primary" :disabled="!selezione.length" @click="aggiungi">
        Aggiungi al carrello
      </button>
    </div>
  </div>
</template>
