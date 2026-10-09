<script setup>
import { computed } from 'vue'
import { money } from '@/lib/format'

const props = defineProps({ product: { type: Object, required: true } })

const priceLabel = computed(() => {
  const { prezzo_min: min, prezzo_max: max } = props.product
  if (min == null) return '—'
  return min === max ? money(min) : `${money(min)} – ${money(max)}`
})
</script>

<template>
  <RouterLink
    :to="`/product/${product.id}`"
    class="card group flex flex-col overflow-hidden transition hover:shadow-md"
  >
    <div class="aspect-[4/5] w-full overflow-hidden bg-zinc-100">
      <img
        v-if="product.immagine"
        :src="product.immagine"
        :alt="product.nome"
        class="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
        loading="lazy"
      />
      <div v-else class="grid size-full place-items-center text-xs text-zinc-400">nessuna immagine</div>
    </div>
    <div class="flex flex-1 flex-col gap-1 p-3">
      <div class="flex items-center justify-between text-[11px] uppercase tracking-wide text-zinc-400">
        <span>{{ product.codice }}</span>
        <span v-if="product.stagione">{{ product.stagione }}</span>
      </div>
      <p class="line-clamp-2 text-sm font-medium">{{ product.nome }}</p>
      <div class="mt-auto flex items-center justify-between pt-1">
        <span class="text-sm font-semibold">{{ priceLabel }}</span>
        <span
          class="text-xs"
          :class="product.giacenza_totale > 0 ? 'text-emerald-600' : 'text-zinc-400'"
        >
          {{ product.giacenza_totale > 0 ? `${product.giacenza_totale} pz` : 'esaurito' }}
        </span>
      </div>
    </div>
  </RouterLink>
</template>
