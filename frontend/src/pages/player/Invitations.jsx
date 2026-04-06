import { useEffect, useMemo, useState } from 'react'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'
import { formatPlayerDate, getPlayerStatusTone } from './ui'

export default function Invitations() {
  const [invites, setInvites] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [actionId, setActionId] = useState(null)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const data = await playerTeamInvitesApi.list()
      setInvites(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus invitaciones.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleAccept = async (inviteId) => {
    setActionId(inviteId)
    setError('')
    try {
      await playerTeamInvitesApi.accept(inviteId)
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos aceptar la invitación.')
    } finally {
      setActionId(null)
    }
  }

  const pendingInvites = useMemo(
    () => invites.filter((invite) => String(invite.status?.code || '').toLowerCase() === 'sent'),
    [invites],
  )
  const acceptedInvites = useMemo(
    () => invites.filter((invite) => String(invite.status?.code || '').toLowerCase() === 'accepted'),
    [invites],
  )

  return (
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Invitaciones</span>
          <h3>Confirma tu pareja sin perderte.</h3>
          <p>Si tienes una invitación pendiente, aquí la aceptas y quedas dentro del equipo con un solo toque.</p>
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
          <span>Pendientes</span>
          <strong>{pendingInvites.length}</strong>
          <small>Esperando tu respuesta.</small>
        </article>
        <article className="player-stat-card">
          <span>Aceptadas</span>
          <strong>{acceptedInvites.length}</strong>
          <small>Equipos ya confirmados contigo.</small>
        </article>
        <article className="player-stat-card">
          <span>Total</span>
          <strong>{invites.length}</strong>
          <small>Historial general de invitaciones.</small>
        </article>
      </div>

      <section className="panel-card player-surface-card">
        <div className="panel-header">
          <h4>Invitaciones por responder</h4>
          <span className="tag muted">{pendingInvites.length}</span>
        </div>

        {loading ? (
          <div className="player-empty-state">Cargando invitaciones...</div>
        ) : pendingInvites.length === 0 ? (
          <div className="player-empty-state">No tienes invitaciones pendientes en este momento.</div>
        ) : (
          <div className="player-card-stack">
            {pendingInvites.map((invite) => (
              <article key={invite.id} className="player-info-card player-action-card">
                <div className="player-card-topline">
                  <span className={`player-status-pill tone-${getPlayerStatusTone(invite.status?.code)}`}>
                    {invite.status?.label || 'Pendiente'}
                  </span>
                  <span className="player-soft-note">Vence {formatPlayerDate(invite.expires_at, { year: 'numeric' })}</span>
                </div>
                <h5>{invite.team?.display_name || 'Equipo'}</h5>
                <p>Tu partner envió la invitación a <strong>{invite.invited_email}</strong>.</p>
                <div className="player-metadata-grid">
                  <div>
                    <span>Equipo</span>
                    <strong>{invite.team?.display_name || 'Por confirmar'}</strong>
                  </div>
                  <div>
                    <span>Correo invitado</span>
                    <strong>{invite.invited_email || 'Sin correo'}</strong>
                  </div>
                </div>
                <div className="player-cta-row">
                  <button
                    className="primary-button"
                    type="button"
                    onClick={() => handleAccept(invite.id)}
                    disabled={actionId === invite.id}
                  >
                    {actionId === invite.id ? 'Aceptando...' : 'Aceptar invitación'}
                  </button>
                </div>
              </article>
            ))}
          </div>
        )}
      </section>

      {acceptedInvites.length > 0 ? (
        <section className="panel-card player-surface-card">
          <div className="panel-header">
            <h4>Invitaciones ya aceptadas</h4>
            <span className="tag muted">{acceptedInvites.length}</span>
          </div>
          <div className="player-card-stack">
            {acceptedInvites.slice(0, 4).map((invite) => (
              <article key={invite.id} className="player-info-card compact">
                <div className="player-card-topline">
                  <span className={`player-status-pill tone-${getPlayerStatusTone(invite.status?.code)}`}>
                    {invite.status?.label || 'Aceptada'}
                  </span>
                </div>
                <h5>{invite.team?.display_name || 'Equipo'}</h5>
                <p>{invite.invited_email || 'Sin correo asociado'}</p>
              </article>
            ))}
          </div>
        </section>
      ) : null}
    </section>
  )
}
