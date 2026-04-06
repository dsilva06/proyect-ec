import { useEffect, useState } from 'react'
import { playerProfileApi } from '../../features/players/profileApi'
import { formatPlayerDate } from './ui'

const getDisplayValue = (value, fallback = 'Por definir') => {
  if (value === null || value === undefined || value === '') return fallback
  return value
}

export default function PlayerProfile() {
  const [profile, setProfile] = useState(null)
  const [error, setError] = useState('')

  const load = async () => {
    try {
      setError('')
      const data = await playerProfileApi.get()
      setProfile(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tu perfil de jugador.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const playerProfile = profile?.player_profile || {}

  return (
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Perfil de jugador</span>
          <h3>Tus datos de torneo en un solo lugar.</h3>
          <p>Consulta el nombre con el que compites, tu documento, teléfono y ranking cargado para cada inscripción.</p>
        </div>
        <div className="player-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="player-section-grid player-section-grid-tight">
        <section className="panel-card player-surface-card player-ranking-summary">
          <span className="player-section-kicker">Identidad competitiva</span>
          <h4>{getDisplayValue(profile?.name, 'Jugador')}</h4>
          <p>{getDisplayValue(profile?.role === 'player' ? 'Jugador activo en el torneo.' : profile?.role, 'Jugador')}</p>
          <div className="player-metadata-grid">
            <div>
              <span>Nombre</span>
              <strong>{getDisplayValue(playerProfile.first_name, profile?.name || 'Por definir')}</strong>
            </div>
            <div>
              <span>Apellido</span>
              <strong>{getDisplayValue(playerProfile.last_name)}</strong>
            </div>
            <div>
              <span>DNI</span>
              <strong>{getDisplayValue(playerProfile.dni)}</strong>
            </div>
            <div>
              <span>Provincia / estado</span>
              <strong>{getDisplayValue(playerProfile.province_state)}</strong>
            </div>
          </div>
        </section>

        <section className="panel-card player-surface-card">
          <div className="panel-header">
            <h4>Contacto y ranking</h4>
            <span className="tag muted">Lectura</span>
          </div>
          <div className="player-card-stack">
            <article className="player-info-card compact">
              <div className="player-metadata-grid">
                <div>
                  <span>Correo</span>
                  <strong>{getDisplayValue(profile?.email)}</strong>
                </div>
                <div>
                  <span>Teléfono</span>
                  <strong>{getDisplayValue(profile?.phone)}</strong>
                </div>
                <div>
                  <span>Fuente ranking</span>
                  <strong>{getDisplayValue(profile?.ranking_source, 'Sin ranking')}</strong>
                </div>
                <div>
                  <span>Ranking</span>
                  <strong>{getDisplayValue(profile?.ranking_value, 'Sin ranking')}</strong>
                </div>
              </div>
            </article>
            <article className="player-info-card compact">
              <div className="player-metadata-grid">
                <div>
                  <span>Última actualización</span>
                  <strong>{profile?.ranking_updated_at ? formatPlayerDate(profile.ranking_updated_at, { year: 'numeric' }) : 'Nunca'}</strong>
                </div>
                <div>
                  <span>Estado</span>
                  <strong>{profile?.is_active ? 'Activo' : 'Inactivo'}</strong>
                </div>
              </div>
            </article>
          </div>
        </section>
      </div>
    </section>
  )
}
