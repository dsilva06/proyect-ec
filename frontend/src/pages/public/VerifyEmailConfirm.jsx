import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { authApi } from '../../features/auth/api'

function normalizeVerificationUrl(rawUrl) {
  if (!rawUrl) return ''

  try {
    return decodeURIComponent(rawUrl).trim()
  } catch {
    return rawUrl.trim()
  }
}

export default function VerifyEmailConfirm() {
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    let cancelled = false

    async function verify() {
      const params = new URLSearchParams(location.search)
      const verificationUrl = normalizeVerificationUrl(params.get('url'))

      if (!verificationUrl) {
        navigate('/verify-email?status=invalid_or_expired', { replace: true })
        return
      }

      try {
        await authApi.verifyEmailByUrl(verificationUrl)
        if (!cancelled) {
          navigate('/verify-email?status=verified', { replace: true })
        }
      } catch {
        if (!cancelled) {
          navigate('/verify-email?status=invalid_or_expired', { replace: true })
        }
      }
    }

    verify()

    return () => {
      cancelled = true
    }
  }, [location.search, navigate])

  return (
    <div className="page auth-page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

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
