export function formatMoney(value, currency = 'COP') {
  const number = Number(value ?? 0)

  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number.isFinite(number) ? number : 0)
}

export function parseMoneyInput(value) {
  if (value === null || value === undefined) {
    return null
  }

  const raw = String(value).trim()

  if (!raw) {
    return null
  }

  const digits = raw
    .replace(/\$/g, '')
    .replace(/\s/g, '')
    .split(',')[0]
    .replace(/\./g, '')
    .replace(/[^\d]/g, '')

  if (!digits) {
    return null
  }

  const amount = Number.parseInt(digits, 10)

  return Number.isFinite(amount) ? amount : null
}

export function formatMoneyInput(value) {
  const amount = typeof value === 'number' ? value : parseMoneyInput(value)

  if (amount === null || !Number.isFinite(amount)) {
    return ''
  }

  return `$ ${new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(Math.trunc(amount))}`
}

export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return new Intl.DateTimeFormat('es-CO', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  }

  return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium' }).format(date)
}

export function weekdayLabel(value) {
  const labels = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo',
  }

  return labels[Number(value)] || '—'
}

export function statusColor(status) {
  return {
    abierto: 'blue-7',
    en_proceso: 'positive',
    cerrado: 'orange-8',
    finalizado: 'grey-7',
    pendiente: 'grey-6',
    en_curso: 'primary',
    completado: 'positive',
    cancelado: 'negative',
    parcial: 'orange-8',
    pagado: 'positive',
    pendiente_validar: 'orange-8',
    pago_correcto: 'positive',
    pago_rechazado: 'negative',
    anulado: 'negative',
    activo: 'positive',
    retirado: 'grey-6',
  }[status] || 'grey'
}

export function statusLabel(status) {
  return {
    abierto: 'Abierto',
    en_proceso: 'En proceso',
    cerrado: 'Cerrado',
    finalizado: 'Finalizado',
    pendiente: 'Pendiente',
    en_curso: 'En curso',
    completado: 'Completado',
    cancelado: 'Cancelado',
    parcial: 'Parcial',
    pagado: 'Pagado',
    pendiente_validar: 'Pendiente de validar',
    pago_correcto: 'Pago correcto',
    pago_rechazado: 'Pago rechazado',
    anulado: 'Anulado',
    activo: 'Activo',
    retirado: 'Retirado',
  }[status] || status || '—'
}

export function errorMessage(error, fallback = 'Ocurrió un error inesperado.') {
  const data = error?.response?.data
  const fieldErrors = data?.errors

  if (fieldErrors && typeof fieldErrors === 'object') {
    const messages = Object.values(fieldErrors).flat().filter(Boolean)

    if (messages.length) {
      return messages.join(' ')
    }
  }

  return data?.message || error?.message || fallback
}

export function unwrapList(payload) {
  const body = payload?.data ?? payload

  if (Array.isArray(body)) {
    return { items: body, meta: { current_page: 1, last_page: 1, per_page: body.length, total: body.length } }
  }

  if (Array.isArray(body?.data)) {
    return { items: body.data, meta: body.meta || {} }
  }

  return { items: [], meta: {} }
}
