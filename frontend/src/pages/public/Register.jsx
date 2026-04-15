import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { inviteStorage } from '../../auth/inviteStorage'
import { writeVerificationContext } from '../../auth/storage'
import { publicTeamInvitesApi } from '../../features/teamInvites/api'
import BrandLockup from '../../components/shared/BrandLockup'

const PHONE_COUNTRY_OPTIONS = [
  { code: 'VE', label: 'Venezuela', dial: '+58', localLength: 10, placeholder: '4121234567' },
  { code: 'CO', label: 'Colombia', dial: '+57', localLength: 10, placeholder: '3001234567' },
  { code: 'US', label: 'Estados Unidos', dial: '+1', localLength: 10, placeholder: '2025550147' },
  { code: 'ES', label: 'España', dial: '+34', localLength: 9, placeholder: '612345678' },
]

const DOCUMENT_TYPE_OPTIONS = [
  {
    value: 'DNI',
    label: 'DNI',
    placeholder: '12345678Z',
    hint: '8 números y una letra final.',
    maxLength: 9,
  },
  {
    value: 'NIE',
    label: 'NIE',
    placeholder: 'X1234567L',
    hint: 'Una letra inicial, 7 números y una letra final.',
    maxLength: 9,
  },
  {
    value: 'PASSPORT',
    label: 'Pasaporte',
    placeholder: 'AB123456',
    hint: 'Entre 5 y 20 letras o números.',
    maxLength: 20,
  },
]

const DEFAULT_PHONE_COUNTRY = PHONE_COUNTRY_OPTIONS[0]

function onlyDigits(value, maxLength) {
  return value.replace(/\D/g, '').slice(0, maxLength)
}

function normalizeDocumentNumber(value, maxLength) {
  return value
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, maxLength)
}

function findPhoneCountry(dialCode) {
  return PHONE_COUNTRY_OPTIONS.find((country) => country.dial === dialCode) ?? DEFAULT_PHONE_COUNTRY
}

function findDocumentType(type) {
  return DOCUMENT_TYPE_OPTIONS.find((option) => option.value === type) ?? DOCUMENT_TYPE_OPTIONS[0]
}

