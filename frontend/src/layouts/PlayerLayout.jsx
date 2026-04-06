import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import BrandLockup from '../components/shared/BrandLockup'

const AUTH_WARNING_KEY = 'auth_login_warning'
const PLAYER_NAV_ITEMS = [
  { to: '/player', label: 'Resumen', shortLabel: 'Inicio', description: 'Panel rápido con lo importante del día', end: true },
  { to: '/player/tournaments', label: 'Torneos', shortLabel: 'Torneos', description: 'Explora torneos y regístrate desde el móvil' },
  { to: '/player/invitations', label: 'Invitaciones', shortLabel: 'Invites', description: 'Acepta tu pareja pendiente y confirma tu lugar' },
  { to: '/player/registrations', label: 'Inscripciones', shortLabel: 'Inscrip.', description: 'Revisa estado, cola y próximos pasos' },
  { to: '/player/ranking', label: 'Mi ranking', shortLabel: 'Ranking', description: 'Actualiza tu ranking y mantén tu perfil listo' },
  { to: '/player/payments', label: 'Pagos', shortLabel: 'Pagos', description: 'Consulta montos, estados y movimientos registrados' },
]

const PLAYER_DOCK_ITEMS = [
  { to: '/player', label: 'Inicio', end: true },
  { to: '/player/tournaments', label: 'Torneos' },
  { to: '/player/registrations', label: 'Inscrip.' },
  { to: '/player/invitations', label: 'Invites' },
]

export default function PlayerLayout() {
  const { user, logout } = useAuth()
  const [authWarning, setAuthWarning] = useState('')
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const location = useLocation()
  const currentSection = PLAYER_NAV_ITEMS.find((item) =>
    item.end ? location.pathname === item.to : location.pathname.startsWith(item.to),
  ) || PLAYER_NAV_ITEMS[0]
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
          <BrandLockup subtitle="Panel de jugador" className="admin-brand-lockup" variant="compact" />
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
          {PLAYER_NAV_ITEMS.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} onClick={handleCloseSidebar}>
              <span className="player-nav-item-title">{item.label}</span>
              <small className="player-nav-item-meta">{item.description}</small>
            </NavLink>
          ))}
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
          <div className="admin-user player-user-panel">
            <div className="player-user-copy">
              <span className="player-user-kicker">Sesión activa</span>
              <strong>{firstName}</strong>
              <span className="admin-email">{user?.email}</span>
            </div>
            <div className="player-header-actions">
              <button className="secondary-button" type="button" onClick={logout}>
                Cerrar sesión
              </button>
              <button
                className="ghost-button admin-menu-toggle"
                type="button"
                aria-label="Abrir menú de navegación"
                aria-expanded={isSidebarOpen}
                aria-controls="player-sidebar-nav"
                onClick={() => setIsSidebarOpen((prev) => !prev)}
              >
                Menú
              </button>
            </div>
          </div>
        </header>

        <nav className="player-quick-strip" aria-label="Secciones del jugador">
          {PLAYER_NAV_ITEMS.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className="player-quick-pill">
              {item.shortLabel}
            </NavLink>
          ))}
        </nav>

        <div className="admin-content">
          <Outlet />
        </div>
      </section>

      <nav className="player-mobile-dock" aria-label="Accesos rápidos del jugador">
        {PLAYER_DOCK_ITEMS.map((item) => (
          <NavLink key={item.to} to={item.to} end={item.end} className="player-mobile-dock-link">
            {item.label}
          </NavLink>
        ))}
        <button
          className={`player-mobile-dock-link player-mobile-dock-trigger${isSidebarOpen ? ' is-open' : ''}`}
          type="button"
          aria-label="Abrir más opciones"
          aria-expanded={isSidebarOpen}
          aria-controls="player-sidebar-nav"
          onClick={() => setIsSidebarOpen((prev) => !prev)}
        >
          Más
        </button>
      </nav>
    </div>
  )
}
