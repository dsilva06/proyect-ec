import { Navigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { getHomeRouteForRole } from '../../auth/roleHelpers'

export default function PublicOnlyRoute({ children }) {
  const { user, status } = useAuth()

  if (status === 'loading') {
    return <div style={{ padding: '24px' }}>Cargando...</div>
  }

  if (status === 'authenticated' && user) {
    return <Navigate to={getHomeRouteForRole(user)} replace />
  }

  return children
}
