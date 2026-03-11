import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { inviteStorage } from '../../auth/inviteStorage'

export default function InviteRedirect() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { user, status } = useAuth()

  useEffect(() => {
    if (token) {
      inviteStorage.setToken(token)
    }

    if (status === 'loading') {
      return
    }

    if (user) {
      navigate('/player/invitations', { replace: true })
    } else {
      navigate('/register', { replace: true })
    }
  }, [token, user, status, navigate])

  return (
    <div style={{ padding: '48px' }}>
      <p>Redirigiendo a registro...</p>
    </div>
  )
}
