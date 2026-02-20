import { useEffect, useMemo, useState } from 'react'
import { statusesApi } from '../../features/statuses/api'
import { adminRegistrationsApi } from '../../features/registrations/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

export default function Registrations() {
  const [registrations, setRegistrations] = useState([])
  const [statuses, setStatuses] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [filters, setFilters] = useState({
    tournament_id: '',
    status_id: '',
    category_id: '',
  })
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(50)
  const [error, setError] = useState('')

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'registration'),
    [statuses],
  )

  const load = async (overrideFilters = filters) => {
    try {
      const [registrationsData, statusesData, tournamentsData] = await Promise.all([
        adminRegistrationsApi.list(overrideFilters),
        statusesApi.list(),
        adminTournamentsApi.list(),
      ])
      setRegistrations(registrationsData)
      setStatuses(statusesData)
      setTournaments(tournamentsData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar las inscripciones.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleFilterChange = (field) => (event) => {
    const value = event.target.value
    const nextFilters = { ...filters, [field]: value }
    if (field === 'tournament_id') {
      nextFilters.category_id = ''
    }
    setFilters(nextFilters)
    setPage(1)
    load(nextFilters)
  }

  const handleSearchChange = (event) => {
    setSearch(event.target.value)
    setPage(1)
  }

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value))
    setPage(1)
  }

  const handleStatusUpdate = async (registrationId, statusId) => {
    try {
      await adminRegistrationsApi.update(registrationId, { status_id: statusId })
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar la inscripción.')
    }
  }

  const handleNotesUpdate = async (registrationId, notes) => {
    try {
      await adminRegistrationsApi.update(registrationId, { notes_admin: notes })
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos guardar la nota.')
    }
  }

  const filteredRegistrations = useMemo(() => {
    const term = search.trim().toLowerCase()
    if (!term) return registrations
    return registrations.filter((registration) => {
      const teamName = registration.team?.display_name || ''
      const tournamentName = registration.tournament_category?.tournament?.name || ''
      const categoryName = registration.tournament_category?.category?.display_name
        || registration.tournament_category?.category?.name
        || ''
      const players = (registration.team?.members || []).map((member) => member.name).join(' ')
      return `${teamName} ${tournamentName} ${categoryName} ${players}`.toLowerCase().includes(term)
    })
  }, [registrations, search])

  const totalPages = Math.max(1, Math.ceil(filteredRegistrations.length / perPage))
  const pagedRegistrations = useMemo(() => {
    const start = (page - 1) * perPage
    return filteredRegistrations.slice(start, start + perPage)
  }, [filteredRegistrations, page, perPage])

  const handlePrevPage = () => setPage((prev) => Math.max(1, prev - 1))
  const handleNextPage = () => setPage((prev) => Math.min(totalPages, prev + 1))

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Inscripciones</h3>
          <p>Revisa quién se registró y ajusta el estado.</p>
        </div>
        <div className="admin-page-actions">
          <select value={filters.tournament_id} onChange={handleFilterChange('tournament_id')}>
            <option value="">Todos los torneos</option>
            {tournaments.map((tournament) => (
              <option key={tournament.id} value={tournament.id}>
                {tournament.name}
              </option>
            ))}
          </select>
          <select value={filters.category_id} onChange={handleFilterChange('category_id')}>
            <option value="">Todas las categorías</option>
            {tournaments
              .filter(
                (tournament) =>
                  !filters.tournament_id || String(tournament.id) === String(filters.tournament_id)
              )
              .flatMap((tournament) =>
                (tournament.categories || []).map((category) => (
                  <option key={category.category?.id} value={category.category?.id}>
                    {category.category?.display_name || category.category?.name}
                  </option>
                ))
              )}
          </select>
          <select value={filters.status_id} onChange={handleFilterChange('status_id')}>
            <option value="">Todos los estados</option>
            {statusOptions.map((status) => (
              <option key={status.id} value={status.id}>
                {status.label}
              </option>
            ))}
          </select>
          <input
            type="text"
            placeholder="Buscar equipo, jugador, torneo..."
            value={search}
            onChange={handleSearchChange}
          />
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="table-actions">
        <span className="tag muted">{filteredRegistrations.length} inscripciones</span>
        <div className="table-controls">
          <label>
            Mostrar
            <select value={perPage} onChange={handlePerPageChange}>
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
            </select>
          </label>
          <div className="table-pagination">
            <button className="ghost-button" type="button" onClick={handlePrevPage} disabled={page === 1}>
              ← Anterior
            </button>
            <span>Página {page} de {totalPages}</span>
            <button className="ghost-button" type="button" onClick={handleNextPage} disabled={page === totalPages}>
              Siguiente →
            </button>
          </div>
        </div>
      </div>

      {filteredRegistrations.length === 0 ? (
        <div className="empty-state">Aún no hay inscripciones.</div>
      ) : (
        <div className="registrations-table">
          <div className="registrations-row registrations-header">
            <div>Equipo</div>
            <div>Torneo / Categoría</div>
            <div>Ranking</div>
            <div>Posición</div>
            <div>Estado</div>
            <div>Nota admin</div>
            <div>Registrado</div>
          </div>
          {pagedRegistrations.map((registration) => (
            <div key={registration.id} className="registrations-row">
              <div>
                <strong>{registration.team?.display_name || 'Equipo'}</strong>
                <span className="muted">
                  {(registration.team?.members || []).map((member) =>
                    `${member.name}${member.ranking_value ? ` (#${member.ranking_value})` : ''}`,
                  ).join(' • ') || 'Sin jugadores'}
                </span>
                {registration.is_wildcard ? <span className="tag muted">Wildcard</span> : null}
              </div>
              <div>
                <strong>{registration.tournament_category?.tournament?.name}</strong>
                <span className="muted">
                  {registration.tournament_category?.category?.display_name || registration.tournament_category?.category?.name}
                </span>
              </div>
              <div>
                <strong>{registration.team_ranking_score ?? 'Pendiente'}</strong>
              </div>
              <div>
                <strong>{registration.queue_position ?? '—'}</strong>
              </div>
              <div>
                <select
                  value={registration.status?.id || ''}
                  onChange={(event) => handleStatusUpdate(registration.id, event.target.value)}
                >
                  {statusOptions.map((status) => (
                    <option key={status.id} value={status.id}>
                      {status.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <input
                  type="text"
                  defaultValue={registration.notes_admin || ''}
                  placeholder="Nota..."
                  onBlur={(event) => handleNotesUpdate(registration.id, event.target.value)}
                />
              </div>
              <div>
                <strong>{registration.created_at?.slice(0, 10) || '—'}</strong>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
