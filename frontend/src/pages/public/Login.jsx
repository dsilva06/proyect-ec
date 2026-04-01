import { useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { inviteStorage } from '../../auth/inviteStorage'
import { getHomeRouteForRole } from '../../auth/roleHelpers'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'
import BrandLockup from '../../components/shared/BrandLockup'

const AUTH_WARNING_KEY = 'auth_login_warning'

function shouldShowVerificationHint(error) {
  const rawMessage = `${error?.data?.raw_message || ''} ${error?.data?.message || ''} ${error?.message || ''}`
    .toLowerCase()

  return (
    rawMessage.includes('verify your email') ||
    rawMessage.includes('verify your account') ||
    rawMessage.includes('email before logging in') ||
    rawMessage.includes('please verify your email')
  )
}

export default function Login() {
  const navigate = useNavigate()
  const location = useLocation()
  const { login } = useAuth()

  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [showVerifyHint, setShowVerifyHint] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const verifiedFromQuery = useMemo(() => {
    const params = new URLSearchParams(location.search)
    return params.get('verified') === '1'
  }, [location.search])

  const handleSubmit = async (event) => {
    event.preventDefault()

    if (isSubmitting) return

    setError('')
    setShowVerifyHint(false)
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
          claimWarning =
            claimError?.data?.message ||
            claimError?.message ||
            'Iniciaste sesión, pero no pudimos asociar tu invitación.'
        }
      }

      if (claimWarning) {
        sessionStorage.setItem(AUTH_WARNING_KEY, claimWarning)
      }

      navigate(getHomeRouteForRole(loggedInUser), { replace: true })
    } catch (err) {
      const errorMessage =
        err?.data?.message ||
        err?.message ||
        'No pudimos iniciar sesión.'

      setError(errorMessage)
      setShowVerifyHint(shouldShowVerificationHint(err))
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
        <BrandLockup subtitle="Acceso de jugadores" />
        <div className="nav-auth-actions">
          <span className="tag muted">Acceso de jugadores</span>
          <Link className="primary-button" to="/register">Registrarse</Link>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <div className="auth-modern-grid">
                <div className="auth-form-panel">
                  <h2>Iniciar sesión</h2>
                  <p className="muted">Ingresa con tu cuenta para ver torneos, pagos e invitaciones.</p>

                  {verifiedFromQuery && (
                    <p className="auth-success">
                      Tu correo fue verificado correctamente. Ya puedes iniciar sesión.
                    </p>
                  )}

                  <form onSubmit={handleSubmit}>
                    <label>
                      Correo
                      <input
                        type="email"
                        placeholder="nombre@correo.com"
                        value={form.email}
                        onChange={(event) => setForm({ ...form, email: event.target.value })}
                        disabled={isSubmitting}
                        required
                      />
                    </label>

                    <label>
                      Contraseña
                      <input
                        type="password"
                        placeholder="Tu contraseña"
                        value={form.password}
                        onChange={(event) => setForm({ ...form, password: event.target.value })}
                        disabled={isSubmitting}
                        required
                      />
                    </label>

                    <button className="primary-button auth-submit" type="submit" disabled={isSubmitting}>
                      {isSubmitting ? 'Ingresando...' : 'Iniciar sesión'}
                    </button>
                  </form>

                  {isSubmitting && <p className="auth-loading">Validando tus credenciales...</p>}
                  {error && <p className="auth-error">{error}</p>}

                  {showVerifyHint && (
                    <p className="auth-switch">
                      <Link to="/verify-email" state={{ email: form.email }}>
                        Reenviar o revisar verificación
                      </Link>
                    </p>
                  )}

                  <p className="auth-switch">
                    ¿No tienes cuenta? <Link to="/register">Crear cuenta</Link>
                  </p>
                </div>

                <aside className="auth-side-panel">
                  <span className="tag muted">Acceso ESTARS</span>
                  <h3>Tu consola de competencia</h3>
                  <p>
                    Desde aquí gestionas tu perfil, recibes invitaciones y haces seguimiento de estados en tiempo real.
                  </p>
                  <div className="auth-side-list">
                    <p>• Historial de inscripciones por categoría</p>
                    <p>• Estado de pago y aceptación del equipo</p>
                    <p>• Acceso directo a sorteos y cronograma</p>
                  </div>
                </aside>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
