import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { inviteStorage } from '../../auth/inviteStorage'
import { getHomeRouteForRole } from '../../auth/roleHelpers'
import { playerTeamInvitesApi, publicTeamInvitesApi } from '../../features/teamInvites/api'

export default function Register() {
  const navigate = useNavigate()
  const { register } = useAuth()
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  })
  const [error, setError] = useState('')
  const [invite, setInvite] = useState(null)
  const [inviteError, setInviteError] = useState('')

  useEffect(() => {
    const token = inviteStorage.getToken()
    if (!token) return

    let isMounted = true
    setInviteError('')

    publicTeamInvitesApi.get(token)
      .then((data) => {
        if (!isMounted) return
        setInvite(data)
        if (data?.invited_email) {
          setForm((prev) => ({ ...prev, email: data.invited_email }))
        }
      })
      .catch(() => {
        if (!isMounted) return
        setInviteError('No pudimos cargar la invitación. Intenta nuevamente.')
        inviteStorage.clearToken()
      })

    return () => {
      isMounted = false
    }
  }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setInviteError('')
    try {
      const loggedInUser = await register(form)
      const token = inviteStorage.getToken()
      if (token) {
        try {
          await playerTeamInvitesApi.claim(token)
          inviteStorage.clearToken()
          navigate('/player/invitations')
          return
        } catch (claimError) {
          setInviteError(claimError?.data?.message || 'No pudimos asociar tu invitación.')
        }
      }
      navigate(getHomeRouteForRole(loggedInUser))
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Registration failed.')
    }
  }

  return (
    <div style={{ padding: '48px' }}>
      <h1>Crear cuenta</h1>
      {invite && (
        <div style={{ marginBottom: '16px', color: '#cbd5f5' }}>
          Invitación pendiente para el equipo <strong>{invite.team?.display_name || 'Equipo'}</strong>.
          Crea tu cuenta con este correo para aceptarla en tu perfil.
          <div style={{ marginTop: '8px' }}>
            ¿Ya tienes cuenta? <Link to="/login">Inicia sesión</Link>
          </div>
        </div>
      )}
      {inviteError && <p style={{ color: '#ff9b9b' }}>{inviteError}</p>}
      <form onSubmit={handleSubmit} style={{ display: 'grid', gap: '12px', maxWidth: '320px' }}>
        <input
          type="text"
          placeholder="Nombre"
          value={form.first_name}
          onChange={(event) => setForm({ ...form, first_name: event.target.value })}
        />
        <input
          type="text"
          placeholder="Apellido"
          value={form.last_name}
          onChange={(event) => setForm({ ...form, last_name: event.target.value })}
        />
        <input
          type="email"
          placeholder="Correo"
          value={form.email}
          disabled={Boolean(invite?.invited_email)}
          onChange={(event) => setForm({ ...form, email: event.target.value })}
        />
        <input
          type="text"
          placeholder="Teléfono"
          value={form.phone}
          onChange={(event) => setForm({ ...form, phone: event.target.value })}
        />
        <input
          type="password"
          placeholder="Contraseña"
          value={form.password}
          onChange={(event) => setForm({ ...form, password: event.target.value })}
        />
        <input
          type="password"
          placeholder="Confirmar contraseña"
          value={form.password_confirmation}
          onChange={(event) =>
            setForm({ ...form, password_confirmation: event.target.value })
          }
        />
        <button type="submit">Crear cuenta</button>
      </form>
      {error && <p style={{ color: '#ff9b9b' }}>{error}</p>}
    </div>
  )
}
