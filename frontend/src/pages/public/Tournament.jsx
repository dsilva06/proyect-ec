import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { playerOpenEntriesApi } from '../../features/openEntries/api'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { publicTournamentsApi } from '../../features/tournaments/api'
import { playerBracketsApi } from '../../features/brackets/api'
import BracketView from '../../components/brackets/BracketView'

// ─── Standard registration form initial state ───────────────────────────────
const initialStandardForm = {
  categoryId: '',
  partnerEmail: '',
  selfRanking: '',
  partnerRanking: '',
  selfRankingSource: 'FEP',
  partnerRankingSource: 'FEP',
}

// ─── OPEN intake form initial state ─────────────────────────────────────────
const initialOpenForm = {
  segment: '',
  partnerEmail: '',
  partnerFirstName: '',
  partnerLastName: '',
  partnerDni: '',
}

const GROUP_LABELS = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  mixto: 'Mixto',
}

const OPEN_SEGMENT_OPTIONS = [
  { value: 'men', label: 'Masculino' },
  { value: 'women', label: 'Femenino' },
]

// ─── Helpers ─────────────────────────────────────────────────────────────────

const isOpenTournament = (tournament) =>
  String(tournament?.mode || '').toLowerCase() === 'open'

const isOpenCategory = (category) =>
  String(category?.category?.level_code || '').toLowerCase() === 'open'

const formatDateShort = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })
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
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount)
}

const formatTournamentFee = (tournament) => {
  const amount = Number(tournament?.entry_fee_amount || 0)
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: tournament?.entry_fee_currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount)
}

