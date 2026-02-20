import { useEffect, useState } from 'react'
import { playerRegistrationsApi } from '../../features/registrations/api'

export default function PlayerRegistrations() {
  const [registrations, setRegistrations] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      const data = await playerRegistrationsApi.list()
      setRegistrations(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus inscripciones.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Mis inscripciones</h3>
          <p>Estado de tus registros por torneo.</p>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      {registrations.length === 0 ? (
        <div className="empty-state">Aún no tienes inscripciones.</div>
      ) : (
        <div className="registration-list">
          {registrations.map((registration) => (
            <div key={registration.id} className="registration-item">
              <div>
                <strong>{registration.team?.display_name}</strong>
                <span>{registration.tournament_category?.tournament?.name}</span>
                <span>{registration.tournament_category?.category?.display_name || registration.tournament_category?.category?.name}</span>
              </div>
              <div>
                <span>Estado</span>
                <strong>{registration.status?.label}</strong>
              </div>
              <div>
                <span>Ranking equipo</span>
                <strong>{registration.team_ranking_score ?? 'Pendiente'}</strong>
              </div>
              <div>
                <span>Posición</span>
                <strong>{registration.queue_position ?? '—'}</strong>
              </div>
              <div>
                <span>Fecha</span>
                <strong>{registration.created_at?.slice(0, 10)}</strong>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
