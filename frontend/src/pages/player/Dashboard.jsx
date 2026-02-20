import { useEffect, useState } from 'react'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { playerTournamentsApi } from '../../features/tournaments/api'

export default function Dashboard() {
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      const [tournamentsData, registrationsData] = await Promise.all([
        playerTournamentsApi.list(),
        playerRegistrationsApi.list(),
      ])
      setTournaments(tournamentsData)
      setRegistrations(registrationsData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tu panel.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Resumen del jugador</h3>
          <p>Consulta torneos abiertos y tu historial de inscripciones.</p>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="admin-grid">
        <div className="panel-card">
          <div className="panel-header">
            <h4>Torneos disponibles</h4>
            <span className="tag muted">{tournaments.length}</span>
          </div>
          {tournaments.length === 0 ? (
            <div className="empty-state">No hay torneos publicados.</div>
          ) : (
            <div className="registration-list">
              {tournaments.map((tournament) => (
                <div key={tournament.id} className="registration-item">
                  <div>
                    <strong>{tournament.name}</strong>
                    <span>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</span>
                  </div>
                  <div>
                    <span>Fechas</span>
                    <strong>{tournament.start_date} → {tournament.end_date}</strong>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Mis inscripciones</h4>
            <span className="tag muted">{registrations.length}</span>
          </div>
          {registrations.length === 0 ? (
            <div className="empty-state">Aún no tienes inscripciones.</div>
          ) : (
            <div className="registration-list">
              {registrations.map((registration) => (
                <div key={registration.id} className="registration-item">
                  <div>
                    <strong>{registration.team?.display_name}</strong>
                    <span>{registration.tournament_category?.tournament?.name}</span>
                  </div>
                  <div>
                    <span>Estado</span>
                    <strong>{registration.status?.label}</strong>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  )
}