const getRankingSourceOptions = (category) => {
  if (isOpenCategory(category)) return ['FIP', 'FEP']
  return ['FEP']
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

const resolveOpenEntryErrorMessage = (err) => {
  const fields = ['segment', 'partner_email', 'partner_first_name', 'partner_last_name', 'partner_dni', 'tournament_id']
  for (const field of fields) {
    const msg = err?.data?.errors?.[field]?.[0]
    if (msg) return msg
  }
  return err?.data?.message || err?.message || 'No pudimos completar la inscripción OPEN.'
}

const getRegistrationMessage = (statusCode) => {
  const code = String(statusCode || '').toLowerCase()
  if (code === 'awaiting_partner_acceptance') {
    return 'Pago realizado. Enviamos la invitación a tu pareja para completar la inscripción.'
  }
  if (code === 'waitlisted') return 'Inscripción recibida. Tu equipo quedó en lista de espera.'
  if (code === 'pending') return 'Inscripción recibida. Aún no está habilitada para pago.'
  if (code === 'paid') return 'Inscripción completada correctamente.'
  return 'Inscripción enviada correctamente.'
}

// ─── Component ───────────────────────────────────────────────────────────────

export default function Tournament() {
  const { user } = useAuth()
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [openEntries, setOpenEntries] = useState([])
  const [activeTournamentId, setActiveTournamentId] = useState(null)
  const [standardForm, setStandardForm] = useState(initialStandardForm)
  const [openForm, setOpenForm] = useState(initialOpenForm)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [brackets, setBrackets] = useState([])

  const load = async () => {
    try {
      const tournamentsData = await publicTournamentsApi.list()
      setTournaments(tournamentsData)
      if (user) {
        const [registrationsData, openEntriesData] = await Promise.all([
          playerRegistrationsApi.list(),
          playerOpenEntriesApi.list(),
        ])
        setRegistrations(registrationsData)
        setOpenEntries(openEntriesData)
      }
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los torneos.')
    }
  }

  useEffect(() => {
    load()
    if (user?.ranking_value) {
      setStandardForm((prev) => ({ ...prev, selfRanking: String(user.ranking_value) }))
    }
  }, [user])

  // Tournament IDs for which the player already has a confirmed registration
  const registeredTournamentIds = useMemo(
    () => new Set(registrations.map((r) => r.tournament_category?.tournament?.id)),
    [registrations],
  )

  // Tournament IDs for which the player already submitted an OPEN entry
  const openEntryTournamentIds = useMemo(
    () => new Set(openEntries.map((e) => e.tournament?.id)),
    [openEntries],
  )

  const isAlreadyParticipating = (tournamentId) =>
    registeredTournamentIds.has(tournamentId) || openEntryTournamentIds.has(tournamentId)

  const handleOpen = async (tournamentId) => {
    setActiveTournamentId(tournamentId)
    setStandardForm(initialStandardForm)
    setOpenForm(initialOpenForm)
    setMessage('')
    setError('')
    try {
      const bracketData = await playerBracketsApi.list({ tournament_id: tournamentId })
      setBrackets(bracketData)
    } catch {
      setBrackets([])
    }
  }

  const handleClose = () => {
    setActiveTournamentId(null)
    setBrackets([])
  }

  // ── Standard registration submit ──────────────────────────────────────────
  const handleStandardRegister = async (event, tournament) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!user) { setError('Inicia sesión para registrarte.'); return }
    if (!standardForm.categoryId) { setError('Selecciona una categoría.'); return }
    if (!standardForm.partnerEmail) { setError('Ingresa el email del partner.'); return }
    if (!standardForm.selfRanking) { setError('Ingresa tu ranking.'); return }
    if (!standardForm.partnerRanking) { setError('Ingresa el ranking de tu partner.'); return }

    const categories = tournament?.categories || []
    const selectedCategory = categories.find((c) => String(c.id) === String(standardForm.categoryId))
    const sourceOptions = getRankingSourceOptions(selectedCategory)
    const selfSource = sourceOptions.includes(standardForm.selfRankingSource)
      ? standardForm.selfRankingSource
      : sourceOptions[0]
    const partnerSource = sourceOptions.includes(standardForm.partnerRankingSource)
      ? standardForm.partnerRankingSource
      : sourceOptions[0]

    try {
      const registration = await playerRegistrationsApi.create({
        tournament_category_id: standardForm.categoryId,
        partner_email: standardForm.partnerEmail,
        self_ranking_value: Number(standardForm.selfRanking),
        self_ranking_source: selfSource,
        partner_ranking_value: Number(standardForm.partnerRanking),
        partner_ranking_source: partnerSource,
      })

      const statusCode = String(registration?.status?.code || '').toLowerCase()
      if (['accepted', 'payment_pending'].includes(statusCode)) {
        const checkout = await playerRegistrationsApi.pay(registration.id)
        if (checkout?.checkout_url) {
          window.location.assign(checkout.checkout_url)
          return
        }
        setMessage('No pudimos abrir la pasarela de pago.')
      } else {
        setMessage(getRegistrationMessage(statusCode))
      }

      setActiveTournamentId(null)
      setBrackets([])
      await load()
    } catch (err) {
      setError(resolveRegistrationErrorMessage(err))
    }
  }

  // ── OPEN intake submit ────────────────────────────────────────────────────
  const handleOpenRegister = async (event, tournament) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!user) { setError('Inicia sesión para inscribirte.'); return }
    if (!openForm.segment) { setError('Selecciona el segmento de la pareja.'); return }
    if (!openForm.partnerEmail) { setError('Ingresa el email de tu pareja.'); return }
    if (!openForm.partnerFirstName) { setError('Ingresa el nombre de tu pareja.'); return }
    if (!openForm.partnerLastName) { setError('Ingresa el apellido de tu pareja.'); return }
    if (!openForm.partnerDni) { setError('Ingresa el DNI de tu pareja.'); return }

    try {
      const entry = await playerOpenEntriesApi.create({
        tournament_id: tournament.id,
        segment: openForm.segment,
        partner_email: openForm.partnerEmail,
        partner_first_name: openForm.partnerFirstName,
        partner_last_name: openForm.partnerLastName,
        partner_dni: openForm.partnerDni,
      })

      // If the entry already has a payment, check out immediately
      const alreadyPaid = Boolean(entry?.paid_at)
      if (!alreadyPaid) {
        const checkout = await playerOpenEntriesApi.pay(entry.id)
        if (checkout?.checkout_url) {
          window.location.assign(checkout.checkout_url)
          return
        }
        setMessage('Entrada creada. No pudimos abrir la pasarela de pago.')
      } else {
        setMessage('Tu entrada OPEN ya está registrada y pagada.')
      }

      setActiveTournamentId(null)
      setBrackets([])
      await load()
    } catch (err) {
      setError(resolveOpenEntryErrorMessage(err))
    }
  }

  return (
    <div className="tournament-page">
      <div className="tournament-page-header">
        <div>
          <h1>Torneos abiertos</h1>
          <p>Registra tu pareja y participa. Un solo pago cubre al equipo completo.</p>
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
            const isOpen = isOpenTournament(tournament)
            const participating = isAlreadyParticipating(tournament.id)

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
                  <div className="tournament-card-tags">
                    <span className="tag">{tournament.status?.label || 'Publicado'}</span>
                    {isOpen && <span className="tag accent">OPEN</span>}
                  </div>
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
                    <span>{isOpen ? 'Entrada' : 'Categorías'}</span>
                    <strong>{isOpen ? 'Asignación por árbitro' : categories.length}</strong>
                  </div>
                  <div>
                    <span>Costo inscripción</span>
                    <strong>{formatTournamentFee(tournament)}</strong>
                  </div>
                  <div>
                    <span>Premio total</span>
                    <strong>{formatMoney(tournament.prize_money)}</strong>
                  </div>
                </div>
                <div className="tournament-actions">
                  {user ? (
                    participating ? (
                      <span className="tag muted">Ya inscrito</span>
                    ) : (
                      <button className="primary-button" type="button">
                        {isOpen ? 'Enviar entrada OPEN' : 'Inscribir equipo'}
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
              const tournament = tournaments.find((t) => t.id === activeTournamentId)
              if (!tournament) return null
              const categories = tournament.categories || []
              const isOpen = isOpenTournament(tournament)
              const participating = isAlreadyParticipating(tournament.id)

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
                        <strong>
                          {formatRange(
                            tournament.registration_open_at,
                            tournament.registration_close_at,
                            formatDateTimeShort,
                          )}
                        </strong>
                      </div>
                      <div>
                        <span>Costo inscripción</span>
                        <strong>{formatTournamentFee(tournament)}</strong>
                      </div>
                      <div>
                        <span>Premio total</span>
                        <strong>{formatMoney(tournament.prize_money)}</strong>
                      </div>
                    </div>

                    {/* ── Registration / intake form ─────────────────────── */}
                    <div className="registration-form">
                      {isOpen ? (
                        <>
                          <h4>Entrada OPEN</h4>
                          <p className="muted">
                            Inscribe tu pareja. El árbitro asignará la categoría después del pago — no necesitas elegirla.
                          </p>
                          {!user && <p className="muted">Inicia sesión para inscribirte.</p>}
                          {user && !participating ? (
                            <form onSubmit={(event) => handleOpenRegister(event, tournament)}>
                              <label>
                                Segmento de juego
                                <select
                                  value={openForm.segment}
                                  onChange={(e) => setOpenForm((prev) => ({ ...prev, segment: e.target.value }))}
                                >
                                  <option value="">— Selecciona —</option>
                                  {OPEN_SEGMENT_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                  ))}
                                </select>
                              </label>
                              <label>
                                Email de tu pareja
                                <input
                                  type="email"
                                  placeholder="nombre@ejemplo.com"
                                  value={openForm.partnerEmail}
                                  onChange={(e) => setOpenForm((prev) => ({ ...prev, partnerEmail: e.target.value }))}
                                />
                                <small className="player-field-help">Tu pareja no necesita cuenta en la plataforma.</small>
                              </label>
                              <label>
                                Nombre de tu pareja
                                <input
                                  type="text"
                                  placeholder="Nombre"
                                  value={openForm.partnerFirstName}
                                  onChange={(e) => setOpenForm((prev) => ({ ...prev, partnerFirstName: e.target.value }))}
                                />
                              </label>
                              <label>
                                Apellido de tu pareja
                                <input
                                  type="text"
                                  placeholder="Apellido"
                                  value={openForm.partnerLastName}
                                  onChange={(e) => setOpenForm((prev) => ({ ...prev, partnerLastName: e.target.value }))}
                                />
                              </label>
                              <label>
                                DNI / Pasaporte de tu pareja
                                <input
                                  type="text"
                                  placeholder="12345678A"
                                  value={openForm.partnerDni}
                                  onChange={(e) => setOpenForm((prev) => ({ ...prev, partnerDni: e.target.value }))}
                                />
                                <small className="player-field-help">Necesario para la acreditación oficial en el torneo.</small>
                              </label>
                              <div className="form-actions">
                                <button className="primary-button" type="submit">
                                  Enviar entrada y pagar
                                </button>
                              </div>
                              {message && <p className="form-message success">{message}</p>}
                              {error && <p className="form-message error">{error}</p>}
                            </form>
                          ) : null}
                          {user && participating ? (
                            <p className="muted">Ya tienes una entrada registrada para este torneo.</p>
                          ) : null}
                        </>
                      ) : (
                        <>
                          <h4>Inscribir equipo</h4>
                          {!user && <p className="muted">Inicia sesión para registrarte.</p>}
                          {user && !participating ? (
                            <form onSubmit={(event) => handleStandardRegister(event, tournament)}>
                              <label>
                                Categoría
                                <select
                                  value={standardForm.categoryId}
                                  onChange={(event) => {
                                    const nextId = event.target.value
                                    const category = categories.find((c) => String(c.id) === String(nextId)) || null
                                    const options = getRankingSourceOptions(category)
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      categoryId: nextId,
                                      selfRankingSource: options[0],
                                      partnerRankingSource: options[0],
                                    }))
                                  }}
                                >
                                  <option value="">Selecciona</option>
                                  {Object.entries(
                                    categories.reduce((acc, cat) => {
                                      const group = cat.category?.group_code || 'otros'
                                      if (!acc[group]) acc[group] = []
                                      acc[group].push(cat)
                                      return acc
                                    }, {}),
                                  ).map(([group, items]) => (
                                    <optgroup key={group} label={GROUP_LABELS[group] || 'Otros'}>
                                      {items.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                          {cat.category?.display_name || cat.category?.name}
                                        </option>
                                      ))}
                                    </optgroup>
                                  ))}
                                </select>
                              </label>
                              <label>
                                Email del partner
                                <input
                                  type="email"
                                  placeholder="nombre@ejemplo.com"
                                  value={standardForm.partnerEmail}
                                  onChange={(e) => setStandardForm((prev) => ({ ...prev, partnerEmail: e.target.value }))}
                                />
                              </label>
                              {(() => {
                                const selectedCategory = categories.find(
                                  (c) => String(c.id) === String(standardForm.categoryId),
                                ) || null
                                const sourceOptions = getRankingSourceOptions(selectedCategory)
                                const rankingSourceLabel = sourceOptions.join(' / ')
                                return (
                                  <>
                                    <label>
                                      Tu ranking {rankingSourceLabel}
                                      <input
                                        type="number"
                                        min="1"
                                        placeholder="Ej: 350"
                                        value={standardForm.selfRanking}
                                        onChange={(e) => setStandardForm((prev) => ({ ...prev, selfRanking: e.target.value }))}
                                      />
                                    </label>
                                    <label>
                                      Fuente de ranking (tú)
                                      <select
                                        value={standardForm.selfRankingSource}
                                        onChange={(e) => setStandardForm((prev) => ({ ...prev, selfRankingSource: e.target.value }))}
                                      >
                                        {sourceOptions.map((src) => (
                                          <option key={src} value={src}>{src}</option>
                                        ))}
                                      </select>
                                    </label>
                                    <label>
                                      Ranking del partner {rankingSourceLabel}
                                      <input
                                        type="number"
                                        min="1"
                                        placeholder="Ej: 420"
                                        value={standardForm.partnerRanking}
                                        onChange={(e) => setStandardForm((prev) => ({ ...prev, partnerRanking: e.target.value }))}
                                      />
                                    </label>
                                    <label>
                                      Fuente de ranking (partner)
                                      <select
                                        value={standardForm.partnerRankingSource}
                                        onChange={(e) => setStandardForm((prev) => ({ ...prev, partnerRankingSource: e.target.value }))}
                                      >
                                        {sourceOptions.map((src) => (
                                          <option key={src} value={src}>{src}</option>
                                        ))}
                                      </select>
                                    </label>
                                  </>
                                )
                              })()}
                              <div className="form-actions">
                                <button className="primary-button" type="submit">Enviar inscripción</button>
                              </div>
                              {message && <p className="form-message success">{message}</p>}
                              {error && <p className="form-message error">{error}</p>}
                            </form>
                          ) : null}
                          {user && participating ? <p className="muted">Ya estás inscrito en este torneo.</p> : null}
                        </>
                      )}
                    </div>

                    {/* ── Published brackets ────────────────────────────── */}
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