export default function Register() {
  const navigate = useNavigate()
  const { register } = useAuth()
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    document_type: 'DNI',
    document_number: '',
    email: '',
    phone_country: DEFAULT_PHONE_COUNTRY.dial,
    phone_local: '',
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
        setInviteError('No pudimos cargar la invitación. Intenta nuevamente.')
        inviteStorage.clearToken()
      })

    return () => {
      isMounted = false
    }
  }, [])

  const selectedPhoneCountry = findPhoneCountry(form.phone_country)
  const selectedDocumentType = findDocumentType(form.document_type)

  const buildRegisterPayload = () => {
    const documentNumber = normalizeDocumentNumber(form.document_number, selectedDocumentType.maxLength)
    const documentPatterns = {
      DNI: /^\d{8}[A-Z]$/,
      NIE: /^[XYZ]\d{7}[A-Z]$/,
      PASSPORT: /^[A-Z0-9]{5,20}$/,
    }

    if (!documentPatterns[form.document_type]?.test(documentNumber)) {
      setError(`El documento no tiene un formato válido. ${selectedDocumentType.hint}`)
      return null
    }

    const phoneLocal = onlyDigits(form.phone_local, selectedPhoneCountry.localLength)
    let normalizedPhone = ''

    if (phoneLocal) {
      if (phoneLocal.length !== selectedPhoneCountry.localLength) {
        setError(
          `El número de ${selectedPhoneCountry.label} debe tener ${selectedPhoneCountry.localLength} dígitos.`,
        )
        return null
      }

      normalizedPhone = `${selectedPhoneCountry.dial}${phoneLocal}`
    }

    return {
      first_name: form.first_name,
      last_name: form.last_name,
      document_type: form.document_type,
      document_number: documentNumber,
      email: form.email,
      phone: normalizedPhone,
      password: form.password,
      password_confirmation: form.password_confirmation,
    }
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isSubmitting) return

    setError('')
    setInviteError('')
    setIsSubmitting(true)

    try {
      const payload = buildRegisterPayload()
      if (!payload) {
        setIsSubmitting(false)
        return
      }

      const data = await register(payload)
      writeVerificationContext(data?.verification_context || null)

      navigate('/verify-email', {
        replace: true,
        state: {
          email: form.email,
          message: data?.message,
          verificationContext: data?.verification_context || null,
        },
      })
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos completar el registro.')
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
          <Link className="ghost-button" to="/login">Iniciar sesión</Link>
          <span className="tag muted">Acceso de jugadores</span>
        </div>
      </header>

      <main>
        <section className="section auth-standalone">
          <div className="auth-shell single-card">
            <div className="auth-card">
              <div className="auth-modern-grid">
                <div className="auth-form-panel">
                  <h2>Crear cuenta</h2>
                  <p className="muted">Registra tu perfil para unirte al torneo y gestionar tu participación.</p>

                  {invite && (
                    <div className="auth-status">
                      <span className="tag">Invitación pendiente</span>
                      <p><strong>{invite.captain_name || 'Tu pareja'}</strong> te invitó a jugar {invite.tournament_name || 'el torneo'}.</p>
                      <p>Categoría: <strong>{invite.category_name || 'Por confirmar'}</strong>.</p>
                      <p>Usa este correo para que la invitación quede asociada automáticamente a tu perfil cuando verifiques la cuenta.</p>
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
                      Documento
                      <div className="auth-inline-input">
                        <select
                          value={form.document_type}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              document_type: event.target.value,
                              document_number: '',
                            })}
                          disabled={isSubmitting}
                          required
                        >
                          {DOCUMENT_TYPE_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                          ))}
                        </select>
                        <input
                          type="text"
                          inputMode="text"
                          placeholder={selectedDocumentType.placeholder}
                          value={form.document_number}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              document_number: normalizeDocumentNumber(
                                event.target.value,
                                selectedDocumentType.maxLength,
                              ),
                            })}
                          maxLength={selectedDocumentType.maxLength}
                          disabled={isSubmitting}
                          required
                        />
                      </div>
                      <small className="auth-field-hint">
                        {selectedDocumentType.hint}
                      </small>
                    </label>
                    <label>
                      Correo
                      <input
                        type="email"
                        placeholder="nombre@email.com"
                        value={form.email}
                        disabled={Boolean(invite?.invited_email) || isSubmitting}
                        onChange={(event) => setForm({ ...form, email: event.target.value })}
                        required
                      />
                    </label>
                    <label>
                      Teléfono (opcional)
                      <div className="auth-inline-input">
                        <select
                          value={form.phone_country}
                          onChange={(event) => setForm({ ...form, phone_country: event.target.value })}
                          disabled={isSubmitting}
                        >
                          {PHONE_COUNTRY_OPTIONS.map((country) => (
                            <option key={country.code} value={country.dial}>
                              {country.label} ({country.dial})
                            </option>
                          ))}
                        </select>
                        <input
                          type="tel"
                          inputMode="numeric"
                          placeholder={selectedPhoneCountry.placeholder}
                          maxLength={selectedPhoneCountry.localLength}
                          value={form.phone_local}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              phone_local: onlyDigits(event.target.value, selectedPhoneCountry.localLength),
                            })}
                          disabled={isSubmitting}
                        />
                      </div>
                      <small className="auth-field-hint">
                        Selecciona país y escribe solo el número local.
                      </small>
                    </label>
                    <label>
                      Contraseña
                      <input
                        type="password"
                        placeholder="Mínimo 8 caracteres"
                        value={form.password}
                        onChange={(event) => setForm({ ...form, password: event.target.value })}
                        disabled={isSubmitting}
                        required
                      />
                    </label>
                    <label>
                      Confirmar contraseña
                      <input
                        type="password"
                        placeholder="Repite la contraseña"
                        value={form.password_confirmation}
                        onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                        disabled={isSubmitting}
                        required
                      />
                    </label>
                    <button className="primary-button auth-submit" type="submit" disabled={isSubmitting}>
                      {isSubmitting ? 'Creando cuenta...' : 'Registrarme'}
                    </button>
                  </form>

                  {inviteError && <p className="auth-error">{inviteError}</p>}
                  {error && <p className="auth-error">{error}</p>}

                  <p className="auth-switch">
                    ¿Ya tienes cuenta? <Link to="/login">Inicia sesión</Link>
                  </p>
                </div>

                <aside className="auth-side-panel">
                  <span className="tag muted">Inscripción segura</span>
                  <h3>Arranca tu temporada</h3>
                  <p>
                    Creamos tu cuenta, enviamos verificación y habilitamos acceso cuando el correo esté confirmado.
                  </p>
                  <div className="auth-side-list">
                    <p>• Vinculación automática con invitaciones pendientes</p>
                    <p>• Perfil listo para rankings y categorías</p>
                    <p>• Flujo de pago y aceptación integrado</p>
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
