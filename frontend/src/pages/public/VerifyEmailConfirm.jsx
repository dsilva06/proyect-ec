import { useEffect } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import BrandLockup from '../../components/shared/BrandLockup'

function normalizeVerificationUrl(rawUrl) {
  if (!rawUrl) return ''
  return rawUrl.trim()
}

export default function VerifyEmailConfirm() {
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    const params = new URLSearchParams(location.search)
    const verificationUrl = normalizeVerificationUrl(params.get('url') || params.get('verify_url'))

    if (!verificationUrl) {
      navigate('/verify-email?status=invalid_or_expired', { replace: true })
      return
    }

    navigate(`/?verify_url=${encodeURIComponent(verificationUrl)}`, { replace: true })
  }, [location.search, navigate])

  return (
    <div className="page auth-page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav auth-nav">
        <BrandLockup subtitle="Centro de verificación" />
        <div className="nav-auth-actions">
          <Link className="ghost-button" to="/login">Iniciar sesión</Link>
          <span className="tag muted">Verificando</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <h2>Verificando correo</h2>
              <p className="muted">Estamos validando tu enlace de verificación...</p>
              <p className="auth-loading">Espera un momento.</p>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
