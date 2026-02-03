import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'

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

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    try {
      await register(form)
      navigate('/')
    } catch (err) {
      setError(err?.data?.message || err?.message || 'Registration failed.')
    }
  }

  return (
    <div style={{ padding: '48px' }}>
      <h1>Create account</h1>
      <form onSubmit={handleSubmit} style={{ display: 'grid', gap: '12px', maxWidth: '320px' }}>
        <input
          type="text"
          placeholder="First name"
          value={form.first_name}
          onChange={(event) => setForm({ ...form, first_name: event.target.value })}
        />
        <input
          type="text"
          placeholder="Last name"
          value={form.last_name}
          onChange={(event) => setForm({ ...form, last_name: event.target.value })}
        />
        <input
          type="email"
          placeholder="Email"
          value={form.email}
          onChange={(event) => setForm({ ...form, email: event.target.value })}
        />
        <input
          type="text"
          placeholder="Phone"
          value={form.phone}
          onChange={(event) => setForm({ ...form, phone: event.target.value })}
        />
        <input
          type="password"
          placeholder="Password"
          value={form.password}
          onChange={(event) => setForm({ ...form, password: event.target.value })}
        />
        <input
          type="password"
          placeholder="Confirm password"
          value={form.password_confirmation}
          onChange={(event) =>
            setForm({ ...form, password_confirmation: event.target.value })
          }
        />
        <button type="submit">Create account</button>
      </form>
      {error && <p style={{ color: '#ff9b9b' }}>{error}</p>}
    </div>
  )
}
