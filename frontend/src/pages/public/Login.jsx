import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { inviteStorage } from '../../auth/inviteStorage'
import { getHomeRouteForRole } from '../../auth/roleHelpers'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'

export default function Login() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    try {
      const loggedInUser = await login(form)
      const token = inviteStorage.getToken()
      if (token) {
        try {
          await playerTeamInvitesApi.claim(token)
          inviteStorage.clearToken()
          navigate('/player/invitations')
          return
        } catch (claimError) {
          setError(claimError?.data?.message || 'No pudimos asociar tu invitación.')
        }
      }
      navigate(getHomeRouteForRole(loggedInUser))
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Login failed.')
    }
  }

  return (
    <div style={{ padding: '48px' }}>
      <h1>Iniciar sesión</h1>
      <form onSubmit={handleSubmit} style={{ display: 'grid', gap: '12px', maxWidth: '320px' }}>
        <input
          type="email"
          placeholder="Correo"
          value={form.email}
          onChange={(event) => setForm({ ...form, email: event.target.value })}
        />
        <input
          type="password"
          placeholder="Contraseña"
          value={form.password}
          onChange={(event) => setForm({ ...form, password: event.target.value })}
        />
        <button type="submit">Entrar</button>
      </form>
      {error && <p style={{ color: '#ff9b9b' }}>{error}</p>}
    </div>
  )
}
