import { reactive } from 'vue'
import { api } from '@/lib/api'

const state = reactive({
  config: {
    vat: 22,
    spese_spedizione: 0,
    importo_minimo_ordine: 0,
    giorni_evasione_programmati: 30,
    metodi_pagamento: ['bonifico', 'contrassegno', 'stripe'],
  },
  filters: { categories: [], seasons: [], packages: [], fabrics: [] },
  loaded: false,
})

const PAYMENT_LABELS = {
  stripe: 'Carta di credito',
  bonifico: 'Bonifico bancario',
  contrassegno: 'Contrassegno',
  rimessa: 'Rimessa diretta',
}

export function useConfig() {
  async function load() {
    const [{ data: cfg }, { data: flt }] = await Promise.all([
      api.get('/app-config'),
      api.get('/filters'),
    ])
    Object.assign(state.config, cfg)
    Object.assign(state.filters, flt)
    state.loaded = true
  }

  function paymentLabel(code) {
    return PAYMENT_LABELS[code] ?? code
  }

  return { state, config: state.config, filters: state.filters, load, paymentLabel }
}
