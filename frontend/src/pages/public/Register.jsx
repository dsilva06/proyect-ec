import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { inviteStorage } from '../../auth/inviteStorage'
import { publicTeamInvitesApi } from '../../features/teamInvites/api'
import BrandLockup from '../../components/shared/BrandLockup'

export default function Register() {
  const navigate = useNavigate()
  const { register } = useAuth()
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    dni: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  })
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [invite, setInvite] = useState(null)
  const [inviteError, setInviteError] = useState('')

  useEffect(() => {
    const token = inviteStorage.getToken()
    if (!token) return

    let isMounted = true

    publicTeamInvitesApi.get(token)
      .then((data) => {
        if (!isMounted) return
        setInvite(data)
        if (data?.invited_email) {
          setForm((prev) => ({ ...prev, email: data.invited_email }))
        }
      })
      .catch(() => {
        if (!isMounted) return
        setInviteError('No pudimos cargar la invitacion. Intenta nuevamente.')
        inviteStorage.clearToken()
      })

    return () => {
      isMounted = false
    }
  }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSubmitting) return

    setError('')
    setInviteError('')
    setIsSubmitting(true)

    try {
      const data = await register(form)

      navigate('/verify-email', {
        replace: true,
        state: {
          email: form.email,
          message: data?.message,
        },
      })
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Registration failed.')
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
        <BrandLockup subtitle="Player Access" />
        <div className="nav-auth-actions">
          <Link className="ghost-button" to="/login">Login</Link>
          <span className="tag muted">Player Access</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>Crear cuenta</h2>
              <p className="muted">Registra tu perfil para unirte al torneo y gestionar tu equipo.</p>

              {invite && (
                <div className="auth-status">
                  <span className="tag">Invitacion pendiente</span>
                  <p>
                    Equipo: <strong>{invite.team?.display_name || 'Equipo'}</strong>
                  </p>
                  <p>Usa este correo para reclamar tu invitacion automaticamente.</p>
                </div>
              )}

              <form onSubmit={handleSubmit}>
                <label>
                  Nombre
                  <input
                    type="text"
                    placeholder="Nombre"
                    value={form.first_name}
                    onChange={(event) => setForm({ ...form, first_name: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <label>
                  Apellido
                  <input
                    type="text"
                    placeholder="Apellido"
                    value={form.last_name}
                    onChange={(event) => setForm({ ...form, last_name: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <label>
                  DNI
                  <input
                    type="text"
                    placeholder="Ej: 12345678"
                    value={form.dni}
                    onChange={(event) => setForm({ ...form, dni: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <label>
                  Correo
                  <input
                    type="email"
                    placeholder="name@email.com"
                    value={form.email}
                    disabled={Boolean(invite?.invited_email) || isSubmitting}
                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Telefono (opcional)
                  <input
                    type="text"
                    placeholder="+34 600 000 000"
                    value={form.phone}
                    onChange={(event) => setForm({ ...form, phone: event.target.value })}
                    disabled={isSubmitting}
                  />
                </label>
                <label>
                  Contrasena
                  <input
                    type="password"
                    placeholder="Minimo 8 caracteres"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <label>
                  Confirmar contrasena
                  <input
                    type="password"
                    placeholder="Repite la contrasena"
                    value={form.password_confirmation}
                    onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                    disabled={isSubmitting}
                    required
                  />
                </label>
                <button className="primary-button auth-submit" type="submit" disabled={isSubmitting}>
                  {isSubmitting ? 'Creando cuenta...' : 'Crear cuenta'}
                </button>
              </form>

              {inviteError && <p className="auth-error">{inviteError}</p>}
              {error && <p className="auth-error">{error}</p>}

              <p className="auth-switch">
                Ya tienes cuenta? <Link to="/login">Inicia sesion</Link>
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
