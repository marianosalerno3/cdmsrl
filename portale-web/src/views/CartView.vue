<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useCart } from '@/stores/cart'
import { useCustomer } from '@/stores/customer'
import { useConfig } from '@/stores/config'
import { money } from '@/lib/format'
import CustomerSelect from '@/components/CustomerSelect.vue'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const cart = useCart()
const { selected } = useCustomer()
const { config, paymentLabel } = useConfig()

const metodo = ref('')
const note = ref('')
const indirizzo = ref('')
const submitting = ref(false)
const error = ref('')
const success = ref(null)

const metodi = computed(() => config.metodi_pagamento ?? ['bonifico', 'contrassegno', 'stripe'])

const canSubmit = computed(
  () => selected.value && cart.state.items.length && metodo.value && !cart.belowMinimum.value,
)

async function conferma() {
  error.value = ''
  submitting.value = true
  try {
    const payload = cart.toOrderPayload({
      clienteId: selected.value.id,
      metodoPagamento: metodo.value,
      note: note.value,
      indirizzo: indirizzo.value || selected.value.indirizzo,
    })
    const { data } = await api.post('/orders', payload)
    const ordine = data.data

    if (metodo.value === 'stripe') {
      const { data: cs } = await api.post(`/orders/${ordine.id}/checkout-session`)
      window.location.href = cs.url
      return
    }

    cart.clear()
    success.value = ordine
  } catch (e) {
    error.value = errorMessage(e, 'Invio ordine non riuscito.')
  } finally {
    submitting.value = false
  }
}

// ritorno da Stripe: ?stripe=success&order=<id>&session_id=<sid>
onMounted(async () => {
  const { stripe, order, session_id: sid } = route.query
  if (stripe === 'success' && order && sid) {
    try {
      const { data } = await api.post(`/orders/${order}/verify-stripe-payment`, { session_id: sid })
      if (data.paid) {
        cart.clear()
        success.value = data.data
      } else {
        error.value = 'Pagamento non confermato.'
      }
    } catch (e) {
      error.value = errorMessage(e)
    }
    router.replace({ name: 'cart' })
  } else if (stripe === 'cancel') {
    error.value = 'Pagamento annullato.'
    router.replace({ name: 'cart' })
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold tracking-tight">Carrello</h1>

    <div v-if="success" class="card p-8 text-center">
      <h2 class="text-xl font-bold">Ordine inviato — {{ success.numero }}</h2>
      <p class="mt-1 text-sm text-zinc-500">Totale {{ money(success.totale) }} (IVA incl.)</p>
      <div class="mt-6 flex justify-center gap-3">
        <RouterLink to="/catalog" class="btn-primary">Nuovo ordine</RouterLink>
        <RouterLink to="/dashboard" class="btn-ghost">Vai alla dashboard</RouterLink>
      </div>
    </div>

    <template v-else>
      <EmptyState
        v-if="!cart.state.items.length"
        title="Il carrello è vuoto"
        subtitle="Aggiungi prodotti dal catalogo."
      >
        <RouterLink to="/catalog" class="btn-primary mt-2">Vai al catalogo</RouterLink>
      </EmptyState>

      <template v-else>
        <div class="card divide-y divide-zinc-100">
          <div
            v-for="i in cart.state.items"
            :key="i.varianteId"
            class="flex items-center gap-3 p-3 text-sm"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate font-medium">{{ i.nome }}</p>
              <p class="text-xs text-zinc-500">
                {{ i.codice }} · {{ i.taglia }} / {{ i.colore }} · {{ money(i.prezzo) }}
              </p>
            </div>
            <input
              type="number"
              min="1"
              class="field !w-16 !px-2 !py-1.5 text-center"
              :value="i.quantita"
              @input="cart.setQuantity(i.varianteId, Number($event.target.value))"
            />
            <span class="w-20 text-right font-semibold">{{ money(i.prezzo * i.quantita) }}</span>
            <button class="btn-link !text-red-500" @click="cart.removeLine(i.varianteId)">✕</button>
          </div>
        </div>

        <div class="card space-y-4 p-4">
          <CustomerSelect />

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label">Metodo di pagamento</label>
              <select v-model="metodo" class="field">
                <option value="">Seleziona…</option>
                <option v-for="m in metodi" :key="m" :value="m">{{ paymentLabel(m) }}</option>
              </select>
            </div>
            <div>
              <label class="label">Indirizzo spedizione</label>
              <input
                v-model="indirizzo"
                class="field"
                :placeholder="selected?.indirizzo || 'Indirizzo del cliente'"
              />
            </div>
          </div>
          <div>
            <label class="label">Note ordine (opzionale)</label>
            <textarea v-model="note" rows="2" class="field"></textarea>
          </div>
        </div>

        <div class="card space-y-2 p-4 text-sm">
          <div class="flex justify-between"><span>Subtotale</span><span>{{ money(cart.subtotal.value) }}</span></div>
          <div class="flex justify-between">
            <span>Spese spedizione</span><span>{{ money(cart.shipping.value) }}</span>
          </div>
          <div class="flex justify-between text-zinc-500">
            <span>IVA ({{ cart.vatRate.value }}%)</span><span>{{ money(cart.vatAmount.value) }}</span>
          </div>
          <div class="flex justify-between border-t border-zinc-100 pt-2 text-base font-bold">
            <span>Totale ordine (IVA incl.)</span><span>{{ money(cart.total.value) }}</span>
          </div>
          <p v-if="cart.belowMinimum.value" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
            Ordine sotto l'importo minimo di {{ money(config.importo_minimo_ordine) }}.
          </p>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <button class="btn-primary w-full" :disabled="!canSubmit || submitting" @click="conferma">
          {{ submitting ? 'Invio…' : metodo === 'stripe' ? 'Paga e conferma' : 'Conferma ordine' }}
        </button>
      </template>
    </template>
  </div>
</template>
