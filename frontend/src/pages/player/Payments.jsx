import { useEffect, useState } from 'react'
import { playerPaymentsApi } from '../../features/payments/api'

export default function PlayerPayments() {
  const [payments, setPayments] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      const data = await playerPaymentsApi.list()
      setPayments(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus pagos.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Mis pagos</h3>
          <p>Historial de pagos asociados a tus inscripciones.</p>
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
                <strong>{payment.status?.label || '—'}</strong>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
