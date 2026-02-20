import { useEffect, useMemo, useState } from 'react'
import { adminTournamentsApi } from '../../features/tournaments/api'
import { adminWildcardsApi } from '../../features/wildcards/api'
import { cleanPayload } from '../../utils/cleanPayload'

const initialForm = {
  tournament_id: '',
  tournament_category_id: '',
  mode: 'link',
  email: '',
  player_name: '',
  partner_email: '',
  partner_name: '',
  wildcard_fee_waived: false,
}

export default function Wildcards() {
  const [form, setForm] = useState(initialForm)
  const [tournaments, setTournaments] = useState([])
  const [invitations, setInvitations] = useState([])
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const selectedTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(form.tournament_id)),
    [tournaments, form.tournament_id],
  )

  const categoryOptions = useMemo(() => {
    if (!selectedTournament) return []
    return (selectedTournament.categories || []).map((category) => ({
      id: category.id,
      label: category.category?.display_name || category.category?.name || 'Categoría',
    }))
  }, [selectedTournament])

  const load = async () => {
    try {
      const [tournamentsData, invitesData] = await Promise.all([
        adminTournamentsApi.list(),
        adminWildcardsApi.list(),
      ])
      setTournaments(tournamentsData)
      setInvitations(invitesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los wildcards.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleChange = (field) => (event) => {
    const value = field === 'wildcard_fee_waived' ? event.target.checked : event.target.value
    setForm((prev) => ({
      ...prev,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')

    try {
      const payload = {
        ...cleanPayload(form),
        tournament_category_id: form.tournament_category_id,
      }
      const result = await adminWildcardsApi.create(payload)
      if (payload.mode === 'link') {
        setMessage(`Invitación creada. Token: ${result.token}`)
      } else {
        setMessage('Wildcard creado y registrado.')
      }
      setForm(initialForm)
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos crear el wildcard.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Wildcards</h3>
          <p>Invita parejas fuera de ranking a un torneo.</p>
        </div>
      </div>

      <div className="admin-grid">
        <div className="panel-card">
          <div className="panel-header">
            <h4>Crear wildcard</h4>
          </div>
          <form className="form-grid" onSubmit={handleSubmit}>
            <label>
              Torneo
              <select value={form.tournament_id} onChange={handleChange('tournament_id')}>
                <option value="">Selecciona</option>
                {tournaments.map((tournament) => (
                  <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
                ))}
              </select>
            </label>
            <label>
              Categoría del torneo
              <select value={form.tournament_category_id} onChange={handleChange('tournament_category_id')}>
                <option value="">Selecciona</option>
                {categoryOptions.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </select>
            </label>
            <label>
              Modo
              <select value={form.mode} onChange={handleChange('mode')}>
                <option value="link">Link de invitación</option>
                <option value="manual">Crear inscripción</option>
              </select>
            </label>
            <label>
              Email jugador
              <input type="email" value={form.email} onChange={handleChange('email')} />
            </label>
            <label>
              Nombre jugador
              <input type="text" value={form.player_name} onChange={handleChange('player_name')} />
            </label>
            <label>
              Email partner
              <input type="email" value={form.partner_email} onChange={handleChange('partner_email')} />
            </label>
            <label>
              Nombre partner
              <input type="text" value={form.partner_name} onChange={handleChange('partner_name')} />
            </label>
            <label className="checkbox-row">
              <input
                type="checkbox"
                checked={form.wildcard_fee_waived}
                onChange={handleChange('wildcard_fee_waived')}
              />
              Exonerar pago
            </label>
            <div className="form-actions">
              <button className="primary-button" type="submit">Crear wildcard</button>
            </div>
            {message && <p className="form-message success">{message}</p>}
            {error && <p className="form-message error">{error}</p>}
          </form>
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Invitaciones</h4>
            <span className="tag muted">{invitations.length}</span>
          </div>
          {invitations.length === 0 ? (
            <div className="empty-state">No hay invitaciones.</div>
          ) : (
            <div className="registration-list">
              {invitations.map((invite) => (
                <div key={invite.id} className="registration-item">
                  <div>
                    <strong>{invite.email}</strong>
                    <span>{invite.tournament_category?.tournament?.name || 'Torneo'}</span>
                    <span>{invite.tournament_category?.category?.display_name || invite.tournament_category?.category?.name}</span>
                  </div>
                  <div>
                    <span>Estado</span>
                    <strong>{invite.status?.label || 'Pendiente'}</strong>
                  </div>
                  <div>
                    <span>Token</span>
                    <strong>{invite.token}</strong>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  )
}
