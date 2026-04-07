import { useEffect, useMemo, useState } from 'react'
import { adminPaymentsApi } from '../../features/payments/api'
import { statusesApi } from '../../features/statuses/api'

export default function Payments() {
  const [payments, setPayments] = useState([])
  const [statuses, setStatuses] = useState([])
  const [error, setError] = useState('')

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'payment'),
    [statuses],
  )

  const summary = useMemo(() => {
    const counts = payments.reduce((acc, payment) => {
      const code = String(payment?.status?.code || '').toLowerCase()
      acc.total += 1
      if (['pending', 'created', 'requires_action', 'processing'].includes(code)) acc.pending += 1
      if (code === 'succeeded') acc.succeeded += 1
      if (['failed', 'cancelled'].includes(code)) acc.failed += 1
      return acc
    }, {
      total: 0,
      pending: 0,
      succeeded: 0,
      failed: 0,
    })

    return counts
  }, [payments])

  const load = async () => {
    try {
      const [paymentsData, statusesData] = await Promise.all([
        adminPaymentsApi.list(),
        statusesApi.list(),
      ])
      setPayments(paymentsData)
      setStatuses(statusesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los pagos.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleStatusUpdate = async (paymentId, statusId) => {
    try {
      await adminPaymentsApi.update(paymentId, { status_id: statusId })
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar el pago.')
    }
  }

  return (
    <section className="admin-page payments-page">
      <div className="admin-page-header">
        <div>
          <h3>Pagos</h3>
          <p>Actualiza el estado de los pagos de inscripción.</p>
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="panel-card">
        <div className="panel-header">
          <h4>Panorama de pagos</h4>
          <span className="tag muted">Actual</span>
        </div>
        <div className="kpi-grid">
          <div className="kpi-card">
            <span>Total</span>
            <strong>{summary.total}</strong>
          </div>
          <div className="kpi-card">
            <span>Pendientes</span>
            <strong>{summary.pending}</strong>
          </div>
          <div className="kpi-card">
            <span>Confirmados</span>
            <strong>{summary.succeeded}</strong>
          </div>
          <div className="kpi-card">
            <span>Fallidos / cancelados</span>
            <strong>{summary.failed}</strong>
          </div>
        </div>
      </div>

      <div className="panel-card">
        <div className="panel-header">
          <div>
            <h4>Listado de pagos</h4>
            <p className="muted">Mantiene el mismo flujo de actualización de estado.</p>
          </div>
          <span className="tag muted">{payments.length}</span>
        </div>

        {payments.length === 0 ? (
          <div className="empty-state">No hay pagos registrados.</div>
        ) : (
          <div className="registration-list payments-list">
            {payments.map((payment) => (
              <div key={payment.id} className="registration-item payment-row">
                <div>
                  <strong>{payment.registration?.team?.display_name || 'Equipo'}</strong>
                  <span>{payment.registration?.tournament_category?.tournament?.name}</span>
                </div>
                <div>
                  <span>Monto</span>
                  <strong>
                    ${(Number(payment.amount_cents || 0) / 100).toFixed(2)} {payment.currency}
                  </strong>
                </div>
                <div>
                  <span>Estado</span>
                  <select
                    value={payment.status?.id || ''}
                    onChange={(event) => handleStatusUpdate(payment.id, event.target.value)}
                  >
                    {statusOptions.map((status) => (
                      <option key={status.id} value={status.id}>
                        {status.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <span>Fecha</span>
                  <strong>{payment.created_at?.slice(0, 10) || '—'}</strong>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}
