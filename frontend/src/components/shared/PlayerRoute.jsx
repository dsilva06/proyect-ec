import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'

export default function PlayerRoute() {
  const { user, status } = useAuth()

  if (status === 'loading') {
    return <div style={{ padding: '24px' }}>Cargando...</div>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (user.role === 'admin') {
    return <Navigate to="/admin" replace />
  }

  return <Outlet />
}
