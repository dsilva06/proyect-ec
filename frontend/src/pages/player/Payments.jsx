import { useEffect, useMemo, useState } from 'react'
import { playerPaymentsApi } from '../../features/payments/api'
import { formatPlayerDate, formatPlayerMoneyFromCents, getPlayerStatusTone } from './ui'

export default function PlayerPayments() {
  const [payments, setPayments] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      setError('')
      const data = await playerPaymentsApi.list()
      setPayments(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus pagos.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const completedPayments = useMemo(
    () => payments.filter((payment) => ['paid', 'completed', 'succeeded'].includes(String(payment.status?.code || '').toLowerCase())),
    [payments],
  )
  const pendingPayments = useMemo(
    () => payments.filter((payment) => ['pending', 'payment_pending'].includes(String(payment.status?.code || '').toLowerCase())),
    [payments],
  )
  const totalPaid = useMemo(
    () => completedPayments.reduce((sum, payment) => sum + Number(payment.amount_cents || 0), 0),
    [completedPayments],
  )

  return (
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Pagos</span>
          <h3>Tu historial financiero, claro y legible en móvil.</h3>
          <p>Consulta cuánto has pagado, qué está pendiente y a qué inscripción corresponde cada movimiento.</p>
        </div>
        <div className="player-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="player-stat-grid">
        <article className="player-stat-card">
          <span>Movimientos</span>
          <strong>{payments.length}</strong>
          <small>Total de pagos registrados.</small>
        </article>
        <article className="player-stat-card">
          <span>Pagos resueltos</span>
          <strong>{completedPayments.length}</strong>
          <small>Operaciones cerradas correctamente.</small>
        </article>
        <article className="player-stat-card">
          <span>Pendientes</span>
          <strong>{pendingPayments.length}</strong>
          <small>Pagos que aún requieren seguimiento.</small>
        </article>
        <article className="player-stat-card">
          <span>Total abonado</span>
          <strong>{formatPlayerMoneyFromCents(totalPaid, completedPayments[0]?.currency || 'USD')}</strong>
          <small>Suma de pagos completados.</small>
        </article>
      </div>

      {payments.length === 0 ? (
        <div className="player-empty-state">No hay pagos registrados en tu cuenta.</div>
      ) : (
        <div className="player-card-stack">
          {payments.map((payment) => (
            <article key={payment.id} className="player-info-card">
              <div className="player-card-topline">
                <span className={`player-status-pill tone-${getPlayerStatusTone(payment.status?.code)}`}>
                  {payment.status?.label || 'Sin estado'}
                </span>
                <span className="player-soft-note">{formatPlayerDate(payment.created_at || payment.updated_at, { year: 'numeric' })}</span>
              </div>
              <h5>{payment.registration?.team?.display_name || 'Equipo'}</h5>
              <p>{payment.registration?.tournament_category?.tournament?.name || 'Torneo'}</p>
              <div className="player-metadata-grid">
                <div>
                  <span>Monto</span>
                  <strong>{formatPlayerMoneyFromCents(payment.amount_cents, payment.currency)}</strong>
                </div>
                <div>
                  <span>Categoría</span>
                  <strong>{payment.registration?.tournament_category?.category?.display_name || payment.registration?.tournament_category?.category?.name || 'Por confirmar'}</strong>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
