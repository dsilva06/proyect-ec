import { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { readVerificationContext, writeVerificationContext } from '../../auth/storage'
import { authApi } from '../../features/auth/api'
import BrandLockup from '../../components/shared/BrandLockup'

export default function VerifyEmailPending() {
  const location = useLocation()
  const navigate = useNavigate()
  const searchParams = useMemo(() => new URLSearchParams(location.search), [location.search])
  const stateMessage = location.state?.message
  const status = searchParams.get('status')
  const verificationUrl = searchParams.get('verify_url') || searchParams.get('url')
  const verifiedName = searchParams.get('name')
  const isVerified = status === 'verified'
  const isInvalidOrExpired = status === 'invalid_or_expired'
  const [email, setEmail] = useState(location.state?.email || '')
  const [verificationContext, setVerificationContext] = useState(
    location.state?.verificationContext || readVerificationContext() || '',
  )
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

    navigate(`/?verify_url=${encodeURIComponent(verificationUrl)}`, { replace: true })
  }, [navigate, status, verificationUrl])

  useEffect(() => {
    writeVerificationContext(verificationContext || null)
  }, [verificationContext])

  useEffect(() => {
    if (isVerified) {
      setVerificationContext('')
    }
  }, [isVerified])

  const handleResend = async (event) => {
    event.preventDefault()
    if (isSubmitting) return

    setError('')
    setIsSubmitting(true)

    try {
      const data = await authApi.resendVerification({
        email,
        verification_context: verificationContext || undefined,
      })

      if (data?.verification_context) {
        setVerificationContext(data.verification_context)
      }

      setMessage(data?.message || 'Correo de verificación reenviado. Revisa tu bandeja de entrada.')
    } catch (err) {
      const code = err?.data?.error_code
      if (code === 'PENDING_REGISTRATION_NOT_FOUND') {
        setError('No encontramos una cuenta pendiente con ese correo. El proceso ha expirado — vuelve a registrarte desde el inicio.')
      } else {
        setError(err?.data?.message || err?.message || 'No pudimos reenviar el correo de verificación. Inténtalo en unos minutos.')
      }
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
        <BrandLockup subtitle="Centro de verificación" />
        <div className="nav-auth-actions">
          <Link className="ghost-button" to="/login">Iniciar sesión</Link>
          <span className="tag muted">{isVerified ? 'Verificación lista' : 'Verificación pendiente'}</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>{isVerified ? 'Verificación lista' : 'Revisa tu correo'}</h2>
              <p className="muted">{message}</p>

              {isVerified ? (
                <Link className="primary-button auth-submit" to="/login?verified=1">
                  Ir a iniciar sesión
                </Link>
              ) : (
                <form onSubmit={handleResend}>
                  <label>
                    Correo
                    <input
                      type="email"
                      placeholder="nombre@correo.com"
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

              {error && (
                <div>
                  <p className="auth-error">{error}</p>
                  {error.includes('registrarte') && (
                    <Link className="secondary-button auth-submit" to="/register">
                      Volver a registrarse
                    </Link>
                  )}
                </div>
              )}

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
