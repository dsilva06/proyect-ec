import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function AdminLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="admin-shell">
      <header className="admin-header">
        <div>
          <h2>Panel de administración</h2>
          <p className="admin-subtitle">Crea torneos y controla inscripciones.</p>
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
        </div>
      </header>

      <nav className="admin-nav">
        <NavLink to="/admin" end>Resumen</NavLink>
        <NavLink to="/admin/settings">Torneos</NavLink>
        <NavLink to="/admin/registrations">Inscripciones</NavLink>
        <NavLink to="/admin/wildcards">Wildcards</NavLink>
        <NavLink to="/admin/rankings">Rankings</NavLink>
        <NavLink to="/admin/payments">Pagos</NavLink>
        <NavLink to="/admin/draws">Cuadros</NavLink>
        <NavLink to="/admin/matches">Partidos</NavLink>
        <NavLink to="/admin/leads">Leads</NavLink>
      </nav>

      <div className="admin-content">
        <Outlet />
      </div>
    </div>
  )
}
