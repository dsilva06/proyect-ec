import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { writeVerificationContext } from '../../auth/storage'
import { useAuth } from '../../auth/useAuth'
import { authApi } from '../../features/auth/api'
import { publicLeadsApi } from '../../features/leads/api'
import { publicTournamentsApi } from '../../features/tournaments/api'
import BrandLockup from '../../components/shared/BrandLockup'
import '../../App.css'

const categories = [
  { name: 'Masculino Abierto', rule: 'Categoría OPEN — árbitro asigna nivel' },
  { name: 'Masculino 1era', rule: 'Nivel avanzado' },
  { name: 'Masculino 2da', rule: 'Nivel intermedio' },
  { name: 'Femenino Abierto', rule: 'Categoría OPEN — árbitro asigna nivel' },
  { name: 'Femenino 1era', rule: 'Nivel avanzado' },
  { name: 'Femenino 2da', rule: 'Nivel intermedio' },
]

const steps = [
  {
    title: 'Crea tu perfil',
    detail: 'Registra tu cuenta y deja listo tu acceso para competir en el torneo.',
  },
  {
    title: 'Elige tu categoría',
    detail: 'Revisa las divisiones disponibles y postúlate en la categoría correcta.',
  },
  {
    title: 'Confirma tu cupo',
    detail: 'Completa el pago dentro de la ventana de aceptación para asegurar tu lugar.',
  },
  {
    title: 'Sigue la competencia',
    detail: 'Consulta horarios, cuadros y resultados a medida que avanza el torneo.',
  },
]

const normalizeCollection = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

