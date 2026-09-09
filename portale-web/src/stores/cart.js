import { reactive, computed } from 'vue'
import { useConfig } from '@/stores/config'

/**
 * Riga carrello: { varianteId, prodottoId, codice, nome, taglia, colore, sku, prezzo, quantita }
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

export function useCart() {
  const { config } = useConfig()

  const count = computed(() => state.items.length)
  const totalPieces = computed(() => state.items.reduce((s, i) => s + i.quantita, 0))
  const subtotal = computed(() =>
    round2(state.items.reduce((s, i) => s + i.prezzo * i.quantita, 0)),
  )
  const shipping = computed(() => Number(config.spese_spedizione || 0))
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
    }
  }

  function removeLine(varianteId) {
    state.items = state.items.filter((i) => i.varianteId !== varianteId)
    persist()
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
    vatRate,
    vatAmount,
    total,
    belowMinimum,
    addLine,
    addMany,
    setQuantity,
    removeLine,
    clear,
    toOrderPayload,
  }
}

function round2(n) {
  return Math.round((n + Number.EPSILON) * 100) / 100
}
