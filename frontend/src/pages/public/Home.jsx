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
  { name: 'Masculino Abierto', rule: 'Nivel abierto' },
  { name: 'Masculino 1era', rule: 'Nivel avanzado' },
  { name: 'Masculino 2da', rule: 'Nivel intermedio' },
  { name: 'Femenino Abierto', rule: 'Nivel abierto' },
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
  if (startLabel !== 'Por confirmar' && endLabel !== 'Por confirmar') return `${startLabel} - ${endLabel}`
  return startLabel !== 'Por confirmar' ? startLabel : endLabel
}

const formatRegistrationWindow = (start, end) => {
  const startLabel = formatDateTime(start)
  const endLabel = formatDateTime(end)
  if (!startLabel && !endLabel) return 'Por confirmar'
  if (startLabel && endLabel) return `${startLabel} - ${endLabel}`
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
  const [tournamentsError, setTournamentsError] = useState('')
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
  const currentTournament = useMemo(() => pickCurrentTournament(tournaments), [tournaments])
  const currentCategoryCount = Array.isArray(currentTournament?.categories) ? currentTournament.categories.length : 0

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
      setTournamentsError('')

      try {
        const response = await publicTournamentsApi.list()
        if (ignore) return
        setTournaments(normalizeCollection(response))
      } catch (error) {
        if (ignore) return
        setTournaments([])
        setTournamentsError(error?.message || 'No pudimos cargar los torneos actuales.')
      } finally {
        if (!ignore) {
          setTournamentsLoading(false)
        }
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
    const params = new URLSearchParams({
      status: 'verified',
    })

    if (verificationState.name) {
      params.set('name', verificationState.name)
    }

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
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav home-nav">
        <BrandLockup subtitle="Circuito competitivo" />
        <nav className="nav-links">
          <a href="#tournaments">Torneo</a>
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
        <span>COMPETENCIA RANKEADA</span>
        <span>CUADROS EN VIVO</span>
        <span>ESTARS PADEL TOUR</span>
      </div>

      <main className="landing-main home-main">
        <section className="home-hero reveal">
          <div className="home-hero-main hero-copy">
            <div className="pill">Temporada activa</div>
            <h1>Un torneo de pádel con visión de circuito, construido para competir con seriedad.</h1>
            <p>
              ESTARS PADEL TOUR organiza inscripciones, validación, pagos, categorías, cuadros y actualizaciones
              de jornada en una sola experiencia. Queremos crecer hasta convertirnos en una referencia regional
              del pádel competitivo, con una identidad y unos estándares cada vez más altos.
            </p>
            <div className="hero-actions">
              <Link className="primary-button" to="/register">Crear cuenta</Link>
              <Link className="secondary-button" to="/login">Iniciar sesión</Link>
              <a className="ghost-button" href="#contact">Hablar con nosotros</a>
            </div>
            <div className="hero-stats home-stats-row">
              <div>
                <strong>{tournamentsLoading ? '...' : tournaments.length}</strong>
                <span>Torneos públicos</span>
              </div>
              <div>
                <strong>{tournamentsLoading ? '...' : currentCategoryCount}</strong>
                <span>Categorías activas</span>
              </div>
              <div>
                <strong>24h</strong>
                <span>Soporte operativo</span>
              </div>
            </div>
          </div>
        </section>

        <section className="home-signal-grid reveal">
          <article className="home-signal-card">
            <span className="tag muted">01</span>
            <h3>Estructura de torneo</h3>
            <p className="card-detail">Inscripciones, categorías y cuadros alineados con una operación ordenada.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">02</span>
            <h3>Criterio competitivo</h3>
            <p className="card-detail">Ranking, aceptación y cupos con reglas claras para cada categoría.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">03</span>
            <h3>Competencia en tiempo real</h3>
            <p className="card-detail">Los cuadros, horarios y resultados se reflejan al ritmo del torneo.</p>
          </article>
        </section>

        <section id="tournaments" className="section section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Temporada actual</span>
            <h2>Torneo destacado</h2>
            <p>Información pública del torneo activo, publicada directamente desde administración.</p>
          </div>
          <div className="card-grid home-tournament-grid">
            {tournamentsLoading ? (
              <article className="card card-featured">
                <div className="card-header">
                  <h3>Cargando torneo actual...</h3>
                  <span className="tag muted">Sincronizando</span>
                </div>
                <p className="card-detail">Actualizando la información oficial del torneo.</p>
              </article>
            ) : currentTournament ? (
              <>
                <article className="card card-featured">
                  <div className="card-header">
                    <h3>{currentTournament.name}</h3>
                    <span className="tag muted">{getTournamentStatusLabel(currentTournament.status)}</span>
                  </div>
                  <p className="card-detail">Fechas: {formatRange(currentTournament.start_date, currentTournament.end_date)}</p>
                  <p className="card-detail">
                    Sede: {currentTournament.venue_name || 'Por confirmar'}
                    {currentTournament.city ? ` · ${currentTournament.city}` : ''}
                  </p>
                  <Link className="secondary-button full" to="/tournament">Ver torneo</Link>
                </article>

                <article className="card home-mini-brief">
                  <div className="card-header">
                    <h3>Estado del torneo</h3>
                    <span className="tag muted">En vivo</span>
                  </div>
                  <p className="card-detail">Inscripción: {formatRegistrationWindow(
                    currentTournament.registration_open_at,
                    currentTournament.registration_close_at,
                  )}</p>
                  <p className="card-detail">Categorías configuradas: {currentCategoryCount}</p>
                  <p className="card-detail">
                    Horario de juego: {currentTournament.day_start_time || '--:--'} - {currentTournament.day_end_time || '--:--'}
                  </p>
                </article>
              </>
            ) : (
              <>
                <article className="card card-featured">
                  <div className="card-header">
                    <h3>No hay torneos publicados todavía</h3>
                    <span className="tag muted">Pendiente</span>
                  </div>
                  <p className="card-detail">Cuando se publique un torneo o se abran inscripciones, aparecerá aquí automáticamente.</p>
                  <a className="ghost-button full" href="#contact">Solicitar aviso</a>
                </article>
                <article className="card home-mini-brief">
                  <div className="card-header">
                    <h3>Estado de publicación</h3>
                    <span className="tag muted">{tournamentsError ? 'Error' : 'Sin datos'}</span>
                  </div>
                  <p className="card-detail">
                    {tournamentsError || 'Todavía no hay un torneo visible en la vitrina pública.'}
                  </p>
                </article>
              </>
            )}
          </div>
        </section>

        <section className="section flow section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Cómo funciona</span>
            <h2>Flujo del jugador</h2>
            <p>Un recorrido claro desde el registro hasta la competencia.</p>
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

        <section id="categories" className="section section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Divisiones</span>
            <h2>Categorías competitivas</h2>
            <p>Divisiones definidas para sostener un torneo ordenado, competitivo y con criterios claros.</p>
          </div>
          <div className="category-grid">
            {categories.map((category) => (
              <div className="category" key={category.name}>
                <div>
                  <h3>{category.name}</h3>
                  <p>{category.rule}</p>
                </div>
                <button className="secondary-button">Ver categoría</button>
              </div>
            ))}
          </div>
        </section>

        <section id="schedule" className="section section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Competencia</span>
            <h2>Cronograma de partidos</h2>
            <p>Se publica cuando el cuadro está cerrado y se actualiza durante cada jornada de competencia.</p>
          </div>
          <div className="schedule">
            <div className="schedule-item">
              <span className="tag">Cronograma</span>
              <div>
                <h3>Programación oficial</h3>
                <p>Los horarios y canchas se anuncian después del cierre y armado del cuadro.</p>
                <p className="muted">Las actualizaciones se publican a medida que avanza el torneo.</p>
              </div>
            </div>
          </div>
          <a className="primary-button" href="#contact">Solicitar información</a>
        </section>

        <section id="contact" className="section contact section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Alianzas</span>
            <h2>Contacto y alianzas</h2>
            <p>Cuéntanos sobre patrocinios, sedes o colaboraciones para seguir elevando el torneo.</p>
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
                  <span>Soporte</span>
                  <strong>support@estarspadeltour.com</strong>
                </div>
                <div>
                  <span>WhatsApp</span>
                  <strong>+1 555 0199</strong>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="footer home-footer">
        <div>
          <strong>ESTARS PADEL TOUR</strong>
          <span>Un torneo con ambición de circuito y estándares cada vez más altos.</span>
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
