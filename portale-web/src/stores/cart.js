import { reactive, computed } from 'vue'
import { useConfig } from '@/stores/config'
import { api } from '@/lib/api'

/**
 * Riga carrello: { varianteId, prodottoId, codice, nome, taglia, colore, sku, prezzo, quantita, programmata, partenza }
 * Il carrello è vincolato al cliente selezionato (vedi stores/customer.js -> select()).
 */
const state = reactive({
  items: safeParse(localStorage.getItem('cart_items')) || [],
  draftId: localStorage.getItem('cart_draft_id') || null,
})

function safeParse(v) {
  try {
    return v ? JSON.parse(v) : null
  } catch {
    return null
  }
}

function persist() {
  localStorage.setItem('cart_items', JSON.stringify(state.items))
  if (state.draftId) localStorage.setItem('cart_draft_id', state.draftId)
  else localStorage.removeItem('cart_draft_id')
}

/**
 * Copia del carrello sul server (uno per cliente): serve a riprenderlo da un altro dispositivo
 * e al promemoria per i carrelli fermi. Si salva poco dopo ogni modifica; l'ordine la cancella lato server.
 * Il cliente e le righe si fissano quando si pianifica il salvataggio, così un cambio di cliente subito dopo
 * non scrive il carrello sbagliato.
 */
let syncTimer = null
function syncServer() {
  const clienteId = localStorage.getItem('selectedCustomerId')
  if (!clienteId || !localStorage.getItem('token')) return
  const items = state.items.map((i) => ({
    varianteId: String(i.varianteId),
    prodottoId: i.prodottoId != null ? String(i.prodottoId) : null,
    codice: i.codice ?? null,
    nome: i.nome ?? null,
    taglia: i.taglia != null ? String(i.taglia) : null,
    colore: i.colore ?? null,
    sku: i.sku ?? null,
    prezzo: Number(i.prezzo) || 0,
    quantita: Number(i.quantita) || 1,
    programmata: !!i.programmata,
  }))
  clearTimeout(syncTimer)
  syncTimer = setTimeout(() => {
    api.put('/cart', { cliente_id: clienteId, items }).catch(() => {
      /* non bloccante: il carrello resta comunque nel browser */
    })
  }, 1500)
}

/** Se il carrello locale è vuoto, riprende quello salvato sul server per il cliente indicato. */
async function restoreFromServer(clienteId) {
  if (!clienteId || state.items.length) return
  try {
    const { data } = await api.get('/cart', { params: { cliente_id: clienteId } })
    const items = data?.data?.items ?? []
    if (items.length && !state.items.length) {
      state.items = items
      persist()
    }
  } catch {
    /* nessun carrello salvato o rete assente */
  }
}

export function useCart() {
  const { config } = useConfig()

  const count = computed(() => state.items.length)
  const totalPieces = computed(() => state.items.reduce((s, i) => s + i.quantita, 0))
  const subtotal = computed(() =>
    round2(state.items.reduce((s, i) => s + i.prezzo * i.quantita, 0)),
  )
  // pronto consegna e programmato (collezione stagione + linea) sono due ordini separati
  const righePronto = computed(() => state.items.filter((i) => !i.programmata))
  const righeProgrammate = computed(() => state.items.filter((i) => i.programmata))
  const programmato = computed(() => righeProgrammate.value.length > 0)
  const misto = computed(() => righeProgrammate.value.length > 0 && righePronto.value.length > 0)
  const sommaRighe = (righe) => round2(righe.reduce((s, i) => s + i.prezzo * i.quantita, 0))
  const subtotalPronto = computed(() => sommaRighe(righePronto.value))
  const subtotalProgrammato = computed(() => sommaRighe(righeProgrammate.value))
  // pronto: fissa fino alla soglia, da soglia in su % sul totale merce; programmato: da definire col commerciale
  const shipping = computed(() => {
    if (!righePronto.value.length) return 0
    const soglia = Number(config.spedizione_pronto_soglia ?? 300)
    const fissa = Number(config.spedizione_pronto_fissa ?? 10)
    if (subtotalPronto.value < soglia) return fissa
    // percentuale arrotondata per eccesso all'euro, mai sotto la spesa fissa (stessa regola dell'API)
    return Math.max(fissa, Math.ceil(round2((subtotalPronto.value * Number(config.spedizione_pronto_perc ?? 3)) / 100)))
  })
  const vatRate = computed(() => Number(config.vat ?? 22))
  const vatAmount = computed(() => round2(((subtotal.value + shipping.value) * vatRate.value) / 100))
  const total = computed(() => round2(subtotal.value + shipping.value + vatAmount.value))
  const belowMinimum = computed(
    () => Number(config.importo_minimo_ordine || 0) > 0 && subtotal.value < Number(config.importo_minimo_ordine),
  )

  function addLine(line) {
    const existing = state.items.find((i) => i.varianteId === line.varianteId)
    if (existing) existing.quantita += line.quantita
    else state.items.push({ ...line })
    persist()
    syncServer()
  }

  /** aggiunge/aggiorna in blocco le righe di un prodotto (dalla matrice taglie/colori) */
  function addMany(lines) {
    lines.filter((l) => l.quantita > 0).forEach(addLine)
  }

  function setQuantity(varianteId, quantita) {
    const i = state.items.find((x) => x.varianteId === varianteId)
    if (!i) return
    if (quantita <= 0) removeLine(varianteId)
    else {
      i.quantita = quantita
      persist()
      syncServer()
    }
  }

  function removeLine(varianteId) {
    state.items = state.items.filter((i) => i.varianteId !== varianteId)
    persist()
    syncServer()
  }

  function clear() {
    state.items = []
    state.draftId = null
    persist()
  }

  /** payload per POST /orders */
  function toOrderPayload({ clienteId, metodoPagamento, note, indirizzo }) {
    return {
      cliente_id: clienteId,
      metodo_pagamento: metodoPagamento,
      note_agente: note || null,
      indirizzo_spedizione: indirizzo || null,
      righe: state.items.map((i) => ({ variante_id: i.varianteId, quantita: i.quantita })),
    }
  }

  return {
    state,
    count,
    totalPieces,
    subtotal,
    shipping,
    righePronto,
    righeProgrammate,
    subtotalPronto,
    subtotalProgrammato,
    programmato,
    misto,
    vatRate,
    vatAmount,
    total,
    belowMinimum,
    addLine,
    addMany,
    setQuantity,
    removeLine,
    clear,
    restoreFromServer,
    toOrderPayload,
  }
}

function round2(n) {
  return Math.round((n + Number.EPSILON) * 100) / 100
}
