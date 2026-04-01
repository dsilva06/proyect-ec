import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { playerTeamsApi } from '../../features/teams/api'
import { publicTournamentsApi } from '../../features/tournaments/api'
import { playerBracketsApi } from '../../features/brackets/api'
import BracketView from '../../components/brackets/BracketView'

const initialForm = {
  categoryId: '',
  partnerEmail: '',
  selfRanking: '',
  partnerRanking: '',
  selfRankingSource: 'FEP',
  partnerRankingSource: 'FEP',
}

const GROUP_LABELS = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  mixto: 'Mixto',
}

const formatDateShort = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleDateString('es-ES', {
    month: 'short',
    day: 'numeric',
  })
}

const formatDateTimeShort = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleString('es-ES', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

const formatRange = (start, end, formatter) => {
  const startLabel = formatter(start)
  const endLabel = formatter(end)
  if (startLabel && endLabel) return `${startLabel} → ${endLabel}`
  return startLabel || endLabel || 'Por confirmar'
}

const formatMoney = (value) => {
  if (value === null || value === undefined || value === '') return 'Por confirmar'
  const amount = Number(value)
  if (!Number.isFinite(amount)) return 'Por confirmar'
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount)
}

const resolveRegistrationErrorMessage = (err) => {
  const rankingMessage = err?.data?.errors?.ranking?.[0]
  if (rankingMessage) return rankingMessage
  const selfSourceMessage = err?.data?.errors?.self_ranking_source?.[0]
  if (selfSourceMessage) return selfSourceMessage
  const partnerSourceMessage = err?.data?.errors?.partner_ranking_source?.[0]
  if (partnerSourceMessage) return partnerSourceMessage
  return err?.data?.message || err?.message || 'No pudimos completar la inscripción.'
}

const isOpenCategory = (category) => String(category?.category?.level_code || '').toLowerCase() === 'open'

const getRankingSourceOptions = (category) => {
  if (isOpenCategory(category)) {
    return ['FIP', 'FEP']
  }

  return ['FEP']
}

