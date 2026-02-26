import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { inviteStorage } from '../../auth/inviteStorage'
import { getHomeRouteForRole } from '../../auth/roleHelpers'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'

export default function Login() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    try {
      const loggedInUser = await login(form)
      const token = inviteStorage.getToken()
      if (token) {
        try {
          await playerTeamInvitesApi.claim(token)
          inviteStorage.clearToken()
          navigate('/player/invitations')
          return
        } catch (claimError) {
          setError(claimError?.data?.message || 'No pudimos asociar tu invitacion.')
        }
      }
      navigate(getHomeRouteForRole(loggedInUser))
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Login failed.')
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
                    required
                  />
                </label>
                <button className="primary-button auth-submit" type="submit">Entrar</button>
              </form>
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
