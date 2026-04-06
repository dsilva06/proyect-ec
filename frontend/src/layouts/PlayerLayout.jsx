import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import BrandLockup from '../components/shared/BrandLockup'

const AUTH_WARNING_KEY = 'auth_login_warning'
const PLAYER_MENU_ITEMS = [
  { to: '/player', label: 'Resumen', shortLabel: 'Inicio', description: 'Lo clave de tu torneo, hoy', end: true },
  { to: '/player/profile', label: 'Perfil', shortLabel: 'Perfil', description: 'Revisa tus datos de jugador y documento' },
  { to: '/player/tournaments', label: 'Torneos', shortLabel: 'Torneos', description: 'Revisa torneos e inscríbete con tu pareja' },
  { to: '/player/invitations', label: 'Invitaciones', shortLabel: 'Invites', description: 'Confirma tu pareja y asegura tu lugar' },
  { to: '/player/registrations', label: 'Inscripciones', shortLabel: 'Inscrip.', description: 'Consulta estado, cola y próximos pasos' },
  { to: '/player/ranking', label: 'Mi ranking', shortLabel: 'Ranking', description: 'Deja listo tu ranking para competir' },
  { to: '/player/payments', label: 'Pagos', shortLabel: 'Pagos', description: 'Revisa pagos ligados a tu inscripción' },
]
const PLAYER_QUICK_ITEMS = PLAYER_MENU_ITEMS.filter((item) => item.to !== '/player/profile')

export default function PlayerLayout() {
  const { user, logout } = useAuth()
  const [authWarning, setAuthWarning] = useState('')
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const location = useLocation()
  const currentSection = PLAYER_MENU_ITEMS.find((item) =>
    item.end ? location.pathname === item.to : location.pathname.startsWith(item.to),
  ) || PLAYER_MENU_ITEMS[0]
  const firstName = String(user?.name || 'Jugador').trim().split(/\s+/)[0] || 'Jugador'

  useEffect(() => {
    const message = sessionStorage.getItem(AUTH_WARNING_KEY)
    if (!message) return

    setAuthWarning(message)
    sessionStorage.removeItem(AUTH_WARNING_KEY)
  }, [])

  useEffect(() => {
    setIsSidebarOpen(false)
  }, [location.pathname])

  const handleCloseSidebar = () => setIsSidebarOpen(false)

  return (
    <div className="admin-shell admin-shell-pro admin-shell-player">
      <button
        type="button"
        className={`admin-sidebar-backdrop ${isSidebarOpen ? 'is-open' : ''}`}
        onClick={handleCloseSidebar}
        aria-hidden={!isSidebarOpen}
        tabIndex={isSidebarOpen ? 0 : -1}
      />

      <aside className={`admin-sidebar-frame ${isSidebarOpen ? 'is-open' : ''}`}>
        <div className="admin-sidebar-top">
          <BrandLockup subtitle="Zona de torneo" className="admin-brand-lockup" variant="compact" />
          <span className="tag muted">Jugador</span>
        </div>

        <nav id="player-sidebar-nav" className={`admin-nav admin-sidebar ${isSidebarOpen ? 'is-open' : ''}`}>
          <div className="admin-sidebar-header">
            <strong>Navegación</strong>
            <button
              className="ghost-button admin-sidebar-close"
              type="button"
              aria-label="Cerrar menú de navegación"
              onClick={handleCloseSidebar}
            >
              Cerrar
            </button>
          </div>
          <div className="player-menu-user">
            <span className="player-user-kicker">Jugador</span>
            <strong>{firstName}</strong>
          </div>
          {PLAYER_MENU_ITEMS.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} onClick={handleCloseSidebar}>
              <span className="player-nav-item-title">{item.label}</span>
              <small className="player-nav-item-meta">{item.description}</small>
            </NavLink>
          ))}
          <div className="player-menu-divider" />
          <button
            className="secondary-button player-menu-logout"
            type="button"
            onClick={logout}
          >
            Cerrar sesión
          </button>
        </nav>
      </aside>

      <section className="admin-main">
        <header className="admin-header player-header">
          <div className="player-header-copy">
            <span className="tag muted">Jugador</span>
            <h2>{currentSection.label}</h2>
            <p className="admin-subtitle">{currentSection.description}</p>
            {authWarning && <p className="auth-error">{authWarning}</p>}
          </div>
          <button
            className="ghost-button admin-menu-toggle player-menu-toggle"
            type="button"
            aria-label="Abrir menú"
            aria-expanded={isSidebarOpen}
            aria-controls="player-sidebar-nav"
            onClick={() => setIsSidebarOpen((prev) => !prev)}
          >
            <span className="player-menu-toggle-bars" aria-hidden="true">
              <span />
              <span />
              <span />
            </span>
          </button>
        </header>

        <nav className="player-quick-strip" aria-label="Secciones del jugador">
          {PLAYER_QUICK_ITEMS.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className="player-quick-pill">
              {item.shortLabel}
            </NavLink>
          ))}
        </nav>

        <div className="admin-content">
          <Outlet />
        </div>
      </section>
    </div>
  )
}
