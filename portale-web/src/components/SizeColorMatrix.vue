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

// taglie in ordine (42 → 60…): per ordine della taglia, non per come arrivano le varianti
const taglie = computed(() =>
  uniq(
    [...props.variants]
      .filter((v) => v.taglia)
      .sort((a, b) => (a.taglia_ordine ?? 2000) - (b.taglia_ordine ?? 2000))
      .map((v) => v.taglia),
  ),
)
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

// sul pronto non si ordina più della giacenza; sulle stagioni programmate si ordina anche senza giacenza
function maxFor(v) {
  return props.product.programmata ? 9999 : v.quantita
}

function clampInput(v, max) {
  const n = Math.max(0, Math.min(Math.floor(Number(v)) || 0, max))
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
      programmata: !!props.product.programmata,
      partenza: props.product.data_partenza || null,
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
                <span v-if="maxFor(variantOf(c, t)) <= 0" class="text-xs text-zinc-400">Esaurito</span>
                <input
                  v-else
                  type="number"
                  min="0"
                  :max="maxFor(variantOf(c, t))"
                  class="field !w-16 !px-2 !py-1.5 text-center"
                  :value="qty[variantOf(c, t).id] || ''"
                  :placeholder="product.programmata ? '' : String(variantOf(c, t).quantita)"
                  @input="
                    qty[variantOf(c, t).id] = clampInput($event.target.value, maxFor(variantOf(c, t)))
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
