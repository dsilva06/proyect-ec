import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import BrandLockup from '../components/shared/BrandLockup'

const AUTH_WARNING_KEY = 'auth_login_warning'

export default function PlayerLayout() {
  const { user, logout } = useAuth()
  const [authWarning, setAuthWarning] = useState('')
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const location = useLocation()

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
          <BrandLockup subtitle="Consola de jugador" className="admin-brand-lockup" variant="compact" />
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
          <NavLink to="/player" end onClick={handleCloseSidebar}>Resumen</NavLink>
          <NavLink to="/player/tournaments" onClick={handleCloseSidebar}>Torneos</NavLink>
          <NavLink to="/player/invitations" onClick={handleCloseSidebar}>Invitaciones</NavLink>
          <NavLink to="/player/ranking" onClick={handleCloseSidebar}>Mi ranking</NavLink>
          <NavLink to="/player/registrations" onClick={handleCloseSidebar}>Mis inscripciones</NavLink>
          <NavLink to="/player/payments" onClick={handleCloseSidebar}>Mis pagos</NavLink>
        </nav>
      </aside>

      <section className="admin-main">
        <header className="admin-header">
          <div>
            <h2>Panel de jugador</h2>
            <p className="admin-subtitle">Gestiona tus inscripciones y pagos.</p>
            {authWarning && <p className="auth-error">{authWarning}</p>}
          </div>
          <div className="admin-user">
            <div>
              <span className="tag muted">Sesión activa</span>
              <strong>{user?.name || 'Jugador'}</strong>
              <span className="admin-email">{user?.email}</span>
            </div>
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
        </header>

        <div className="admin-content">
          <Outlet />
        </div>
      </section>
    </div>
  )
}
