import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'

export default function ProtectedRoute() {
  const { user, status } = useAuth()

  if (status === 'loading') {
    return <div style={{ padding: '24px' }}>Loading...</div>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}
