<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useCart } from '@/stores/cart'
import { useCustomer } from '@/stores/customer'
import { useAuth } from '@/stores/auth'
import { useConfig } from '@/stores/config'
import { money, date } from '@/lib/format'
import CustomerSelect from '@/components/CustomerSelect.vue'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const cart = useCart()
const { selected, load: loadCustomers, select: selectCustomer } = useCustomer()
const { isCliente } = useAuth()
const { config, paymentLabel } = useConfig()

const metodo = ref('')
const note = ref('')
const indirizzo = ref('')
const submitting = ref(false)
const error = ref('')
const success = ref(null) // ordini creati (pronto e/o programmato)

// carrello diviso in pronto consegna e programmato: sono due ordini distinti
const gruppi = computed(() =>
  [
    { key: 'pronto', titolo: 'Pronto consegna', righe: cart.righePronto.value },
    { key: 'programmato', titolo: 'Ordine programmato', righe: cart.righeProgrammate.value },
  ].filter((g) => g.righe.length),
)
const partenzeProgrammato = computed(() => [
  ...new Set(cart.righeProgrammate.value.map((i) => i.partenza).filter(Boolean)),
])

// il contrassegno si offre al cliente solo se il backoffice glielo ha abilitato (l'API lo verifica comunque)
const metodi = computed(() =>
  (config.metodi_pagamento ?? ['bonifico', 'contrassegno', 'stripe']).filter(
    (m) => m !== 'contrassegno' || !isCliente.value || selected.value?.contrassegno_abilitato,
  ),
)

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
    const ordini = data.orders ?? [data.data]
    const primo = data.data // il pronto, se c'è: è quello da pagare

    // il programmato non si paga online: resta da concordare col commerciale
    if (metodo.value === 'stripe' && !primo.programmato) {
      try {
        sessionStorage.setItem('ordini_programmati', JSON.stringify(ordini.filter((o) => o.programmato)))
      } catch {
        /* non bloccante */
      }
      const { data: cs } = await api.post(`/orders/${primo.id}/checkout-session`)
      window.location.href = cs.url
      return
    }

    cart.clear()
    success.value = ordini
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
        let programmati = []
        try {
          programmati = JSON.parse(sessionStorage.getItem('ordini_programmati') || '[]')
          sessionStorage.removeItem('ordini_programmati')
        } catch {
          /* non bloccante */
        }
        success.value = [data.data, ...programmati]
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

  // link "riprendi il carrello" dalle email: ?cliente=<id> seleziona il cliente e rimette il carrello salvato
  if (!stripe) {
    if (route.query.cliente) {
      await loadCustomers().catch(() => {})
      selectCustomer(String(route.query.cliente))
      router.replace({ name: 'cart' })
    }
    await cart.restoreFromServer(selected.value?.id ?? localStorage.getItem('selectedCustomerId'))
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold tracking-tight">Carrello</h1>

    <div v-if="success" class="card p-8 text-center">
      <h2 class="text-xl font-bold">{{ success.length > 1 ? 'Ordini inviati' : 'Ordine inviato' }}</h2>
      <div v-for="o in success" :key="o.id" class="mt-3">
        <p class="font-semibold">{{ o.numero }}{{ o.programmato ? ' · programmato' : '' }}</p>
        <p v-if="o.programmato" class="text-sm text-zinc-500">
          Spedizione e pagamento verranno concordati con il commerciale.
        </p>
        <p v-else class="text-sm text-zinc-500">Totale {{ money(o.totale) }} (IVA incl.)</p>
      </div>
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
        <p
          v-if="cart.misto.value"
          class="rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-600"
        >
          Nel carrello ci sono capi in pronto consegna e capi programmati: verranno inviati come due ordini separati.
        </p>
        <div v-for="g in gruppi" :key="g.key" class="card divide-y divide-zinc-100">
          <div v-if="cart.programmato.value" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ g.titolo }}
            <span v-if="g.key === 'programmato' && partenzeProgrammato.length" class="font-normal normal-case">
              · partenza spedizioni {{ partenzeProgrammato.map((d) => date(d)).join(', ') }}
            </span>
          </div>
          <div
            v-for="i in g.righe"
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
            <span>Spese spedizione{{ cart.misto.value ? ' (ordine pronto)' : '' }}</span>
            <span v-if="!cart.righePronto.value.length">Da definire</span>
            <span v-else>{{ money(cart.shipping.value) }}</span>
          </div>
          <p class="-mt-1 text-xs text-zinc-400">
            <template v-if="cart.righePronto.value.length">
              Pronto: {{ money(config.spedizione_pronto_fissa) }} fino a {{ money(config.spedizione_pronto_soglia) }};
              da {{ money(config.spedizione_pronto_soglia) }} in su {{ config.spedizione_pronto_perc }}% sul totale ordine, arrotondato all'euro per eccesso (minimo {{ money(config.spedizione_pronto_fissa) }}).
            </template>
            <template v-if="cart.programmato.value">
              Ordine programmato: la spedizione e il pagamento vengono concordati con il commerciale.
              <template v-if="metodo === 'stripe'">Il pagamento con carta vale per l'ordine in pronto consegna.</template>
            </template>
          </p>
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
