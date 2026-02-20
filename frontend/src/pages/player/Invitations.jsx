import { useEffect, useState } from 'react'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'

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

  const pendingInvites = invites.filter((invite) => invite.status?.code === 'sent')
  const acceptedInvites = invites.filter((invite) => invite.status?.code === 'accepted')

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Invitaciones pendientes</h3>
          <p>Acepta tu lugar en el equipo desde aquí.</p>
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="admin-grid">
        <div className="panel-card">
          <div className="panel-header">
            <h4>Resumen</h4>
            <span className="tag muted">{pendingInvites.length} pendientes</span>
          </div>
          <div className="registration-list">
            <div className="registration-item">
              <div>
                <span>Pendientes</span>
                <strong>{pendingInvites.length}</strong>
              </div>
              <div>
                <span>Aceptadas</span>
                <strong>{acceptedInvites.length}</strong>
              </div>
              <div>
                <span>Total</span>
                <strong>{invites.length}</strong>
              </div>
            </div>
          </div>
          <p className="form-message">
            Tu partner envió la invitación a tu correo. Acepta para quedar dentro del equipo.
          </p>
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Invitaciones pendientes</h4>
          </div>

          {loading ? (
            <div className="empty-state">Cargando invitaciones...</div>
          ) : pendingInvites.length === 0 ? (
            <div className="empty-state">No tienes invitaciones pendientes.</div>
          ) : (
            <div className="registration-list">
              {pendingInvites.map((invite) => (
                <div key={invite.id} className="registration-item">
                  <div>
                    <strong>{invite.team?.display_name || 'Equipo'}</strong>
                    <span>{invite.invited_email}</span>
                    <span>Vence: {invite.expires_at?.slice(0, 10) || 'Pronto'}</span>
                  </div>
                  <div>
                    <span>Estado</span>
                    <strong>{invite.status?.label || 'Pendiente'}</strong>
                  </div>
                  <div>
                    <button
                      className="primary-button"
                      type="button"
                      onClick={() => handleAccept(invite.id)}
                      disabled={actionId === invite.id}
                    >
                      {actionId === invite.id ? 'Aceptando...' : 'Aceptar invitación'}
                    </button>
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
