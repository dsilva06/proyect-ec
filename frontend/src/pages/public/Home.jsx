import { useEffect, useState } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { publicLeadsApi } from '../../features/leads/api'
import BrandLockup from '../../components/shared/BrandLockup'
import '../../App.css'

const categories = [
  { name: 'Masculino Open', rule: 'Nivel abierto' },
  { name: 'Masculino 1era', rule: 'Nivel avanzado' },
  { name: 'Masculino 2da', rule: 'Nivel intermedio' },
  { name: 'Femenino Open', rule: 'Nivel abierto' },
  { name: 'Femenino 1era', rule: 'Nivel avanzado' },
  { name: 'Femenino 2da', rule: 'Nivel intermedio' },
]

const steps = [
  {
    title: 'Create your team',
    detail: 'Register your pair, complete player profiles, and set rankings.',
  },
  {
    title: 'Join a category',
    detail: 'Pick the category, see the waitlist rules, and confirm entry fee.',
  },
  {
    title: 'Pay and confirm',
    detail: 'Secure your place in the acceptance window with card payment.',
  },
  {
    title: 'Play the draw',
    detail: 'Daily-updated matches, courts, and live results.',
  },
]

export default function Home() {
  const location = useLocation()
  const { user, logout } = useAuth()
  const [contactForm, setContactForm] = useState({
    full_name: '',
    email: '',
    company: '',
    message: '',
  })
  const [contactStatus, setContactStatus] = useState('')
  const verificationUrl = new URLSearchParams(location.search).get('verify_url')

  useEffect(() => {
    const updateScroll = () => {
      document.documentElement.style.setProperty('--scroll-y', `${window.scrollY}px`)
    }

    updateScroll()
    window.addEventListener('scroll', updateScroll, { passive: true })

    return () => window.removeEventListener('scroll', updateScroll)
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

  if (verificationUrl) {
    return (
      <Navigate
        to={`/verify-email?url=${encodeURIComponent(verificationUrl)}`}
        replace
      />
    )
  }

  return (
    <div className="page home-page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav home-nav">
        <BrandLockup subtitle="Tournament Hub" />
        <nav className="nav-links">
          <a href="#tournaments">Tournament</a>
          <a href="#categories">Categories</a>
          <a href="#schedule">Schedule</a>
          <a href="#contact">Contact</a>
        </nav>
        <div className="nav-auth-actions">
          {user ? (
            <button className="secondary-button" onClick={logout} type="button">
              Cerrar sesión
            </button>
          ) : (
            <>
              <Link className="ghost-button" to="/login">Login</Link>
              <Link className="primary-button" to="/register">Sign up</Link>
            </>
          )}
        </div>
      </header>

      <div className="hero-marquee home-marquee" aria-hidden="true">
        <span>ESTARS PADEL TOUR</span>
        <span>RANKED COMPETITION</span>
        <span>LIVE DRAWS</span>
        <span>ESTARS PADEL TOUR</span>
      </div>

      <main className="landing-main home-main">
        <section className="home-hero reveal">
          <div className="home-hero-main hero-copy">
            <div className="pill">Season launch</div>
            <h1>One hub for registrations, rankings, draws, and match-day updates.</h1>
            <p>
              ESTARS centralizes the full tournament operation so players and organizers work in one clean flow:
              register, validate, pay, and compete with transparent status every day.
            </p>
            <div className="hero-actions">
              <Link className="primary-button" to="/register">Create account</Link>
              <Link className="secondary-button" to="/login">Login</Link>
              <a className="ghost-button" href="#contact">Talk to us</a>
            </div>
            <div className="hero-stats home-stats-row">
              <div>
                <strong>6</strong>
                <span>Active categories</span>
              </div>
              <div>
                <strong>Top Rank</strong>
                <span>Priority acceptance</span>
              </div>
              <div>
                <strong>24h</strong>
                <span>Ops support</span>
              </div>
            </div>
          </div>

          <aside className="hero-card home-ops-card">
            <div className="hero-card-header">
              <h3>Control Snapshot</h3>
              <span className="tag">Live Workflow</span>
            </div>
            <div className="hero-card-body">
              <div className="row">
                <span>Entry model</span>
                <strong>Ranking + waitlist</strong>
              </div>
              <div className="row">
                <span>Payment trigger</span>
                <strong>After acceptance</strong>
              </div>
              <div className="row">
                <span>Draw publication</span>
                <strong>Daily board updates</strong>
              </div>
              <div className="row">
                <span>Player communication</span>
                <strong>Email + status feed</strong>
              </div>
            </div>
            <div className="home-ops-actions">
              <Link className="secondary-button" to="/register">Join this season</Link>
              <a className="ghost-button" href="#schedule">See schedule flow</a>
            </div>
          </aside>
        </section>

        <section className="home-signal-grid reveal">
          <article className="home-signal-card">
            <span className="tag muted">01</span>
            <h3>Single source of truth</h3>
            <p className="card-detail">Registrations, categories, and payments sync in one panel.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">02</span>
            <h3>Fair acceptance model</h3>
            <p className="card-detail">Ranking-first logic and explicit pending/waitlist states.</p>
          </article>
          <article className="home-signal-card">
            <span className="tag muted">03</span>
            <h3>Fast operations</h3>
            <p className="card-detail">Admins update draws and players see status immediately.</p>
          </article>
        </section>

        <section id="tournaments" className="section section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Live Event Layer</span>
            <h2>Current Tournament</h2>
            <p>Single-event mode now, designed to expand into a full calendar.</p>
          </div>
          <div className="card-grid home-tournament-grid">
            <article className="card card-featured">
              <div className="card-header">
                <h3>Tournament name: TBD</h3>
                <span className="tag muted">Announcing soon</span>
              </div>
              <p className="card-detail">Dates: to be confirmed.</p>
              <p className="card-detail">Venue: to be confirmed.</p>
              <p className="card-detail emphasis">Mode: pro / amateur divisions.</p>
              <a className="ghost-button full" href="#contact">Get updates</a>
            </article>
            <article className="card home-mini-brief">
              <div className="card-header">
                <h3>Operator checklist</h3>
                <span className="tag muted">Readiness</span>
              </div>
              <p className="card-detail">Categories configured with capacity rules.</p>
              <p className="card-detail">Registration state and payment window linked.</p>
              <p className="card-detail">Draw publication and match boards prepared.</p>
            </article>
          </div>
        </section>

        <section className="section flow section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">How It Works</span>
            <h2>Player Flow</h2>
            <p>Every step is explicit, auditable, and designed to reduce friction.</p>
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
            <span className="section-kicker">Divisions</span>
            <h2>Competitive Categories</h2>
            <p>Six divisions with transparent entry rules and dynamic capacity.</p>
          </div>
          <div className="category-grid">
            {categories.map((category) => (
              <div className="category" key={category.name}>
                <div>
                  <h3>{category.name}</h3>
                  <p>{category.rule}</p>
                </div>
                <button className="secondary-button">Join</button>
              </div>
            ))}
          </div>
        </section>

        <section id="schedule" className="section section-block section-tone-solid reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Tournament Ops</span>
            <h2>Match Schedule</h2>
            <p>Published once draws are locked and updated daily during tournament days.</p>
          </div>
          <div className="schedule">
            <div className="schedule-item">
              <span className="tag">Schedule</span>
              <div>
                <h3>Published per tournament</h3>
                <p>Match times and courts will be announced after the draw.</p>
                <p className="muted">Updates appear daily during play.</p>
              </div>
            </div>
          </div>
          <a className="primary-button" href="#contact">Ask for calendar link</a>
        </section>

        <section id="contact" className="section contact section-block section-tone-ice reveal">
          <div className="section-title home-section-title">
            <span className="section-kicker">Partnerships</span>
            <h2>Contact & Partnerships</h2>
            <p>Tell us about sponsorships, venues, or strategic collaborations.</p>
          </div>
          <div className="contact-grid">
            <form className="contact-form" onSubmit={handleContactSubmit}>
              <label>
                Nombre completo
                <input
                  type="text"
                  placeholder="Your name"
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
                  placeholder="name@email.com"
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
              <h3>Need help registering?</h3>
              <p>
                Our team answers within 24 hours. We also support event
                organizers, clubs, and brands that want a premium experience.
              </p>
              <div className="contact-details">
                <div>
                  <span>Support</span>
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
          <span>Smart tournaments for modern players.</span>
        </div>
        <div className="footer-links">
          <a href="#">Privacy</a>
          <a href="#">Terms</a>
          <a href="#">Status</a>
        </div>
      </footer>
    </div>
  )
}
