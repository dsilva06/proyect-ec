import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { authApi } from '../../features/auth/api'

export default function VerifyEmailPending() {
  const location = useLocation()
  const [email, setEmail] = useState(location.state?.email || '')
  const [message, setMessage] = useState(
    location.state?.message || 'Revisa tu bandeja de entrada para verificar tu cuenta.',
  )
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleResend = async (event) => {
    event.preventDefault()
    if (isSubmitting) return

    setError('')
    setIsSubmitting(true)

    try {
      const data = await authApi.resendVerification({ email })
      setMessage(
        data?.message || 'If the account exists and is not yet verified, a verification email has been sent.',
      )
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos reenviar el correo de verificación.')
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
          <Link className="ghost-button" to="/login">Login</Link>
          <span className="tag muted">Verificación pendiente</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>Revisa tu correo</h2>
              <p className="muted">{message}</p>

              <form onSubmit={handleResend}>
                <label>
                  Correo
                  <input
                    type="email"
                    placeholder="name@email.com"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    required
                    disabled={isSubmitting}
                  />
                </label>
                <button className="secondary-button auth-submit" type="submit" disabled={isSubmitting}>
                  {isSubmitting ? 'Reenviando...' : 'Reenviar correo de verificación'}
                </button>
              </form>

              {error && <p className="auth-error">{error}</p>}

              <p className="auth-switch">
                ¿Ya verificaste tu correo? <Link to="/login">Inicia sesión</Link>
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
