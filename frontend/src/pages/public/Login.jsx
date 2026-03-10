import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { inviteStorage } from '../../auth/inviteStorage'
import { getHomeRouteForRole } from '../../auth/roleHelpers'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'

const AUTH_WARNING_KEY = 'auth_login_warning'

export default function Login() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSubmitting) return

    setError('')
    setIsSubmitting(true)

    try {
      const loggedInUser = await login(form)
      const token = inviteStorage.getToken()

      let claimWarning = ''
      if (token) {
        try {
          await playerTeamInvitesApi.claim(token)
          inviteStorage.clearToken()
          navigate('/player/invitations', { replace: true })
          return
        } catch (claimError) {
          inviteStorage.clearToken()
          claimWarning = claimError?.data?.message || claimError?.message || 'Iniciaste sesion, pero no pudimos asociar tu invitacion.'
        }
      }

      if (claimWarning) {
        sessionStorage.setItem(AUTH_WARNING_KEY, claimWarning)
      }

      navigate(getHomeRouteForRole(loggedInUser), { replace: true })
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Login failed.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="page auth-page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav auth-nav">
        <div className="brand">
          <Link to="/" className="brand-mark">ESTARS PADEL TOUR</Link>
          <span className="brand-subtitle">Tournament Hub</span>
        </div>
        <div className="nav-auth-actions">
          <span className="tag muted">Player Access</span>
          <Link className="primary-button" to="/register">Sign up</Link>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>Iniciar sesion</h2>
              <p className="muted">Ingresa con tu cuenta para ver torneos, pagos e invitaciones.</p>
              <form onSubmit={handleSubmit}>
                <label>
                  Correo
                  <input
                    type="email"
                    placeholder="name@email.com"
                    value={form.email}
                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <label>
                  Contrasena
                  <input
                    type="password"
                    placeholder="Tu contrasena"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <button className="primary-button auth-submit" type="submit" disabled={isSubmitting}>
                  {isSubmitting ? 'Entrando...' : 'Entrar'}
                </button>
              </form>
              {isSubmitting && <p className="auth-loading">Validando tus credenciales...</p>}
              {error && <p className="auth-error">{error}</p>}
              <p className="auth-switch">
                No tienes cuenta? <Link to="/register">Crear cuenta</Link>
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
