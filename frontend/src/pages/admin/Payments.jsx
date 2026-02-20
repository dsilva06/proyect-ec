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
    <section className="admin-page">
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

      {payments.length === 0 ? (
        <div className="empty-state">No hay pagos registrados.</div>
      ) : (
        <div className="registration-list">
          {payments.map((payment) => (
            <div key={payment.id} className="registration-item">
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
    </section>
  )
}
