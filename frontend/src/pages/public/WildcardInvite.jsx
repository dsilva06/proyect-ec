import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { playerWildcardsApi, publicWildcardsApi } from '../../features/wildcards/api'

export default function WildcardInvite() {
  const { token } = useParams()
  const { user } = useAuth()
  const [invite, setInvite] = useState(null)
  const [partnerEmail, setPartnerEmail] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    const load = async () => {
      try {
        const data = await publicWildcardsApi.show(token)
        setInvite(data)
        setPartnerEmail(data.partner_email || '')
      } catch (err) {
        setError(err?.message || 'No pudimos cargar la invitación.')
      }
    }
    load()
  }, [token])

  const handleClaim = async () => {
    setError('')
    setMessage('')
    try {
      await playerWildcardsApi.claim(token, { partner_email: partnerEmail })
      setMessage('Wildcard registrado correctamente.')
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos registrar el wildcard.')
    }
  }

  if (error) {
    return <div className="page"><div className="empty-state">{error}</div></div>
  }

  if (!invite) {
    return <div className="page"><div className="empty-state">Cargando invitación...</div></div>
  }

  return (
    <div className="page">
      <div className="panel-card">
        <div className="panel-header">
          <h3>Invitación wildcard</h3>
        </div>
        <p>{invite.tournament_category?.tournament?.name || 'Torneo'}</p>
        <p>{invite.tournament_category?.category?.display_name || 'Categoría'}</p>
        {invite.email ? <p>Invitado: {invite.email}</p> : null}

        {!user ? (
          <div className="form-actions">
            <Link className="primary-button" to="/login">Inicia sesión</Link>
            <Link className="ghost-button" to="/register">Registrarse</Link>
          </div>
        ) : (
          <div className="form-grid">
            <label>
              Email partner
              <input
                type="email"
                value={partnerEmail}
                onChange={(event) => setPartnerEmail(event.target.value)}
              />
            </label>
            <div className="form-actions">
              <button className="primary-button" type="button" onClick={handleClaim}>
                Registrar wildcard
              </button>
            </div>
            {message && <p className="form-message success">{message}</p>}
            {error && <p className="form-message error">{error}</p>}
          </div>
        )}
      </div>
    </div>
  )
}
