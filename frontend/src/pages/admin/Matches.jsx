import { useEffect, useMemo, useState } from 'react'
import { adminMatchesApi } from '../../features/matches/api'
import { adminTournamentsApi } from '../../features/tournaments/api'
import { statusesApi } from '../../features/statuses/api'
import { cleanPayload } from '../../utils/cleanPayload'
import DateTimePicker from '../../components/shared/DateTimePicker'

const initialForm = {
  tournament_category_id: '',
  round_number: 1,
  match_number: 1,
  status_id: '',
  scheduled_at: '',
  estimated_duration_minutes: '',
  court: '',
}

export default function Matches() {
  const [matches, setMatches] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [statuses, setStatuses] = useState([])
  const [form, setForm] = useState(initialForm)
  const [filters, setFilters] = useState({ tournament_id: '', category_id: '' })
  const [error, setError] = useState('')

  const categoryOptions = useMemo(() => {
    return tournaments.flatMap((tournament) =>
      (tournament.categories || []).map((category) => ({
        id: category.id,
        label: `${tournament.name} • ${category.category?.name}`,
      })),
    )
  }, [tournaments])

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'match'),
    [statuses],
  )

  const categoryFilterOptions = useMemo(() => {
    if (!filters.tournament_id) {
      return tournaments.flatMap((tournament) =>
        (tournament.categories || []).map((category) => ({
          id: category.category?.id,
          label: `${tournament.name} • ${category.category?.display_name || category.category?.name}`,
        })),
      )
    }
    const tournament = tournaments.find((item) => String(item.id) === String(filters.tournament_id))
    return (tournament?.categories || []).map((category) => ({
      id: category.category?.id,
      label: category.category?.display_name || category.category?.name,
    }))
  }, [filters.tournament_id, tournaments])

  const load = async (nextFilters = filters) => {
    try {
      const [matchesData, tournamentsData, statusesData] = await Promise.all([
        adminMatchesApi.list(nextFilters),
        adminTournamentsApi.list(),
        statusesApi.list(),
      ])
      setMatches(matchesData)
      setTournaments(tournamentsData)
      setStatuses(statusesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los partidos.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleChange = (field) => (event) => {
    setForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const handleFilterChange = (field) => (event) => {
    const value = event.target.value
    const nextFilters = { ...filters, [field]: value }
    if (field === 'tournament_id') {
      nextFilters.category_id = ''
    }
    setFilters(nextFilters)
    load(nextFilters)
  }

  const handleValueChange = (field) => (value) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const handleCreate = async (event) => {
    event.preventDefault()
    setError('')
    try {
      await adminMatchesApi.create({
        ...cleanPayload(form),
        status_id: form.status_id || statusOptions?.[0]?.id,
        round_number: Number(form.round_number || 1),
        match_number: Number(form.match_number || 1),
        estimated_duration_minutes: form.estimated_duration_minutes
          ? Number(form.estimated_duration_minutes)
          : null,
      })
      setForm(initialForm)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos crear el partido.')
    }
  }

  const handleDelay = async (matchId) => {
    setError('')
    try {
      await adminMatchesApi.delay(matchId)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos retrasar el partido.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Partidos</h3>
          <p>Agenda partidos y actualiza estado.</p>
        </div>
        <div className="admin-page-actions">
          <select value={filters.tournament_id} onChange={handleFilterChange('tournament_id')}>
            <option value="">Todos los torneos</option>
            {tournaments.map((tournament) => (
              <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
            ))}
          </select>
          <select value={filters.category_id} onChange={handleFilterChange('category_id')}>
            <option value="">Todas las categorías</option>
            {categoryFilterOptions.map((category) => (
              <option key={category.id} value={category.id}>{category.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="admin-grid">
        <div className="panel-card">
          <div className="panel-header">
            <h4>Crear partido</h4>
          </div>
          <form className="form-grid" onSubmit={handleCreate}>
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
              Ronda
              <input type="number" value={form.round_number} onChange={handleChange('round_number')} />
            </label>
            <label>
              Número de partido
              <input type="number" value={form.match_number} onChange={handleChange('match_number')} />
            </label>
            <label>
              No antes de
              <DateTimePicker value={form.scheduled_at} onChange={handleValueChange('scheduled_at')} />
            </label>
            <label>
              Duración estimada (min)
              <input
                type="number"
                min="10"
                value={form.estimated_duration_minutes}
                onChange={handleChange('estimated_duration_minutes')}
              />
            </label>
            <label>
              Cancha
              <input type="text" value={form.court} onChange={handleChange('court')} />
            </label>
            <label>
              Estado
              <select value={form.status_id} onChange={handleChange('status_id')}>
                <option value="">Selecciona</option>
                {statusOptions.map((status) => (
                  <option key={status.id} value={status.id}>{status.label}</option>
                ))}
              </select>
            </label>
            <div className="form-actions">
              <button className="primary-button" type="submit">Crear partido</button>
            </div>
          </form>
          {error && <p className="form-message error">{error}</p>}
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Partidos programados</h4>
            <span className="tag muted">{matches.length}</span>
          </div>
          {matches.length === 0 ? (
            <div className="empty-state">No hay partidos registrados.</div>
          ) : (
            <div className="registration-list">
              {matches.map((match) => (
                <div key={match.id} className="registration-item">
                  <div>
                    <strong>Ronda {match.round_number} • Partido {match.match_number}</strong>
                    <span>{match.tournament_category?.tournament?.name || 'Torneo'}</span>
                    <span>{match.tournament_category?.category?.display_name || match.tournament_category?.category?.name || 'Categoría'}</span>
                  </div>
                  <div>
                    <span>No antes de</span>
                    <strong>{(match.not_before_at || match.scheduled_at) ? (match.not_before_at || match.scheduled_at).replace('T', ' ').slice(0, 16) : '—'}</strong>
                  </div>
                  <div>
                    <span>Estado</span>
                    <strong>{match.status?.label || '—'}</strong>
                  </div>
                  <div>
                    <span>Cancha</span>
                    <strong>{match.court || '—'}</strong>
                  </div>
                  <div className="form-actions">
                    <button
                      className="ghost-button"
                      type="button"
                      onClick={() => handleDelay(match.id)}
                    >
                      Retrasar
                    </button>
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
