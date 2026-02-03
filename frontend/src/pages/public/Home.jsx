import { useEffect, useState } from 'react'
import { useAuth } from '../../auth/AuthContext'
import '../../App.css'

const categories = [
  { name: 'Category 1', rule: 'Elite level' },
  { name: 'Category 2', rule: 'Advanced' },
  { name: 'Category 3', rule: 'Intermediate' },
  { name: 'Category 4', rule: 'Recreational' },
  { name: 'Women Open', rule: 'Women only' },
  { name: 'Mixed', rule: 'Mixed pairs' },
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

const loyalty = [
  {
    title: 'Earn points fast',
    detail: 'Get points for registrations, wins, and referrals.',
  },
  {
    title: 'Tier perks',
    detail: 'Move up from Bronze to Gold with priority acceptance windows.',
  },
  {
    title: 'Redeem rewards',
    detail: 'Use points for discounts, gear, or future entry fees.',
  },
]

export default function Home() {
  const { user, status, login, register, logout } = useAuth()
  const [loginForm, setLoginForm] = useState({ email: '', password: '' })
  const [registerForm, setRegisterForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  })
  const [authError, setAuthError] = useState('')

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

  const handleLogin = async (event) => {
    event.preventDefault()
    setAuthError('')
    try {
      await login(loginForm)
      setLoginForm({ email: '', password: '' })
    } catch (error) {
      setAuthError(error?.data?.message || error?.message || 'Login failed.')
    }
  }

  const handleRegister = async (event) => {
    event.preventDefault()
    setAuthError('')
    try {
      await register(registerForm)
      setRegisterForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
      })
    } catch (error) {
      setAuthError(error?.data?.message || error?.message || 'Registration failed.')
    }
  }

  return (
    <div className="page">
      <div className="background-orb orb-one" />
      <div className="background-orb orb-two" />
      <div className="background-grid" />

      <header className="nav">
        <div className="brand">
          <span className="brand-mark">PADEL CUP</span>
          <span className="brand-subtitle">Tournament Hub</span>
        </div>
        <nav className="nav-links">
          <a href="#auth">Account</a>
          <a href="#tournaments">Tournament</a>
          <a href="#categories">Categories</a>
          <a href="#schedule">Schedule</a>
          <a href="#loyalty">Loyalty</a>
          <a href="#contact">Contact</a>
        </nav>
        {user ? (
          <button className="secondary-button" onClick={logout} type="button">
            Log out
          </button>
        ) : (
          <a className="primary-button" href="#auth">Create Account</a>
        )}
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
              <a className="primary-button" href="#auth">Create account</a>
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

        <section id="auth" className="section auth reveal">
          <div className="section-title">
            <h2>Player account</h2>
            <p>Create your account or log in to join the tournament.</p>
          </div>
          <div className="auth-grid">
            <div className="auth-card">
              <h3>Sign up</h3>
              <form onSubmit={handleRegister}>
                <label>
                  First name
                  <input
                    type="text"
                    value={registerForm.first_name}
                    onChange={(event) =>
                      setRegisterForm({ ...registerForm, first_name: event.target.value })
                    }
                    placeholder="First name"
                  />
                </label>
                <label>
                  Last name
                  <input
                    type="text"
                    value={registerForm.last_name}
                    onChange={(event) =>
                      setRegisterForm({ ...registerForm, last_name: event.target.value })
                    }
                    placeholder="Last name"
                  />
                </label>
                <label>
                  Email
                  <input
                    type="email"
                    value={registerForm.email}
                    onChange={(event) =>
                      setRegisterForm({ ...registerForm, email: event.target.value })
                    }
                    placeholder="name@email.com"
                  />
                </label>
                <label>
                  Phone (optional)
                  <input
                    type="text"
                    value={registerForm.phone}
                    onChange={(event) =>
                      setRegisterForm({ ...registerForm, phone: event.target.value })
                    }
                    placeholder="+34 600 000 000"
                  />
                </label>
                <label>
                  Password
                  <input
                    type="password"
                    value={registerForm.password}
                    onChange={(event) =>
                      setRegisterForm({ ...registerForm, password: event.target.value })
                    }
                    placeholder="At least 8 characters"
                  />
                </label>
                <label>
                  Confirm password
                  <input
                    type="password"
                    value={registerForm.password_confirmation}
                    onChange={(event) =>
                      setRegisterForm({
                        ...registerForm,
                        password_confirmation: event.target.value,
                      })
                    }
                    placeholder="Repeat password"
                  />
                </label>
                <button className="primary-button" type="submit">
                  Create account
                </button>
              </form>
            </div>
            <div className="auth-card">
              <h3>Log in</h3>
              <form onSubmit={handleLogin}>
                <label>
                  Email
                  <input
                    type="email"
                    value={loginForm.email}
                    onChange={(event) =>
                      setLoginForm({ ...loginForm, email: event.target.value })
                    }
                    placeholder="name@email.com"
                  />
                </label>
                <label>
                  Password
                  <input
                    type="password"
                    value={loginForm.password}
                    onChange={(event) =>
                      setLoginForm({ ...loginForm, password: event.target.value })
                    }
                    placeholder="Your password"
                  />
                </label>
                <button className="secondary-button" type="submit">
                  Log in
                </button>
              </form>
              {user && (
                <div className="auth-status">
                  <span className="tag">Signed in</span>
                  <p>{user.name}</p>
                  <button className="ghost-button" type="button" onClick={logout}>
                    Log out
                  </button>
                </div>
              )}
            </div>
          </div>
          {authError && <p className="auth-error">{authError}</p>}
          {status === 'loading' && <p className="auth-loading">Checking session...</p>}
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
              <p className="card-detail emphasis">Mode: invitational / open / hybrid.</p>
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

        <section id="loyalty" className="section loyalty reveal">
          <div className="section-title">
            <h2>Loyalty program</h2>
            <p>Reward players who show up and compete.</p>
          </div>
          <div className="loyalty-grid">
            {loyalty.map((item) => (
              <div className="loyalty-card" key={item.title}>
                <h3>{item.title}</h3>
                <p>{item.detail}</p>
              </div>
            ))}
          </div>
        </section>

        <section id="contact" className="section contact reveal">
          <div className="section-title">
            <h2>Contact & partnerships</h2>
            <p>Let us know if you want to sponsor or collaborate.</p>
          </div>
          <div className="contact-grid">
            <form className="contact-form">
              <label>
                Full name
                <input type="text" placeholder="Your name" />
              </label>
              <label>
                Email
                <input type="email" placeholder="name@email.com" />
              </label>
              <label>
                Company
                <input type="text" placeholder="Optional" />
              </label>
              <label>
                Message
                <textarea rows="4" placeholder="Tell us about your idea" />
              </label>
              <button className="primary-button" type="button">Send message</button>
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
                  <strong>support@padelcup.com</strong>
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
          <strong>PADEL CUP</strong>
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
