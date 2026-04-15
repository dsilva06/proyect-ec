import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { playerOpenEntriesApi } from '../../features/openEntries/api'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { publicTournamentsApi } from '../../features/tournaments/api'

const initialStandardForm = {
  categoryId: '',
  partnerEmail: '',
  selfRanking: '',
  partnerRanking: '',
  selfRankingSource: 'FEP',
  partnerRankingSource: 'FEP',
}

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

const OPEN_SEGMENT_LABELS = {
  men: 'Masculino',
  women: 'Femenino',
}

const isOpenTournament = (tournament) => String(tournament?.mode || '').toLowerCase() === 'open'

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
  return err?.data?.message || err?.message || 'No pudimos completar la inscripcion.'
}

const resolveOpenEntryErrorMessage = (err) => {
  const fields = [
    'segment',
    'partner_email',
    'partner_first_name',
    'partner_last_name',
    'partner_dni',
    'tournament_id',
  ]
  for (const field of fields) {
    const message = err?.data?.errors?.[field]?.[0]
    if (message) return message
  }
  return err?.data?.message || err?.message || 'No pudimos completar la inscripcion OPEN.'
}

const getRegistrationMessage = (statusCode) => {
  const code = String(statusCode || '').toLowerCase()
  if (code === 'awaiting_partner_acceptance') {
    return 'Pago realizado. Enviamos la invitacion a tu pareja para completar la inscripcion.'
  }
  if (code === 'waitlisted') return 'Inscripcion recibida. Tu equipo quedo en lista de espera.'
  if (code === 'pending') return 'Inscripcion recibida. Aun no esta habilitada para pago.'
  if (code === 'paid') return 'Inscripcion completada correctamente.'
  return 'Inscripcion enviada correctamente.'
}

