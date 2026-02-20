import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function PlayerLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="admin-shell">
      <header className="admin-header">
        <div>
          <h2>Panel de jugador</h2>
          <p className="admin-subtitle">Gestiona tus inscripciones y pagos.</p>
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
        </div>
      </header>

      <nav className="admin-nav">
        <NavLink to="/player" end>Resumen</NavLink>
        <NavLink to="/player/tournaments">Torneos</NavLink>
        <NavLink to="/player/invitations">Invitaciones</NavLink>
        <NavLink to="/player/ranking">Mi ranking</NavLink>
        <NavLink to="/player/registrations">Mis inscripciones</NavLink>
        <NavLink to="/player/payments">Mis pagos</NavLink>
      </nav>

      <div className="admin-content">
        <Outlet />
      </div>
    </div>
  )
}
