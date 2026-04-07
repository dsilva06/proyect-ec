import { useEffect, useMemo, useState } from 'react'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'
import { formatPlayerDate, getPlayerStatusTone, getPlayerRegistrationStageLabel } from './ui'

export default function Invitations() {
  const [invites, setInvites] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [actionId, setActionId] = useState(null)
  const [actionType, setActionType] = useState('')

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
    setActionType('accept')
    setError('')
    try {
      await playerTeamInvitesApi.accept(inviteId)
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos aceptar la invitación.')
    } finally {
      setActionId(null)
      setActionType('')
    }
  }

  const handleReject = async (inviteId) => {
    setActionId(inviteId)
    setActionType('reject')
    setError('')
    try {
      await playerTeamInvitesApi.reject(inviteId)
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos rechazar la invitación.')
    } finally {
      setActionId(null)
      setActionType('')
    }
  }

  const pendingInvites = useMemo(
    () => invites.filter((invite) => String(invite.status?.code || '').toLowerCase() === 'pending'),
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
          <h3>Confirma tu lugar en el torneo.</h3>
          <p>Aquí ves quién te invitó, en qué torneo entras y si el pago del equipo ya quedó cubierto.</p>
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
                <h5>{invite.tournament_name || invite.team?.display_name || 'Equipo'}</h5>
                <p>
                  <strong>{invite.captain_name || 'Tu pareja'}</strong> te invitó a jugar
                  {' '}
                  <strong>{invite.category_name || 'esta categoría'}</strong>.
                </p>
                <div className="player-metadata-grid">
                  <div>
                    <span>Capitán</span>
                    <strong>{invite.captain_name || 'Por confirmar'}</strong>
                  </div>
                  <div>
                    <span>Inscripción</span>
                    <strong>{getPlayerRegistrationStageLabel(invite.registration_status_code)}</strong>
                  </div>
                  <div>
                    <span>Pago del equipo</span>
                    <strong>{invite.payment_is_covered ? 'Cubierto' : 'Pendiente'}</strong>
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
                    {actionId === invite.id && actionType === 'accept' ? 'Aceptando...' : 'Aceptar invitación'}
                  </button>
                  <button
                    className="secondary-button"
                    type="button"
                    onClick={() => handleReject(invite.id)}
                    disabled={actionId === invite.id}
                  >
                    {actionId === invite.id && actionType === 'reject' ? 'Rechazando...' : 'Rechazar'}
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
                <h5>{invite.tournament_name || invite.team?.display_name || 'Equipo'}</h5>
                <p>{invite.category_name || invite.invited_email || 'Sin correo asociado'}</p>
              </article>
            ))}
          </div>
        </section>
      ) : null}
    </section>
  )
}