export default function Tournament() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [openEntries, setOpenEntries] = useState([])
  const [activeTournamentId, setActiveTournamentId] = useState(null)
  const [standardForm, setStandardForm] = useState(initialStandardForm)
  const [openForm, setOpenForm] = useState(initialOpenForm)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const getOpenSegmentOptions = (tournament) => {
    const groupCodes = new Set(
      (tournament?.categories || [])
        .map((category) => String(category?.category?.group_code || '').toLowerCase())
        .filter(Boolean),
    )

    const options = []

    if (
      groupCodes.size === 0 ||
      groupCodes.has('masculino') ||
      groupCodes.has('mixto') ||
      groupCodes.has('mixed')
    ) {
      options.push('men')
    }

    if (
      groupCodes.size === 0 ||
      groupCodes.has('femenino') ||
      groupCodes.has('mixto') ||
      groupCodes.has('mixed')
    ) {
      options.push('women')
    }

    return options
  }

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
      } else {
        setRegistrations([])
        setOpenEntries([])
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

  const registeredTournamentIds = useMemo(
    () =>
      new Set(
        registrations.map((registration) => registration.tournament_category?.tournament?.id),
      ),
    [registrations],
  )

  const openEntryByTournamentId = useMemo(
    () =>
      new Map(
        openEntries.map((entry) => [String(entry.tournament_id || entry.tournament?.id), entry]),
      ),
    [openEntries],
  )

  const handleOpen = (tournamentId) => {
    const tournament = tournaments.find((item) => String(item.id) === String(tournamentId))
    const nextStandardForm = { ...initialStandardForm }
    const nextOpenForm = {
      ...initialOpenForm,
      segment: getOpenSegmentOptions(tournament)[0] || '',
    }

    if (user?.ranking_value) {
      nextStandardForm.selfRanking = String(user.ranking_value)
    }

    setActiveTournamentId(tournamentId)
    setStandardForm(nextStandardForm)
    setOpenForm(nextOpenForm)
    setMessage('')
    setError('')
  }

  const handleClose = () => {
    setActiveTournamentId(null)
  }

  const handleBack = () => {
    if (window.history.length > 1) {
      navigate(-1)
      return
    }

    navigate('/')
  }

  const handleStandardRegister = async (event, tournament) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!user) {
      setError('Inicia sesion para registrarte.')
      return
    }
    if (!standardForm.categoryId) {
      setError('Selecciona una categoria.')
      return
    }
    if (!standardForm.partnerEmail) {
      setError('Ingresa el email del partner.')
      return
    }
    if (!standardForm.selfRanking) {
      setError('Ingresa tu ranking.')
      return
    }
    if (!standardForm.partnerRanking) {
      setError('Ingresa el ranking de tu partner.')
      return
    }

    const categories = tournament?.categories || []
    const selectedCategory = categories.find(
      (category) => String(category.id) === String(standardForm.categoryId),
    )
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
      await load()
    } catch (err) {
      setError(resolveRegistrationErrorMessage(err))
    }
  }

  const handleOpenRegister = async (event, tournament) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!user) {
      setError('Inicia sesion para inscribirte.')
      return
    }
    if (!openForm.segment) {
      setError('Selecciona el segmento de la pareja.')
      return
    }
    if (!openForm.partnerEmail) {
      setError('Ingresa el email de tu pareja.')
      return
    }
    if (!openForm.partnerFirstName.trim()) {
      setError('Ingresa el nombre de tu pareja.')
      return
    }
    if (!openForm.partnerLastName.trim()) {
      setError('Ingresa el apellido de tu pareja.')
      return
    }
    if (!openForm.partnerDni.trim()) {
      setError('Ingresa el DNI de tu pareja.')
      return
    }

    try {
      const entry = await playerOpenEntriesApi.create({
        tournament_id: tournament.id,
        segment: openForm.segment,
        partner_email: openForm.partnerEmail,
        partner_first_name: openForm.partnerFirstName.trim(),
        partner_last_name: openForm.partnerLastName.trim(),
        partner_dni: openForm.partnerDni.trim(),
      })

      const alreadyPaid = Boolean(entry?.paid_at || entry?.payment_is_covered)
      if (!alreadyPaid) {
        const checkout = await playerOpenEntriesApi.pay(entry.id)
        if (checkout?.checkout_url) {
          window.location.assign(checkout.checkout_url)
          return
        }
        setMessage('Entrada creada. No pudimos abrir la pasarela de pago.')
      } else {
        setMessage('Tu entrada OPEN ya esta registrada y pagada.')
      }

      setActiveTournamentId(null)
      await load()
    } catch (err) {
      setError(resolveOpenEntryErrorMessage(err))
    }
  }

  const handleOpenEntryCheckout = async (openEntryId) => {
    setError('')
    setMessage('')
    try {
      const checkout = await playerOpenEntriesApi.pay(openEntryId)
      if (checkout?.checkout_url) {
        window.location.assign(checkout.checkout_url)
        return
      }

      throw new Error('No pudimos abrir Stripe Checkout.')
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos abrir el checkout OPEN.')
    }
  }

  return (
    <div className="tournament-page">
      <button className="ghost-button tournament-back-button" type="button" onClick={handleBack}>
        Volver
      </button>

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
            const existingOpenEntry = openEntryByTournamentId.get(String(tournament.id)) || null
            const isRegistered = registeredTournamentIds.has(tournament.id)
            const participating = isRegistered || Boolean(existingOpenEntry)
            const hasPendingOpenEntry = Boolean(
              existingOpenEntry && !existingOpenEntry.payment_is_covered && !existingOpenEntry.paid_at,
            )

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
                    <p>
                      {tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}
                    </p>
                  </div>
                  <div className="tournament-card-tags">
                    <span className="tag">{tournament.status?.label || 'Publicado'}</span>
                    {isOpen ? <span className="tag accent">OPEN</span> : null}
                  </div>
                </div>
                <div className="tournament-meta">
                  <div>
                    <span>Fechas</span>
                    <strong>
                      {formatRange(tournament.start_date, tournament.end_date, formatDateShort)}
                    </strong>
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
                  {!isOpen ? (
                    <div>
                      <span>Categorias</span>
                      <strong>{categories.length}</strong>
                    </div>
                  ) : null}
                  <div>
                    <span>Costo por equipo</span>
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
                      <span className="tag muted">
                        {hasPendingOpenEntry ? 'Inscripcion OPEN pendiente de pago' : 'Ya inscrito'}
                      </span>
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
                      Inicia sesion
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
              const isOpen = isOpenTournament(tournament)
              const existingOpenEntry = openEntryByTournamentId.get(String(tournament.id)) || null
              const isRegistered = registeredTournamentIds.has(tournament.id)
              const participating = isRegistered || Boolean(existingOpenEntry)
              const selectedCategory =
                categories.find(
                  (category) => String(category.id) === String(standardForm.categoryId),
                ) || null
              const rankingSourceOptions = getRankingSourceOptions(selectedCategory)
              const rankingSourceLabel = rankingSourceOptions.join(' / ')

              return (
                <>
                  <div className="modal-header">
                    <div>
                      <h3>{tournament.name}</h3>
                      <p>
                        {tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}
                      </p>
                    </div>
                    <button className="ghost-button" type="button" onClick={handleClose}>
                      Cerrar
                    </button>
                  </div>
                  <div className="modal-body">
                    <p>{tournament.description || 'Sin descripcion.'}</p>
                    <div className="tournament-meta">
                      <div>
                        <span>Fechas</span>
                        <strong>
                          {formatRange(tournament.start_date, tournament.end_date, formatDateShort)}
                        </strong>
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
                        <span>Costo por equipo</span>
                        <strong>{formatTournamentFee(tournament)}</strong>
                      </div>
                      <div>
                        <span>Premio total</span>
                        <strong>{formatMoney(tournament.prize_money)}</strong>
                      </div>
                    </div>

                    <div className="registration-form">
                      {isOpen ? (
                        <>
                          <h4>Inscribir pareja OPEN</h4>
                          <p className="muted">
                            Inscribe tu pareja. El arbitro asignara la categoria despues del pago y
                            aqui no debes escogerla.
                          </p>
                          <div className="entry-fee-banner">
                            <div>
                              <div className="entry-fee-banner-label">Costo total del equipo</div>
                              <div className="entry-fee-banner-amount">
                                {formatTournamentFee(tournament)}
                              </div>
                            </div>
                            <div className="entry-fee-banner-note">
                              Un solo pago cubre a los dos jugadores.
                              <br />
                              Pagas tu, tu pareja queda inscrita automaticamente.
                            </div>
                          </div>
                          {!user ? <p className="muted">Inicia sesion para inscribirte.</p> : null}
                          {user && existingOpenEntry ? (
                            <div className="player-card-stack">
                              <article className="player-info-card">
                                <div className="player-card-topline">
                                  <span
                                    className={`player-status-pill tone-${
                                      existingOpenEntry.payment_is_covered ||
                                      existingOpenEntry.paid_at
                                        ? 'success'
                                        : 'warning'
                                    }`}
                                  >
                                    {existingOpenEntry.payment_is_covered ||
                                    existingOpenEntry.paid_at
                                      ? 'Pago registrado'
                                      : 'Pago pendiente'}
                                  </span>
                                  <span className="player-soft-note">
                                    {OPEN_SEGMENT_LABELS[existingOpenEntry.segment] ||
                                      existingOpenEntry.segment ||
                                      'OPEN'}
                                  </span>
                                </div>
                                <h5>
                                  {[
                                    existingOpenEntry.partner_first_name,
                                    existingOpenEntry.partner_last_name,
                                  ]
                                    .filter(Boolean)
                                    .join(' ') || 'Partner OPEN'}
                                </h5>
                                <p>{existingOpenEntry.partner_email}</p>
                                <div className="player-metadata-grid">
                                  <div>
                                    <span>DNI partner</span>
                                    <strong>{existingOpenEntry.partner_dni || 'Pendiente'}</strong>
                                  </div>
                                  <div>
                                    <span>Asignacion</span>
                                    <strong>
                                      {existingOpenEntry.registration_id
                                        ? 'Categoria asignada'
                                        : 'Pendiente por arbitro'}
                                    </strong>
                                  </div>
                                </div>
                                {!existingOpenEntry.payment_is_covered &&
                                !existingOpenEntry.paid_at ? (
                                  <div className="form-actions">
                                    <button
                                      className="primary-button"
                                      type="button"
                                      onClick={() =>
                                        handleOpenEntryCheckout(existingOpenEntry.id)
                                      }
                                    >
                                      Ir al checkout
                                    </button>
                                  </div>
                                ) : (
                                  <p className="muted">
                                    Tu pareja OPEN ya quedo registrada y pagada. Solo falta la
                                    asignacion de categoria.
                                  </p>
                                )}
                              </article>
                            </div>
                          ) : null}
                          {user && !existingOpenEntry ? (
                            <form onSubmit={(event) => handleOpenRegister(event, tournament)}>
                              <label>
                                Segmento de juego
                                <select
                                  value={openForm.segment}
                                  onChange={(event) =>
                                    setOpenForm((prev) => ({
                                      ...prev,
                                      segment: event.target.value,
                                    }))
                                  }
                                >
                                  <option value="">Selecciona</option>
                                  {getOpenSegmentOptions(tournament).map((segment) => (
                                    <option key={segment} value={segment}>
                                      {OPEN_SEGMENT_LABELS[segment] || segment}
                                    </option>
                                  ))}
                                </select>
                              </label>
                              <label>
                                Email de tu pareja
                                <input
                                  type="email"
                                  placeholder="nombre@ejemplo.com"
                                  value={openForm.partnerEmail}
                                  onChange={(event) =>
                                    setOpenForm((prev) => ({
                                      ...prev,
                                      partnerEmail: event.target.value,
                                    }))
                                  }
                                />
                                <small className="player-field-help">
                                  Tu pareja no necesita cuenta en la plataforma.
                                </small>
                              </label>
                              <label>
                                Nombre de tu pareja
                                <input
                                  type="text"
                                  placeholder="Nombre"
                                  value={openForm.partnerFirstName}
                                  onChange={(event) =>
                                    setOpenForm((prev) => ({
                                      ...prev,
                                      partnerFirstName: event.target.value,
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                Apellido de tu pareja
                                <input
                                  type="text"
                                  placeholder="Apellido"
                                  value={openForm.partnerLastName}
                                  onChange={(event) =>
                                    setOpenForm((prev) => ({
                                      ...prev,
                                      partnerLastName: event.target.value,
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                DNI / Pasaporte de tu pareja
                                <input
                                  type="text"
                                  placeholder="12345678A"
                                  value={openForm.partnerDni}
                                  onChange={(event) =>
                                    setOpenForm((prev) => ({
                                      ...prev,
                                      partnerDni: event.target.value,
                                    }))
                                  }
                                />
                                <small className="player-field-help">
                                  Necesario para la acreditacion oficial en el torneo.
                                </small>
                              </label>
                              <div className="form-actions">
                                <button className="primary-button" type="submit">
                                  Enviar entrada y pagar
                                </button>
                              </div>
                              {message ? <p className="form-message success">{message}</p> : null}
                              {error ? <p className="form-message error">{error}</p> : null}
                            </form>
                          ) : null}
                          {user && participating && !existingOpenEntry ? (
                            <p className="muted">
                              Ya tienes una entrada registrada para este torneo.
                            </p>
                          ) : null}
                        </>
                      ) : (
                        <>
                          <h4>Inscribir equipo</h4>
                          <div className="entry-fee-banner">
                            <div>
                              <div className="entry-fee-banner-label">Costo total del equipo</div>
                              <div className="entry-fee-banner-amount">
                                {formatTournamentFee(tournament)}
                              </div>
                            </div>
                            <div className="entry-fee-banner-note">
                              Un solo pago cubre a los dos jugadores.
                              <br />
                              Se cobra al confirmar la inscripcion del equipo.
                            </div>
                          </div>
                          {!user ? <p className="muted">Inicia sesion para registrarte.</p> : null}
                          {user && !isRegistered ? (
                            <form onSubmit={(event) => handleStandardRegister(event, tournament)}>
                              <label>
                                Categoria
                                <select
                                  value={standardForm.categoryId}
                                  onChange={(event) => {
                                    const nextId = event.target.value
                                    const category =
                                      categories.find(
                                        (item) => String(item.id) === String(nextId),
                                      ) || null
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
                                    categories.reduce((accumulator, category) => {
                                      const group = category.category?.group_code || 'otros'
                                      if (!accumulator[group]) accumulator[group] = []
                                      accumulator[group].push(category)
                                      return accumulator
                                    }, {}),
                                  ).map(([group, items]) => (
                                    <optgroup key={group} label={GROUP_LABELS[group] || 'Otros'}>
                                      {items.map((category) => (
                                        <option key={category.id} value={category.id}>
                                          {category.category?.display_name ||
                                            category.category?.name}
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
                                  onChange={(event) =>
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      partnerEmail: event.target.value,
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                Tu ranking {rankingSourceLabel}
                                <input
                                  type="number"
                                  min="1"
                                  placeholder="Ej: 350"
                                  value={standardForm.selfRanking}
                                  onChange={(event) =>
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      selfRanking: event.target.value,
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                Fuente de ranking (tu)
                                <select
                                  value={standardForm.selfRankingSource}
                                  onChange={(event) =>
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      selfRankingSource: event.target.value,
                                    }))
                                  }
                                >
                                  {rankingSourceOptions.map((source) => (
                                    <option key={source} value={source}>
                                      {source}
                                    </option>
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
                                  onChange={(event) =>
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      partnerRanking: event.target.value,
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                Fuente de ranking (partner)
                                <select
                                  value={standardForm.partnerRankingSource}
                                  onChange={(event) =>
                                    setStandardForm((prev) => ({
                                      ...prev,
                                      partnerRankingSource: event.target.value,
                                    }))
                                  }
                                >
                                  {rankingSourceOptions.map((source) => (
                                    <option key={source} value={source}>
                                      {source}
                                    </option>
                                  ))}
                                </select>
                              </label>
                              <div className="form-actions">
                                <button className="primary-button" type="submit">
                                  Enviar inscripcion
                                </button>
                              </div>
                              {message ? <p className="form-message success">{message}</p> : null}
                              {error ? <p className="form-message error">{error}</p> : null}
                            </form>
                          ) : null}
                          {user && isRegistered ? (
                            <p className="muted">Ya estas inscrito en este torneo.</p>
                          ) : null}
                        </>
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