export default function Tournament() {
  const { user } = useAuth()
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [activeTournamentId, setActiveTournamentId] = useState(null)
  const [form, setForm] = useState(initialForm)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [brackets, setBrackets] = useState([])

  const load = async () => {
    try {
      const tournamentsData = await publicTournamentsApi.list()
      setTournaments(tournamentsData)
      if (user) {
        const registrationsData = await playerRegistrationsApi.list()
        setRegistrations(registrationsData)
      }
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los torneos.')
    }
  }

  useEffect(() => {
    load()
    if (user?.ranking_value) {
      setForm((prev) => ({ ...prev, selfRanking: String(user.ranking_value) }))
    }
  }, [user])

  const registeredIds = useMemo(() => {
    return new Set(registrations.map((registration) => registration.tournament_category?.tournament?.id))
  }, [registrations])

  const handleOpen = async (tournamentId) => {
    setActiveTournamentId(tournamentId)
    setForm(initialForm)
    setMessage('')
    setError('')
    try {
      const bracketData = await playerBracketsApi.list({ tournament_id: tournamentId })
      setBrackets(bracketData)
    } catch (err) {
      setBrackets([])
    }
  }

  const handleClose = () => {
    setActiveTournamentId(null)
    setBrackets([])
  }

  const handleRegister = async (event, tournamentId) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!user) {
      setError('Inicia sesión para registrarte.')
      return
    }

    if (!form.categoryId) {
      setError('Selecciona una categoría.')
      return
    }

    if (!form.partnerEmail) {
      setError('Ingresa el email del partner.')
      return
    }

    if (!form.selfRanking) {
      setError('Ingresa tu ranking.')
      return
    }

    if (!form.partnerRanking) {
      setError('Ingresa el ranking de tu partner.')
      return
    }

    const tournament = tournaments.find((item) => String(item.id) === String(tournamentId))
    const selectedCategory = (tournament?.categories || []).find((item) => String(item.id) === String(form.categoryId))
    const sourceOptions = getRankingSourceOptions(selectedCategory)
    const selfSource = sourceOptions.includes(form.selfRankingSource) ? form.selfRankingSource : sourceOptions[0]
    const partnerSource = sourceOptions.includes(form.partnerRankingSource) ? form.partnerRankingSource : sourceOptions[0]

    try {
      const team = await playerTeamsApi.create({
        partner_email: form.partnerEmail,
      })

      await playerRegistrationsApi.create({
        tournament_category_id: form.categoryId,
        team_id: team.id,
        partner_email: form.partnerEmail,
        self_ranking_value: Number(form.selfRanking),
        self_ranking_source: selfSource,
        partner_ranking_value: Number(form.partnerRanking),
        partner_ranking_source: partnerSource,
      })

      setMessage('Inscripción enviada correctamente.')
      setActiveTournamentId(null)
      setBrackets([])
      await load()
    } catch (err) {
      setError(resolveRegistrationErrorMessage(err))
    }
  }

  return (
    <div className="tournament-page">
      <div className="tournament-page-header">
        <div>
          <h1>Torneos abiertos</h1>
          <p>Registra tu equipo en las categorías disponibles.</p>
        </div>
        <div className="tournament-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      {tournaments.length === 0 ? (
        <div className="empty-state">No hay torneos publicados.</div>
      ) : (
        <div className="tournament-list">
          {tournaments.map((tournament) => {
            const categories = tournament.categories || []
            const isRegistered = registeredIds.has(tournament.id)

            return (
              <article
                key={tournament.id}
                className="tournament-card"
                onClick={() => handleOpen(tournament.id)}
                role="button"
                tabIndex={0}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') handleOpen(tournament.id)
                }}
              >
                <div className="tournament-card-header">
                  <div>
                    <h3>{tournament.name}</h3>
                    <p>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</p>
                  </div>
                  <span className="tag">{tournament.status?.label || 'Publicado'}</span>
                </div>
                <div className="tournament-meta">
                  <div>
                    <span>Fechas</span>
                    <strong>{formatRange(tournament.start_date, tournament.end_date, formatDateShort)}</strong>
                  </div>
                  <div>
                    <span>Inscripciones</span>
                    <strong>
                      {formatRange(
                        tournament.registration_open_at,
                        tournament.registration_close_at,
                        formatDateTimeShort,
                      )}
                    </strong>
                  </div>
                  <div>
                    <span>Categorías</span>
                    <strong>{categories.length}</strong>
                  </div>
                  <div>
                    <span>Premio total</span>
                    <strong>{formatMoney(tournament.prize_money)}</strong>
                  </div>
                </div>
                <div className="tournament-actions">
                  {user ? (
                    isRegistered ? (
                      <span className="tag muted">Ya inscrito</span>
                    ) : (
                      <button className="primary-button" type="button">
                        Inscribir equipo
                      </button>
                    )
                  ) : (
                    <Link
                      className="secondary-button"
                      to="/login"
                      onClick={(event) => event.stopPropagation()}
                    >
                      Inicia sesión
                    </Link>
                  )}
                </div>

              </article>
            )
          })}
        </div>
      )}

      {activeTournamentId ? (
        <div className="modal-backdrop" onClick={handleClose}>
          <div className="modal-card" onClick={(event) => event.stopPropagation()}>
            {(() => {
              const tournament = tournaments.find((item) => item.id === activeTournamentId)
              if (!tournament) return null
              const categories = tournament.categories || []
              const isRegistered = registeredIds.has(tournament.id)
              const selectedCategory = categories.find((item) => String(item.id) === String(form.categoryId)) || null
              const rankingSourceOptions = getRankingSourceOptions(selectedCategory)
              const rankingSourceLabel = rankingSourceOptions.join(' / ')

              return (
                <>
                  <div className="modal-header">
                    <div>
                      <h3>{tournament.name}</h3>
                      <p>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</p>
                    </div>
                    <button className="ghost-button" type="button" onClick={handleClose}>Cerrar</button>
                  </div>
                  <div className="modal-body">
                    <p>{tournament.description || 'Sin descripción.'}</p>
                    <div className="tournament-meta">
                      <div>
                        <span>Fechas</span>
                        <strong>{formatRange(tournament.start_date, tournament.end_date, formatDateShort)}</strong>
                      </div>
                      <div>
                        <span>Inscripciones</span>
                        <strong>{formatRange(tournament.registration_open_at, tournament.registration_close_at, formatDateTimeShort)}</strong>
                      </div>
                      <div>
                        <span>Premio total</span>
                        <strong>{formatMoney(tournament.prize_money)}</strong>
                      </div>
                    </div>

                    <div className="registration-form">
                      <h4>Inscribir equipo</h4>
                      {!user && <p className="muted">Inicia sesión para registrarte.</p>}
                      {user && !isRegistered ? (
                        <form onSubmit={(event) => handleRegister(event, tournament.id)}>
                          <label>
                            Categoría
                            <select
                              value={form.categoryId}
                              onChange={(event) => {
                                const nextCategoryId = event.target.value
                                const category = categories.find((item) => String(item.id) === String(nextCategoryId)) || null
                                const options = getRankingSourceOptions(category)
                                setForm((prev) => ({
                                  ...prev,
                                  categoryId: nextCategoryId,
                                  selfRankingSource: options[0],
                                  partnerRankingSource: options[0],
                                }))
                              }}
                            >
                              <option value="">Selecciona</option>
                              {Object.entries(
                                categories.reduce((acc, category) => {
                                  const group = category.category?.group_code || 'otros'
                                  if (!acc[group]) acc[group] = []
                                  acc[group].push(category)
                                  return acc
                                }, {}),
                              ).map(([group, items]) => (
                                <optgroup key={group} label={GROUP_LABELS[group] || 'Otros'}>
                                  {items.map((category) => (
                                    <option key={category.id} value={category.id}>
                                      {category.category?.display_name || category.category?.name}
                                    </option>
                                  ))}
                                </optgroup>
                              ))}
                            </select>
                          </label>
                          <label>
                            Email del partner (obligatorio)
                            <input
                              type="email"
                              value={form.partnerEmail}
                              onChange={(event) => setForm((prev) => ({ ...prev, partnerEmail: event.target.value }))}
                            />
                          </label>
                          <label>
                            Tu ranking {rankingSourceLabel} (obligatorio)
                            <input
                              type="number"
                              min="1"
                              value={form.selfRanking}
                              onChange={(event) => setForm((prev) => ({ ...prev, selfRanking: event.target.value }))}
                            />
                          </label>
                          <label>
                            Fuente ranking (tú)
                            <select
                              value={form.selfRankingSource}
                              onChange={(event) => setForm((prev) => ({ ...prev, selfRankingSource: event.target.value }))}
                            >
                              {rankingSourceOptions.map((source) => (
                                <option key={source} value={source}>
                                  {source}
                                </option>
                              ))}
                            </select>
                          </label>
                          <label>
                            Ranking del partner {rankingSourceLabel} (obligatorio)
                            <input
                              type="number"
                              min="1"
                              value={form.partnerRanking}
                              onChange={(event) => setForm((prev) => ({ ...prev, partnerRanking: event.target.value }))}
                            />
                          </label>
                          <label>
                            Fuente ranking (partner)
                            <select
                              value={form.partnerRankingSource}
                              onChange={(event) => setForm((prev) => ({ ...prev, partnerRankingSource: event.target.value }))}
                            >
                              {rankingSourceOptions.map((source) => (
                                <option key={source} value={source}>
                                  {source}
                                </option>
                              ))}
                            </select>
                          </label>
                          <div className="form-actions">
                            <button className="primary-button" type="submit">Enviar inscripción</button>
                          </div>
                          {message && <p className="form-message success">{message}</p>}
                          {error && <p className="form-message error">{error}</p>}
                        </form>
                      ) : null}
                      {user && isRegistered ? <p className="muted">Ya estás inscrito.</p> : null}
                    </div>

                    <div className="panel-card bracket-panel">
                      <div className="panel-header">
                        <h4>Cuadros publicados</h4>
                        <span className="tag muted">{brackets.length}</span>
                      </div>
                      {brackets.length === 0 ? (
                        <div className="empty-state">No hay cuadros publicados.</div>
                      ) : (
                        brackets.map((bracket) => (
                          <div key={bracket.id} className="bracket-view">
                            <div className="panel-header">
                              <h5>{bracket.tournament_category?.category?.display_name || 'Categoría'}</h5>
                              <span className="tag muted">{bracket.status?.label || 'Publicado'}</span>
                            </div>
                            <BracketView bracket={bracket} />
                          </div>
                        ))
                      )}
                    </div>
                  </div>
                </>
              )
            })()}
          </div>
        </div>
      ) : null}
    </div>
  )
}
