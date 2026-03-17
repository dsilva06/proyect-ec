import { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { authApi } from '../../features/auth/api'

export default function VerifyEmailPending() {
  const location = useLocation()
  const navigate = useNavigate()
  const searchParams = useMemo(() => new URLSearchParams(location.search), [location.search])
  const stateMessage = location.state?.message
  const status = searchParams.get('status')
  const verificationUrl = searchParams.get('url')
  const verifiedName = searchParams.get('name')
  const isVerified = status === 'verified'
  const isInvalidOrExpired = status === 'invalid_or_expired'
  const [email, setEmail] = useState(location.state?.email || '')
  const [isAutoVerifying, setIsAutoVerifying] = useState(false)
  const [message, setMessage] = useState(
    stateMessage ||
      (isVerified
        ? verifiedName
          ? `Bienvenido, ${verifiedName}. Tu correo fue verificado con éxito.`
          : 'Tu correo fue verificado con éxito.'
        : isInvalidOrExpired
          ? 'El enlace de verificación es inválido o expiró. Puedes solicitar uno nuevo.'
          : 'Revisa tu bandeja de entrada para verificar tu cuenta.'),
  )
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setMessage(
      stateMessage ||
        (status === 'verified'
          ? verifiedName
            ? `Bienvenido, ${verifiedName}. Tu correo fue verificado con éxito.`
            : 'Tu correo fue verificado con éxito.'
          : status === 'invalid_or_expired'
            ? 'El enlace de verificación es inválido o expiró. Puedes solicitar uno nuevo.'
            : 'Revisa tu bandeja de entrada para verificar tu cuenta.'),
    )
  }, [stateMessage, status, verifiedName])

  useEffect(() => {
    if (!verificationUrl || status) return

    let cancelled = false
    setIsAutoVerifying(true)
    setError('')

    authApi
      .verifyEmailByUrl(verificationUrl)
      .then((data) => {
        if (cancelled) return

        const name = data?.name ? `&name=${encodeURIComponent(data.name)}` : ''
        navigate(`/verify-email?status=verified${name}`, { replace: true })
      })
      .catch((err) => {
        if (cancelled) return
        const fallbackMessage = 'El enlace de verificación es inválido o expiró. Puedes solicitar uno nuevo.'
        const message = err?.data?.message || err?.message || fallbackMessage

        navigate('/verify-email?status=invalid_or_expired', {
          replace: true,
          state: { message },
        })
      })
      .finally(() => {
        if (!cancelled) {
          setIsAutoVerifying(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [navigate, status, verificationUrl])

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
          <span className="tag muted">{isVerified ? 'Verificación lista' : 'Verificación pendiente'}</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>{isVerified ? 'Verificación lista' : 'Revisa tu correo'}</h2>
              <p className="muted">{message}</p>

              {isAutoVerifying ? (
                <p className="auth-loading">Validando tu enlace de verificación...</p>
              ) : isVerified ? (
                <Link className="primary-button auth-submit" to="/login?verified=1">
                  Ir a iniciar sesión
                </Link>
              ) : (
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
              )}

              {error && <p className="auth-error">{error}</p>}

              <p className="auth-switch">
                {isVerified ? (
                  <>
                    ¿Listo para entrar? <Link to="/login">Inicia sesión</Link>
                  </>
                ) : (
                  <>
                    ¿Ya verificaste tu correo? <Link to="/login">Inicia sesión</Link>
                  </>
                )}
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
