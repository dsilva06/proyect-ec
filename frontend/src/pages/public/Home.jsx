import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { publicLeadsApi } from '../../features/leads/api'
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
  const { user, logout } = useAuth()
  const [contactForm, setContactForm] = useState({
    full_name: '',
    email: '',
    company: '',
    message: '',
  })
  const [contactStatus, setContactStatus] = useState('')

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

  return (
    <div className="page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav">
        <div className="brand">
          <span className="brand-mark">ESTARS PADEL TOUR</span>
          <span className="brand-subtitle">Tournament Hub</span>
        </div>
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

      <main>
        <section className="hero reveal">
          <div className="hero-copy">
            <div className="pill">Web demo for players</div>
            <h1>Register, rank, and play. Your padel season starts here.</h1>
            <p>
              A modern padel tournament experience with ranking-based acceptance,
              transparent waitlists, and daily-updated draws. Built for players
              first, ready for mobile later.
            </p>
            <div className="hero-actions">
              <Link className="primary-button" to="/register">Create account</Link>
              <Link className="secondary-button" to="/login">Login</Link>
              <a className="ghost-button" href="#contact">Contact us</a>
            </div>
          </div>
          <div className="hero-card">
            <div className="hero-card-header">
              <h3>Registration snapshot</h3>
              <span className="tag muted">Player view</span>
            </div>
            <div className="hero-card-body">
              <div className="row">
                <span>Ranking source</span>
                <strong>FEP / FIP</strong>
              </div>
              <div className="row">
                <span>Waitlist logic</span>
                <strong>Top by ranking</strong>
              </div>
              <div className="row">
                <span>Payment window</span>
                <strong>Enabled after acceptance</strong>
              </div>
              <div className="row">
                <span>Draw updates</span>
                <strong>Posted daily</strong>
              </div>
            </div>
          </div>
        </section>

        <section id="tournaments" className="section reveal">
          <div className="section-title">
            <h2>Current tournament</h2>
            <p>
              We are launching with a single tournament. More events will be
              added later.
            </p>
          </div>
          <div className="card-grid">
            <article className="card">
              <div className="card-header">
                <h3>Tournament name: TBD</h3>
                <span className="tag muted">Announcing soon</span>
              </div>
              <p className="card-detail">Dates: to be confirmed.</p>
              <p className="card-detail">Venue: to be confirmed.</p>
              <p className="card-detail emphasis">Modalidad: pro / amateur.</p>
              <a className="ghost-button full" href="#contact">Get updates</a>
            </article>
          </div>
        </section>

        <section className="section flow reveal">
          <div className="section-title">
            <h2>Player flow</h2>
            <p>Every step is clear, fair, and fast.</p>
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

        <section id="categories" className="section reveal">
          <div className="section-title">
            <h2>Categories</h2>
            <p>Six divisions, clear rules, flexible capacity.</p>
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

        <section id="schedule" className="section reveal">
          <div className="section-title">
            <h2>Match schedule</h2>
            <p>Published once the draw is ready. Updated daily during play.</p>
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

        <section id="contact" className="section contact reveal">
          <div className="section-title">
            <h2>Contact & partnerships</h2>
            <p>Let us know if you want to sponsor or collaborate.</p>
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

      <footer className="footer">
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
