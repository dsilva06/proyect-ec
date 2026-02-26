import { useEffect, useMemo, useState } from 'react'
import { adminMatchesApi } from '../../features/matches/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

const formatDateTime = (value) => {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleString('es-ES', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getTeamLabel = (registration) => registration?.team?.display_name || 'Por definir'
const getMatchDateTime = (match) => match?.not_before_at || match?.scheduled_at || null

const getHourKey = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  return `${String(parsed.getHours()).padStart(2, '0')}:00`
}

export default function Matches() {
  const [matches, setMatches] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [filters, setFilters] = useState({ tournament_id: '', category_id: '' })
  const [searchTerm, setSearchTerm] = useState('')
  const [hourFilter, setHourFilter] = useState('')
  const [selectedMatch, setSelectedMatch] = useState(null)
  const [error, setError] = useState('')

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
      const [matchesData, tournamentsData] = await Promise.all([
        adminMatchesApi.list(nextFilters),
        adminTournamentsApi.list(),
      ])
      setMatches(matchesData)
      setTournaments(tournamentsData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los partidos.')
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
    setSelectedMatch(null)
    load(nextFilters)
  }

  const hourOptions = useMemo(() => {
    const unique = new Set(
      matches
        .map((match) => getHourKey(getMatchDateTime(match)))
        .filter(Boolean),
    )

    return Array.from(unique).sort((a, b) => a.localeCompare(b))
  }, [matches])

  const visibleMatches = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()

    return matches
      .filter((match) => {
        if (hourFilter) {
          const matchHour = getHourKey(getMatchDateTime(match))
          if (matchHour !== hourFilter) return false
        }

        if (!query) return true

        const searchable = [
          getTeamLabel(match.registration_a),
          getTeamLabel(match.registration_b),
          match.tournament_category?.tournament?.name,
          match.tournament_category?.category?.display_name,
          match.tournament_category?.category?.name,
          match.status?.label,
          match.court,
          `ronda ${match.round_number}`,
          `partido ${match.match_number}`,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase()

        return searchable.includes(query)
      })
      .sort((a, b) => {
        const roundA = Number(a.round_number || 0)
        const roundB = Number(b.round_number || 0)
        if (roundA !== roundB) return roundA - roundB

        const matchA = Number(a.match_number || 0)
        const matchB = Number(b.match_number || 0)
        if (matchA !== matchB) return matchA - matchB

        const timeA = getMatchDateTime(a) ? new Date(getMatchDateTime(a)).getTime() : Number.MAX_SAFE_INTEGER
        const timeB = getMatchDateTime(b) ? new Date(getMatchDateTime(b)).getTime() : Number.MAX_SAFE_INTEGER
        return timeA - timeB
      })
  }, [matches, searchTerm, hourFilter])

  const matchesByRound = useMemo(() => {
    const grouped = new Map()

    visibleMatches.forEach((match) => {
      const roundNumber = Number(match.round_number || 0)
      const key = roundNumber > 0 ? String(roundNumber) : 'sin-ronda'
      if (!grouped.has(key)) {
        grouped.set(key, [])
      }
      grouped.get(key).push(match)
    })

    return Array.from(grouped.entries()).sort((a, b) => {
      if (a[0] === 'sin-ronda') return 1
      if (b[0] === 'sin-ronda') return -1
      return Number(a[0]) - Number(b[0])
    })
  }, [visibleMatches])

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Partidos</h3>
          <p>Vista detallada de los partidos generados desde el cuadro.</p>
        </div>
      </div>

      <div className="panel-card">
        <div className="panel-header">
          <h4>Filtros</h4>
          <span className="tag muted">{visibleMatches.length}</span>
        </div>
        <div className="admin-page-actions matches-filters">
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
          <select value={hourFilter} onChange={(event) => setHourFilter(event.target.value)}>
            <option value="">Todas las horas</option>
            {hourOptions.map((hour) => (
              <option key={hour} value={hour}>{hour}</option>
            ))}
          </select>
          <input
            type="text"
            className="matches-search"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            placeholder="Buscar por jugador, equipo, torneo o categoria"
          />
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="panel-card">
        <div className="panel-header">
          <h4>Partidos ordenados por ronda</h4>
          <span className="tag muted">{visibleMatches.length}</span>
        </div>

        {visibleMatches.length === 0 ? (
          <div className="empty-state">No hay partidos para estos filtros.</div>
        ) : (
          <div className="matches-round-list">
            {matchesByRound.map(([roundKey, roundMatches]) => (
              <section key={roundKey} className="matches-round-group">
                <div className="matches-round-header">
                  <h5>{roundKey === 'sin-ronda' ? 'Sin ronda' : `Ronda ${roundKey}`}</h5>
                  <span className="tag muted">{roundMatches.length}</span>
                </div>

                <div className="registration-list">
                  {roundMatches.map((match) => (
                    <div
                      key={match.id}
                      className="registration-item match-item is-clickable"
                      role="button"
                      tabIndex={0}
                      onClick={() => setSelectedMatch(match)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          setSelectedMatch(match)
                        }
                      }}
                    >
                      <div>
                        <strong>Partido {match.match_number}</strong>
                        <span>{match.tournament_category?.tournament?.name || 'Torneo'}</span>
                        <span>{match.tournament_category?.category?.display_name || match.tournament_category?.category?.name || 'Categoría'}</span>
                      </div>
                      <div>
                        <span>Pareja A</span>
                        <strong>{getTeamLabel(match.registration_a)}</strong>
                      </div>
                      <div>
                        <span>Pareja B</span>
                        <strong>{getTeamLabel(match.registration_b)}</strong>
                      </div>
                      <div>
                        <span>No antes de</span>
                        <strong>{formatDateTime(match.not_before_at || match.scheduled_at)}</strong>
                      </div>
                      <div>
                        <span>Estado</span>
                        <strong>{match.status?.label || '—'}</strong>
                      </div>
                      <div>
                        <span>Cancha</span>
                        <strong>{match.court || '—'}</strong>
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            ))}
          </div>
        )}
      </div>

      {selectedMatch ? (
        <div className="modal-backdrop" onClick={() => setSelectedMatch(null)}>
          <div className="modal-card" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h3>Detalle del partido</h3>
                <p>Ronda {selectedMatch.round_number} • Partido {selectedMatch.match_number}</p>
              </div>
              <button className="ghost-button" type="button" onClick={() => setSelectedMatch(null)}>
                Cerrar
              </button>
            </div>

            <div className="modal-body match-detail-body">
              <div className="match-detail-grid">
                <div>
                  <span>Torneo</span>
                  <strong>{selectedMatch.tournament_category?.tournament?.name || '—'}</strong>
                </div>
                <div>
                  <span>Categoría</span>
                  <strong>{selectedMatch.tournament_category?.category?.display_name || selectedMatch.tournament_category?.category?.name || '—'}</strong>
                </div>
                <div>
                  <span>Estado</span>
                  <strong>{selectedMatch.status?.label || '—'}</strong>
                </div>
                <div>
                  <span>Cancha</span>
                  <strong>{selectedMatch.court || '—'}</strong>
                </div>
                <div>
                  <span>No antes de</span>
                  <strong>{formatDateTime(selectedMatch.not_before_at)}</strong>
                </div>
                <div>
                  <span>Programado</span>
                  <strong>{formatDateTime(selectedMatch.scheduled_at)}</strong>
                </div>
                <div>
                  <span>Duración estimada</span>
                  <strong>{selectedMatch.estimated_duration_minutes ? `${selectedMatch.estimated_duration_minutes} min` : '—'}</strong>
                </div>
                <div>
                  <span>Ganador</span>
                  <strong>{getTeamLabel(selectedMatch.winner_registration)}</strong>
                </div>
              </div>

              <div className="match-detail-section">
                <h4>Participantes</h4>
                <p><strong>Pareja A:</strong> {getTeamLabel(selectedMatch.registration_a)}</p>
                <p><strong>Pareja B:</strong> {getTeamLabel(selectedMatch.registration_b)}</p>
              </div>

              <div className="match-detail-section">
                <h4>Marcador</h4>
                <pre className="match-score-box">
                  {selectedMatch.score_json
                    ? JSON.stringify(selectedMatch.score_json, null, 2)
                    : 'Sin marcador registrado.'}
                </pre>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
