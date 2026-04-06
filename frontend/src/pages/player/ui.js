export const formatPlayerDate = (value, options = {}) => {
  if (!value) return 'Por confirmar'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value).slice(0, 10)

  return parsed.toLocaleDateString('es-ES', {
    day: 'numeric',
    month: 'short',
    ...options,
  })
}

export const formatPlayerMoneyFromCents = (amountCents, currency = 'USD') => {
  const amount = Number(amountCents || 0) / 100
  if (!Number.isFinite(amount)) return 'Por confirmar'

  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: currency || 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

export const getPlayerStatusTone = (statusCode) => {
  const code = String(statusCode || '').toLowerCase()

  if (['accepted', 'paid', 'completed', 'confirmed', 'approved'].includes(code)) return 'success'
  if (['payment_pending', 'pending', 'waitlisted', 'sent'].includes(code)) return 'warning'
  if (['cancelled', 'failed', 'rejected', 'expired'].includes(code)) return 'danger'

  return 'neutral'
}
