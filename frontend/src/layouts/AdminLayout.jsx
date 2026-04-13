import { useEffect, useMemo, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import BrandLockup from '../components/shared/BrandLockup'

const AUTH_WARNING_KEY = 'auth_login_warning'

const ADMIN_NAV_ITEMS = [
  {
    to: '/admin',
    label: 'Resumen',
    meta: 'KPIs, alertas y operación diaria',
    match: (pathname) => pathname === '/admin',
    end: true,
  },
  {
    to: '/admin/settings',
    label: 'Torneos',
    meta: 'Configuración, categorías y calendario',
    match: (pathname) => pathname.startsWith('/admin/settings'),
  },
  {
    to: '/admin/registrations',
    label: 'Inscripciones',
    meta: 'Flujo operativo, cola y validaciones',
    match: (pathname) => pathname.startsWith('/admin/registrations'),
  },
  {
    to: '/admin/wildcards',
    label: 'Wildcards',
    meta: 'Invitaciones y cupos especiales',
    match: (pathname) => pathname.startsWith('/admin/wildcards'),
  },
  {
    to: '/admin/players',
    label: 'Jugadores',
    meta: 'Base de jugadores y rankings',
    match: (pathname) => pathname.startsWith('/admin/players'),
  },
  {
    to: '/admin/payments',
    label: 'Pagos',
    meta: 'Cobros, estados y seguimiento',
    match: (pathname) => pathname.startsWith('/admin/payments'),
  },
  {
    to: '/admin/draws',
    label: 'Cuadros',
    meta: 'Sorteos, llaves y cruces',
    match: (pathname) => pathname.startsWith('/admin/draws'),
  },
  {
    to: '/admin/matches',
    label: 'Partidos',
    meta: 'Agenda, rondas y resultados',
    match: (pathname) => pathname.startsWith('/admin/matches'),
  },
  {
    to: '/admin/leads',
    label: 'Leads',
    meta: 'Captación y seguimiento comercial',
    match: (pathname) => pathname.startsWith('/admin/leads'),
  },
]

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const [authWarning, setAuthWarning] = useState('')
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const location = useLocation()

  const currentSection = useMemo(() => (
    ADMIN_NAV_ITEMS.find((item) => item.match(location.pathname))
    || ADMIN_NAV_ITEMS[0]
  ), [location.pathname])

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
    <div className="admin-shell admin-shell-pro">
      <button
        type="button"
        className={`admin-sidebar-backdrop ${isSidebarOpen ? 'is-open' : ''}`}
        onClick={handleCloseSidebar}
        aria-hidden={!isSidebarOpen}
        tabIndex={isSidebarOpen ? 0 : -1}
      />

      <aside className={`admin-sidebar-frame ${isSidebarOpen ? 'is-open' : ''}`}>
        <div className="admin-sidebar-top">
          <BrandLockup subtitle="Panel administrativo" className="admin-brand-lockup" variant="compact" />
          <span className="tag muted">Administrador</span>
        </div>

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
          <div className="admin-sidebar-section">
            <span className="admin-nav-section-label">Operación</span>
            {ADMIN_NAV_ITEMS.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={handleCloseSidebar}
              >
                <span className="admin-nav-item-title">{item.label}</span>
                <span className="admin-nav-item-meta">{item.meta}</span>
              </NavLink>
            ))}
          </div>
        </nav>
      </aside>

      <section className="admin-main">
        <header className="admin-header">
          <div className="admin-header-copy">
            <span className="admin-header-eyebrow">Panel administrativo</span>
            <h2>{currentSection.label}</h2>
            <p className="admin-subtitle">{currentSection.meta}</p>
            {authWarning && <p className="auth-error">{authWarning}</p>}
          </div>
          <div className="admin-user">
            <div className="admin-user-card">
              <span className="tag muted">Sesión activa</span>
              <strong>{user?.name || 'Admin'}</strong>
              <span className="admin-email">{user?.email}</span>
            </div>
            <div className="admin-user-actions">
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
          </div>
        </header>

        <div className="admin-content">
          <Outlet />
        </div>
      </section>
    </div>
  )
}
