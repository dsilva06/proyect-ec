import { useEffect, useMemo, useState } from 'react'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { formatPlayerDate, getPlayerStatusTone } from './ui'

const isOpenTournamentRegistration = (registration) =>
  String(registration?.tournament_category?.tournament?.mode || '').toLowerCase() === 'open'

export default function PlayerRegistrations() {
  const [registrations, setRegistrations] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      setError('')
      const data = await playerRegistrationsApi.list()
      setRegistrations(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus inscripciones.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const pendingItems = useMemo(
    () => registrations.filter((registration) => ['pending', 'payment_pending', 'waitlisted'].includes(String(registration.status?.code || '').toLowerCase())),
    [registrations],
  )
  const confirmedItems = useMemo(
    () => registrations.filter((registration) => ['accepted', 'paid'].includes(String(registration.status?.code || '').toLowerCase())),
    [registrations],
  )

  return (
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Inscripciones</span>
          <h3>Entiende tu estado de torneo de un vistazo.</h3>
          <p>En móvil verás cada inscripción como una tarjeta clara: equipo, torneo, estatus, cola y fecha sin tener que leer tablas.</p>
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
          <span>Total de inscripciones</span>
          <strong>{registrations.length}</strong>
          <small>Historial completo en tu cuenta.</small>
        </article>
        <article className="player-stat-card">
          <span>Pendientes</span>
          <strong>{pendingItems.length}</strong>
          <small>Necesitan seguimiento o siguiente paso.</small>
        </article>
        <article className="player-stat-card">
          <span>Confirmadas</span>
          <strong>{confirmedItems.length}</strong>
          <small>Ya mejor encaminadas dentro del torneo.</small>
        </article>
      </div>

      {registrations.length === 0 ? (
        <div className="player-empty-state">Aún no tienes inscripciones registradas.</div>
      ) : (
        <div className="player-card-stack">
          {registrations.map((registration) => (
            <article key={registration.id} className="player-info-card">
              <div className="player-card-topline">
                <span className={`player-status-pill tone-${getPlayerStatusTone(registration.status?.code)}`}>
                  {registration.status?.label || 'Sin estado'}
                </span>
                <span className="player-soft-note">{formatPlayerDate(registration.created_at, { year: 'numeric' })}</span>
              </div>
              <h5>{registration.team?.display_name || 'Equipo'}</h5>
              <p>{registration.tournament_category?.tournament?.name || 'Torneo'} · {registration.tournament_category?.category?.display_name || registration.tournament_category?.category?.name || 'Categoría'}</p>
              <div className="player-metadata-grid">
                <div>
                  <span>Ranking equipo</span>
                  <strong>{isOpenTournamentRegistration(registration) ? 'No aplica' : registration.team_ranking_score ?? 'Pendiente'}</strong>
                </div>
                <div>
                  <span>Posición en cola</span>
                  <strong>{registration.queue_position ?? '—'}</strong>
                </div>
                <div>
                  <span>Tipo</span>
                  <strong>{registration.is_wildcard ? 'Wildcard' : 'Regular'}</strong>
                </div>
                <div>
                  <span>Fecha</span>
                  <strong>{formatPlayerDate(registration.created_at, { year: 'numeric' })}</strong>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
