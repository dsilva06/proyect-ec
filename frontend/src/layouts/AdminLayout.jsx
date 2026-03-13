import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'

const AUTH_WARNING_KEY = 'auth_login_warning'

export default function AdminLayout() {
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
    <div className="admin-shell">
      <header className="admin-header">
        <div>
          <h2>Panel de administración</h2>
          <p className="admin-subtitle">Crea torneos y controla inscripciones.</p>
          {authWarning && <p className="auth-error">{authWarning}</p>}
        </div>
        <div className="admin-user">
          <div>
            <span className="tag muted">Sesión activa</span>
            <strong>{user?.name || 'Admin'}</strong>
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
            aria-controls="admin-sidebar-nav"
            onClick={() => setIsSidebarOpen((prev) => !prev)}
          >
            Menú
          </button>
        </div>
      </header>

      <button
        type="button"
        className={`admin-sidebar-backdrop ${isSidebarOpen ? 'is-open' : ''}`}
        onClick={handleCloseSidebar}
        aria-hidden={!isSidebarOpen}
        tabIndex={isSidebarOpen ? 0 : -1}
      />

      <nav id="admin-sidebar-nav" className={`admin-nav admin-sidebar ${isSidebarOpen ? 'is-open' : ''}`}>
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
        <NavLink to="/admin" end onClick={handleCloseSidebar}>Resumen</NavLink>
        <NavLink to="/admin/settings" onClick={handleCloseSidebar}>Torneos</NavLink>
        <NavLink to="/admin/registrations" onClick={handleCloseSidebar}>Inscripciones</NavLink>
        <NavLink to="/admin/wildcards" onClick={handleCloseSidebar}>Wildcards</NavLink>
        <NavLink to="/admin/players" onClick={handleCloseSidebar}>Jugadores</NavLink>
        <NavLink to="/admin/payments" onClick={handleCloseSidebar}>Pagos</NavLink>
        <NavLink to="/admin/draws" onClick={handleCloseSidebar}>Cuadros</NavLink>
        <NavLink to="/admin/matches" onClick={handleCloseSidebar}>Partidos</NavLink>
        <NavLink to="/admin/leads" onClick={handleCloseSidebar}>Leads</NavLink>
      </nav>

      <div className="admin-content">
        <Outlet />
      </div>
    </div>
  )
}
