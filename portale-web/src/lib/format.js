const eur = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' })
const dateFmt = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' })

export function money(value) {
  const n = Number(value ?? 0)
  return eur.format(Number.isFinite(n) ? n : 0)
}

// nomi dei listini come li usa l'azienda (l'API restituisce il codice interno: standard, plus5, plus10)
const LISTINI = { standard: 'OT', plus5: 'OT+5', plus10: 'OT+10' }

export function listinoLabel(value) {
  return LISTINI[value] ?? String(value ?? 'OT')
}

export function date(value) {
  if (!value) return '—'
  const d = value instanceof Date ? value : new Date(value)
  return Number.isNaN(d.getTime()) ? '—' : dateFmt.format(d)
}