const formatDate = (value) => {
  if (!value) return 'Por confirmar'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return 'Por confirmar'
  return parsed.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const formatDateTime = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  return parsed.toLocaleString('es-ES', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

const formatRange = (start, end) => {
  const startLabel = formatDate(start)
  const endLabel = formatDate(end)
  if (startLabel === 'Por confirmar' && endLabel === 'Por confirmar') return 'Por confirmar'
  if (startLabel !== 'Por confirmar' && endLabel !== 'Por confirmar') return `${startLabel} – ${endLabel}`
  return startLabel !== 'Por confirmar' ? startLabel : endLabel
}

const formatRegistrationWindow = (start, end) => {
  const startLabel = formatDateTime(start)
  const endLabel = formatDateTime(end)
  if (!startLabel && !endLabel) return 'Por confirmar'
  if (startLabel && endLabel) return `${startLabel} – ${endLabel}`
  return startLabel || endLabel
}

const statusPriority = (statusCode) => {
  if (statusCode === 'registration_open') return 0
  if (statusCode === 'published') return 1
  return 10
}

const TOURNAMENT_STATUS_LABELS_ES = {
  draft: 'Borrador',
  published: 'Publicado',
  registration_open: 'Inscripción abierta',
  registration_closed: 'Inscripción cerrada',
  in_progress: 'En curso',
  completed: 'Completado',
  cancelled: 'Cancelado',
}

const getTournamentStatusLabel = (status) => {
  if (!status?.code) return 'Publicado'
  return TOURNAMENT_STATUS_LABELS_ES[status.code] || status.label || 'Publicado'
}

const pickCurrentTournament = (tournaments) => {
  if (!Array.isArray(tournaments) || tournaments.length === 0) return null

  const sorted = [...tournaments].sort((a, b) => {
    const statusDiff = statusPriority(a?.status?.code) - statusPriority(b?.status?.code)
    if (statusDiff !== 0) return statusDiff

    const aTime = new Date(a?.start_date || 0).getTime()
    const bTime = new Date(b?.start_date || 0).getTime()
    const safeATime = Number.isFinite(aTime) ? aTime : Number.MAX_SAFE_INTEGER
    const safeBTime = Number.isFinite(bTime) ? bTime : Number.MAX_SAFE_INTEGER
    return safeATime - safeBTime
  })

  return sorted[0] || null
}

export default function Home() {
  const location = useLocation()
  const { user, logout } = useAuth()
  const [tournaments, setTournaments] = useState([])
  const [tournamentsLoading, setTournamentsLoading] = useState(true)
  const [verificationState, setVerificationState] = useState({
    status: 'idle',
    message: '',
    name: '',
  })
  const [contactForm, setContactForm] = useState({
    full_name: '',
    email: '',
    company: '',
    message: '',
  })
  const [contactStatus, setContactStatus] = useState('')
  const verificationParams = new URLSearchParams(location.search)
  const verificationUrl = verificationParams.get('verify_url') || verificationParams.get('url')
  // Do not show modal during verification flow
  const [showModal, setShowModal] = useState(!verificationUrl)
  const currentTournament = useMemo(() => pickCurrentTournament(tournaments), [tournaments])
  const currentCategoryCount = Array.isArray(currentTournament?.categories)
    ? currentTournament.categories.length
    : 0

  // Auth-aware inscription destination
  const inscripcionTo = user ? '/player/tournaments' : '/login'

  useEffect(() => {
    const updateScroll = () => {
      document.documentElement.style.setProperty('--scroll-y', `${window.scrollY}px`)
    }

    updateScroll()
    window.addEventListener('scroll', updateScroll, { passive: true })

    return () => window.removeEventListener('scroll', updateScroll)
  }, [])

  useEffect(() => {
    let ignore = false

    const loadTournaments = async () => {
      setTournamentsLoading(true)

      try {
        const response = await publicTournamentsApi.list()
        if (ignore) return
        setTournaments(normalizeCollection(response))
      } catch {
        if (ignore) return
        setTournaments([])
      } finally {
        if (!ignore) setTournamentsLoading(false)
      }
    }

    loadTournaments()

    return () => {
      ignore = true
    }
  }, [])

  useEffect(() => {
    const nodes = document.querySelectorAll('.reveal')
    if (!nodes.length) return undefined

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible')
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.18 },
    )

    nodes.forEach((node) => observer.observe(node))

    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (!verificationUrl) {
      setVerificationState({ status: 'idle', message: '' })
      return
    }

    let cancelled = false

    setVerificationState({
      status: 'processing',
      message: 'Estamos verificando tu correo...',
    })

    authApi
      .verifyEmailByUrl(verificationUrl)
      .then((data) => {
        if (cancelled) return

        writeVerificationContext(null)
        setVerificationState({
          status: 'success',
          message: data?.message || 'Tu correo fue verificado correctamente.',
          name: data?.name || '',
        })
      })
      .catch((error) => {
        if (cancelled) return

        const message =
          error?.data?.message ||
          error?.message ||
          'No pudimos verificar tu correo.'

        setVerificationState({
          status: 'error',
          message,
        })
      })

    return () => {
      cancelled = true
    }
  }, [verificationUrl])

  const handleContactSubmit = async (event) => {
    event.preventDefault()
    setContactStatus('')

    try {
      await publicLeadsApi.create({
        type: 'partnership',
        full_name: contactForm.full_name,
        email: contactForm.email,
        company: contactForm.company || undefined,
        message: contactForm.message,
        source: 'landing',
      })
      setContactForm({
        full_name: '',
        email: '',
        company: '',
        message: '',
      })
      setContactStatus('¡Gracias! Te responderemos en menos de 24 horas.')
    } catch (error) {
      setContactStatus(error?.data?.message || error?.message || 'No pudimos enviar el mensaje.')
    }
  }

  if (verificationState.status === 'success') {
    const params = new URLSearchParams({ status: 'verified' })
    if (verificationState.name) params.set('name', verificationState.name)

    return (
      <Navigate
        to={`/verify-email?${params.toString()}`}
        state={{ message: verificationState.message }}
        replace
      />
    )
  }

  if (verificationState.status === 'error') {
    return (
      <Navigate
        to="/verify-email?status=invalid_or_expired"
        state={{ message: verificationState.message }}
        replace
      />
    )
  }

  if (verificationState.status === 'processing') {
    return (
      <div className="page auth-page">
        <div className="background-orb orb-one" />
        <div className="background-orb orb-two" />
        <div className="background-grid" />

        <header className="nav auth-nav">
          <BrandLockup subtitle="Centro de verificación" />
        </header>

        <main>
          <section className="section auth-standalone">
            <div className="auth-shell single-card">
              <div className="auth-card">
                <h2>Verificando correo</h2>
                <p className="muted">{verificationState.message}</p>
                <p className="auth-loading">Espera un momento.</p>
              </div>
            </div>
          </section>
        </main>
      </div>
    )
  }

  return (
    <div className="page home-page">
      {/* Entry announcement modal */}
      {showModal && !user && (
        <div
          className="modal-backdrop"
          onClick={() => setShowModal(false)}
        >
          <div
            className="entry-modal-card"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              className="entry-modal-close"
              onClick={() => setShowModal(false)}
              type="button"
              aria-label="Cerrar"
            >
              ×
            </button>
            <div className="entry-modal-badge">1ª Edición · Mayo 2025</div>
            <h2>Inscripciones abiertas</h2>
            <p>
              Ya están abiertas las inscripciones para la primera edición de ESTARS PÁDEL TOUR.
              Regístrate o inicia sesión para asegurar tu plaza en el torneo del
              8, 9 y 10 de mayo en DUIN SPORTS Las Rozas.
            </p>
            <div className="entry-modal-actions">
              <Link
                className="primary-button"
                to="/login"
                onClick={() => setShowModal(false)}
              >
                Iniciar sesión
              </Link>
              <Link
                className="secondary-button"
                to="/register"
                onClick={() => setShowModal(false)}
              >
                Crear cuenta
              </Link>
            </div>
          </div>
        </div>
      )}

      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav home-nav">
        <BrandLockup subtitle="Circuito de pádel competitivo" />
        <nav className="nav-links">
          <a href="#edicion">1ª Edición</a>
          <a href="#categories">Categorías</a>
          <a href="#schedule">Cronograma</a>
          <a href="#contact">Contacto</a>
        </nav>
        <div className="nav-auth-actions">
          {user ? (
            <button className="secondary-button" onClick={logout} type="button">
              Cerrar sesión
            </button>
          ) : (
            <>
              <Link className="ghost-button" to="/login">Iniciar sesión</Link>
              <Link className="primary-button" to="/register">Registrarse</Link>
            </>
          )}
        </div>
      </header>

      <div className="hero-marquee home-marquee" aria-hidden="true">
        <span>ESTARS PADEL TOUR</span>
        <span>1ª EDICIÓN PRO</span>
        <span>8.000€ PRIZE MONEY</span>
        <span>DUIN SPORTS LAS ROZAS</span>
      </div>

      <main className="landing-main home-main">
        {/* Hero */}
        <section className="home-hero reveal">
          <div className="home-hero-main hero-copy">
            <div className="pill">Inscripciones abiertas · Mayo 2025</div>
            <h1>ESTARS PÁDEL TOUR</h1>
            <p>
              ESTARS PÁDEL TOUR es un circuito de transición profesional que dará paso a su 1era edición PRO este
              8, 9 y 10 de mayo en DUIN SPORTS Las Rozas. Un circuito pensado para quienes buscan llevar su carrera
              deportiva al siguiente nivel. En esta primera edición se repartirán 8.000€ en Prize Money + material
              deportivo, en categorías OPEN, 1era y 2da masculina y femenina. Todo está preparado para que la élite
              del pádel amateur y la competición profesional se fusionen en un mismo escenario y te impulsen al
              próximo nivel.
            </p>
            <div className="hero-actions">
              <Link className="primary-button" to={inscripcionTo}>Inscribirme</Link>
              {!user && <Link className="secondary-button" to="/login">Iniciar sesión</Link>}
              <a className="ghost-button" href="#edicion">Ver torneo actual</a>
            </div>
            <div className="hero-stats home-stats-row">
              <div>
                <strong>8.000€</strong>
                <span>Prize Money</span>
              </div>
              <div>
                <strong>{tournamentsLoading ? '...' : (currentCategoryCount || 6)}</strong>
                <span>Categorías</span>
              </div>
              <div>
                <strong>8–10 May</strong>
                <span>Las Rozas</span>
              </div>
            </div>
          </div>
        </section>

        {/* Edition key highlights */}
        <section className="home-signal-grid reveal">
          <article className="home-signal-card">
            <span className="tag accent">Prize Money</span>
            <h3>8.000€ + material deportivo</h3>
            <p className="card-detail">La primera edición reparte premio en metálico y material deportivo entre los ganadores de cada categoría.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">Fecha y sede</span>
            <h3>8, 9 y 10 de mayo — DUIN SPORTS Las Rozas</h3>
            <p className="card-detail">Tres días de competencia en las instalaciones de DUIN SPORTS, Las Rozas de Madrid.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">Categorías</span>
            <h3>OPEN · 1ª · 2ª — Masculino y Femenino</h3>
            <p className="card-detail">Seis divisiones para que compitas en el nivel que te corresponde. Admisión gestionada con criterio.</p>
          </article>
        </section>

        {/* Current edition spotlight */}
        <section id="edicion" className="section section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Edición activa</span>
            <h2>1ª Edición ESTARS PÁDEL TOUR</h2>
            <p>Una sola edición. Un solo foco. Asegura tu plaza antes de que se cierre el cuadro.</p>
          </div>

          <div className="home-edition-spotlight">
            {tournamentsLoading ? (
              <div className="edition-spotlight-card">
                <div className="edition-spotlight-header">
                  <div>
                    <div className="entry-modal-badge">Cargando...</div>
                    <h2 style={{ marginTop: '12px' }}>Sincronizando datos del torneo</h2>
                  </div>
                </div>
              </div>
            ) : currentTournament ? (
              <div className="edition-spotlight-card">
                <div className="edition-spotlight-header">
                  <div>
                    <span className="tag accent">{getTournamentStatusLabel(currentTournament.status)}</span>
                    <h2 style={{ marginTop: '12px' }}>{currentTournament.name}</h2>
                  </div>
                </div>

                <div className="edition-spotlight-meta">
                  <div className="edition-spotlight-meta-item">
                    <span>Fechas</span>
                    <strong>{formatRange(currentTournament.start_date, currentTournament.end_date)}</strong>
                  </div>
                  <div className="edition-spotlight-meta-item">
                    <span>Sede</span>
                    <strong>
                      {currentTournament.venue_name || 'DUIN SPORTS'}
                      {currentTournament.city ? ` · ${currentTournament.city}` : ' · Las Rozas'}
                    </strong>
                  </div>
                  <div className="edition-spotlight-meta-item edition-spotlight-prize">
                    <span>Prize Money</span>
                    <strong>8.000€ + material</strong>
                  </div>
                  <div className="edition-spotlight-meta-item">
                    <span>Categorías</span>
                    <strong>{currentCategoryCount > 0 ? `${currentCategoryCount} divisiones` : '6 divisiones'}</strong>
                  </div>
                  {(currentTournament.registration_open_at || currentTournament.registration_close_at) && (
                    <div className="edition-spotlight-meta-item">
                      <span>Inscripción</span>
                      <strong>{formatRegistrationWindow(
                        currentTournament.registration_open_at,
                        currentTournament.registration_close_at,
                      )}</strong>
                    </div>
                  )}
                </div>

                <div className="edition-spotlight-actions">
                  <Link className="primary-button" to={inscripcionTo}>Inscribirme</Link>
                  <Link className="secondary-button" to="/tournament">Ver detalles</Link>
                </div>
              </div>
            ) : (
              <div className="edition-spotlight-card">
                <div className="edition-spotlight-header">
                  <div>
                    <span className="tag accent">Próximamente</span>
                    <h2 style={{ marginTop: '12px' }}>1ª Edición ESTARS PÁDEL TOUR</h2>
                  </div>
                </div>
                <div className="edition-spotlight-meta">
                  <div className="edition-spotlight-meta-item">
                    <span>Fechas</span>
                    <strong>8, 9 y 10 de mayo 2025</strong>
                  </div>
                  <div className="edition-spotlight-meta-item">
                    <span>Sede</span>
                    <strong>DUIN SPORTS · Las Rozas</strong>
                  </div>
                  <div className="edition-spotlight-meta-item edition-spotlight-prize">
                    <span>Prize Money</span>
                    <strong>8.000€ + material</strong>
                  </div>
                  <div className="edition-spotlight-meta-item">
                    <span>Categorías</span>
                    <strong>6 divisiones</strong>
                  </div>
                </div>
                <div className="edition-spotlight-actions">
                  <Link className="primary-button" to={inscripcionTo}>Inscribirme</Link>
                  <a className="ghost-button" href="#contact">Solicitar información</a>
                </div>
              </div>
            )}
          </div>
        </section>

        {/* How it works */}
        <section className="section flow section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Cómo funciona</span>
            <h2>Tu camino al torneo</h2>
            <p>Cuatro pasos. Sin burocracia. Todo lo que necesitas para competir.</p>
          </div>
          <div className="steps">
            {steps.map((step, index) => (
              <div className="step" key={step.title} style={{ '--delay': `${index * 0.1}s` }}>
                <span className="step-number">0{index + 1}</span>
                <h3>{step.title}</h3>
                <p>{step.detail}</p>
              </div>
            ))}
          </div>
        </section>

        {/* Categories */}
        <section id="categories" className="section section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Divisiones</span>
            <h2>Categorías de la 1ª edición</h2>
            <p>Seis divisiones diseñadas para una competencia ordenada, con criterios claros y nivel parejo.</p>
          </div>
          <div className="category-grid">
            {categories.map((category) => (
              <div className="category" key={category.name}>
                <div>
                  <h3>{category.name}</h3>
                  <p>{category.rule}</p>
                </div>
                <Link className="secondary-button" to={inscripcionTo}>Inscribirme</Link>
              </div>
            ))}
          </div>
        </section>

        {/* Schedule */}
        <section id="schedule" className="section section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Competencia</span>
            <h2>Cronograma de partidos</h2>
            <p>La programación oficial se publica tras el cierre del cuadro y se actualiza en tiempo real durante cada jornada.</p>
          </div>
          <div className="schedule">
            <div className="schedule-item">
              <span className="tag">8–10 Mayo · Las Rozas</span>
              <div>
                <h3>Agenda oficial del torneo</h3>
                <p>Horarios, canchas y rondas se confirman una vez armado el cuadro definitivo.</p>
                <p className="muted">Cada jornada se actualiza en tiempo real. Consulta aquí antes de ir a jugar.</p>
              </div>
            </div>
          </div>
          <Link className="primary-button" to={inscripcionTo}>Asegurar mi plaza</Link>
        </section>

        {/* Contact / Alliances */}
        <section id="contact" className="section contact section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Alianzas</span>
            <h2>Trabaja con nosotros</h2>
            <p>Si tienes una cancha, una marca o una idea para hacer crecer el torneo, queremos escucharte.</p>
          </div>
          <div className="contact-grid">
            <form className="contact-form" onSubmit={handleContactSubmit}>
              <label>
                Nombre completo
                <input
                  type="text"
                  placeholder="Tu nombre"
                  value={contactForm.full_name}
                  onChange={(event) =>
                    setContactForm((prev) => ({ ...prev, full_name: event.target.value }))
                  }
                />
              </label>
              <label>
                Correo
                <input
                  type="email"
                  placeholder="nombre@correo.com"
                  value={contactForm.email}
                  onChange={(event) =>
                    setContactForm((prev) => ({ ...prev, email: event.target.value }))
                  }
                />
              </label>
              <label>
                Empresa
                <input
                  type="text"
                  placeholder="Opcional"
                  value={contactForm.company}
                  onChange={(event) =>
                    setContactForm((prev) => ({ ...prev, company: event.target.value }))
                  }
                />
              </label>
              <label>
                Mensaje
                <textarea
                  rows="4"
                  placeholder="Cuéntanos tu idea"
                  value={contactForm.message}
                  onChange={(event) =>
                    setContactForm((prev) => ({ ...prev, message: event.target.value }))
                  }
                />
              </label>
              <button className="primary-button" type="submit">Enviar mensaje</button>
              {contactStatus && <p className="form-message">{contactStatus}</p>}
            </form>
            <div className="contact-panel">
              <h3>¿Quieres formar parte del crecimiento del torneo?</h3>
              <p>
                Respondemos dentro de 24 horas. También trabajamos con clubes, marcas y aliados
                que quieren impulsar una experiencia competitiva cada vez más sólida.
              </p>
              <div className="contact-details">
                <div>
                  <span>Inscripciones y contacto</span>
                  <strong>inscripciones@estarspadeltour.com</strong>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="footer home-footer">
        <div>
          <strong>ESTARS PADEL TOUR</strong>
          <span>1ª Edición PRO · 8, 9 y 10 de mayo · DUIN SPORTS Las Rozas</span>
        </div>
        <div className="footer-links">
          <a href="#">Privacidad</a>
          <a href="#">Términos</a>
          <a href="#">Estado</a>
        </div>
      </footer>
    </div>
  )
}
