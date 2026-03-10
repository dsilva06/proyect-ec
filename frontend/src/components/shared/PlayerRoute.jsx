import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { getHomeRouteForRole } from '../../auth/roleHelpers'

export default function PlayerRoute() {
  const { user, status } = useAuth()

  if (status === 'loading') {
    return <div style={{ padding: '24px' }}>Cargando...</div>
  }

  if (status !== 'authenticated' || !user || user.is_active === false) {
    return <Navigate to="/login" replace />
  }

  if (user.role !== 'player') {
    return <Navigate to={getHomeRouteForRole(user)} replace />
  }

  return <Outlet />
}
