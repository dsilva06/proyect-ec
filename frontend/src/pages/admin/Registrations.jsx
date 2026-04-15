import { useEffect, useMemo, useState } from 'react'
import { statusesApi } from '../../features/statuses/api'
import { adminRegistrationsApi } from '../../features/registrations/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

const QUICK_FILTERS = [
  { id: 'all', label: 'Todo' },
  { id: 'needs_payment', label: 'Pago pendiente' },
  { id: 'missing_queue', label: 'Sin cola' },
  { id: 'wildcards', label: 'Wildcards' },
  { id: 'paid', label: 'Devolver pago' },
]

const formatDateShort = (value) => {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value).slice(0, 10)
  return parsed.toLocaleDateString('es-ES')
}

const getStatusCode = (registration) => String(registration.status?.code || '')

const isProRegistration = (registration) =>
  String(registration?.tournament_category?.tournament?.mode || '').toLowerCase() === 'pro'

const isOpenTournamentRegistration = (registration) =>
  String(registration?.tournament_category?.tournament?.mode || '').toLowerCase() === 'open'

const hasTournamentStarted = (registration) => {
  const startDate = registration?.tournament_category?.tournament?.start_date
  if (!startDate) return false
  const parsed = Date.parse(startDate)
  if (Number.isNaN(parsed)) return false
  return parsed <= Date.now()
}

const isAcceptedRegistration = (registration) =>
  getStatusCode(registration) === 'accepted' || Boolean(registration.accepted_at)

const shouldRefundRegistration = (registration) => {
  if (!isProRegistration(registration)) return false
  if (isAcceptedRegistration(registration)) return false

  const code = getStatusCode(registration)
  if (code === 'paid') return true
  if (code === 'waitlisted' && hasTournamentStarted(registration)) return true

  return false
}

const hasMissingQueue = (registration) =>
  registration.queue_position === null
  || registration.queue_position === undefined
  || registration.queue_position === ''

const getRankingRows = (registration) =>
  (registration.rankings || [])
    .slice()
    .sort((a, b) => Number(a.slot || 0) - Number(b.slot || 0))

const hasRankingInfo = (registration) =>
  getRankingRows(registration).some((row) => row.ranking_value !== null && row.ranking_value !== undefined)

const isOpenCategory = (registration) =>
  String(registration?.tournament_category?.category?.level_code || '').toLowerCase() === 'open'

const getAllowedRankingSources = (registration) => (isOpenCategory(registration) ? ['FIP', 'FEP'] : ['FEP'])

const getDefaultRankingSource = (registration) => (isOpenCategory(registration) ? 'FIP' : 'FEP')

const getMembersLabel = (registration) => {
  const byRank = getRankingRows(registration)
    .map((row) => {
      const name = row.user?.name || row.invited_email || `Jugador ${row.slot}`
      const rank = row.ranking_value ? ` (#${row.ranking_value})` : ''
      return `${name}${rank}`
    })
    .join(' • ')

  if (byRank) return byRank

  return (registration.team?.members || [])
    .map((member) => `${member.name}${member.ranking_value ? ` (#${member.ranking_value})` : ''}`)
    .join(' • ') || 'Sin jugadores'
}

const buildRankingDraft = (registration) => {
  const rankingMap = new Map(getRankingRows(registration).map((item) => [Number(item.slot), item]))
  const allowedSources = getAllowedRankingSources(registration)
  const defaultSource = getDefaultRankingSource(registration)

  return [1, 2].map((slot) => {
    const row = rankingMap.get(slot)
    const source = row?.ranking_source && allowedSources.includes(row.ranking_source)
      ? row.ranking_source
      : defaultSource

    return {
      slot,
      ranking_value: row?.ranking_value ?? '',
      ranking_source: source,
      is_verified: Boolean(row?.is_verified),
      player_name: row?.user?.name || row?.invited_email || `Jugador ${slot}`,
    }
  })
}

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
  const [error, setError] = useState('')
  const [draggedRegistrationId, setDraggedRegistrationId] = useState(null)
  const [dropTargetStatusId, setDropTargetStatusId] = useState('')
  const [updatingRegistrationId, setUpdatingRegistrationId] = useState(null)
  const [quickFilter, setQuickFilter] = useState('all')
  const [selectedRegistrationId, setSelectedRegistrationId] = useState(null)
  const [detailForm, setDetailForm] = useState({
    status_id: '',
    notes_admin: '',
  })
  const [rankingDraft, setRankingDraft] = useState([])
  const [isSavingDetail, setIsSavingDetail] = useState(false)
  const [isSavingRankings, setIsSavingRankings] = useState(false)
  const [rulesOpen, setRulesOpen] = useState(false)

  const statusOptions = useMemo(() => statuses
    .filter((status) => status.module === 'registration')
    .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0)), [statuses])

  const statusByCode = useMemo(() => {
    const map = new Map()
    statusOptions.forEach((status) => {
      map.set(String(status.code), status)
    })
      return map
  }, [statusOptions])

  const boardColumns = useMemo(() => {
    const accepted = statusByCode.get('accepted')
    const paymentPending = statusByCode.get('payment_pending')
    const waitlisted = statusByCode.get('waitlisted')
    const pending = statusByCode.get('pending')
    const waitlistTarget = waitlisted || pending

    return [
      {
        key: 'accepted',
        label: 'Aceptado',
        statusId: accepted ? String(accepted.id) : '',
        canDrop: Boolean(accepted),
      },
      {
        key: 'payment',
        label: 'Pago',
        statusId: paymentPending ? String(paymentPending.id) : '',
        canDrop: Boolean(paymentPending),
      },
      {
        key: 'waitlist',
        label: 'Lista de espera',
        statusId: waitlistTarget ? String(waitlistTarget.id) : '',
        canDrop: Boolean(waitlistTarget),
      },
      {
        key: 'refund',
        label: 'Devolver pago',
        statusId: '',
        canDrop: false,
      },
    ]
  }, [statusByCode])

  const load = async (overrideFilters = filters) => {
    try {
      setError('')
      const [registrationsData, statusesData, tournamentsData] = await Promise.all([
        adminRegistrationsApi.list(overrideFilters),
        statusesApi.list(),
        adminTournamentsApi.list(),
      ])
      setRegistrations(registrationsData)
      setStatuses(statusesData)
      setTournaments(tournamentsData)
      return registrationsData
    } catch (err) {
      setError(err?.message || 'No pudimos cargar las inscripciones.')
      return []
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
    load(nextFilters)
  }

  const handleSearchChange = (event) => {
    setSearch(event.target.value)
  }

  const filteredRegistrations = useMemo(() => {
    const term = search.trim().toLowerCase()
    return registrations.filter((registration) => {
      const teamName = registration.team?.display_name || ''
      const tournamentName = registration.tournament_category?.tournament?.name || ''
      const categoryName = registration.tournament_category?.category?.display_name
        || registration.tournament_category?.category?.name
        || ''
      const players = getMembersLabel(registration)

      const matchesSearch = !term
        || `${teamName} ${tournamentName} ${categoryName} ${players}`.toLowerCase().includes(term)
      if (!matchesSearch) return false

      const statusCode = getStatusCode(registration)
      if (quickFilter === 'needs_payment') return ['accepted', 'payment_pending'].includes(statusCode)
      if (quickFilter === 'missing_queue') return hasMissingQueue(registration)
      if (quickFilter === 'wildcards') return Boolean(registration.is_wildcard)
      if (quickFilter === 'paid') return shouldRefundRegistration(registration)
      return true
    })
  }, [registrations, search, quickFilter])

  const quickFilterCounts = useMemo(() => ({
    all: registrations.length,
    needs_payment: registrations.filter((registration) => ['accepted', 'payment_pending'].includes(getStatusCode(registration))).length,
    missing_queue: registrations.filter(hasMissingQueue).length,
    wildcards: registrations.filter((registration) => Boolean(registration.is_wildcard)).length,
    paid: registrations.filter((registration) => shouldRefundRegistration(registration)).length,
  }), [registrations])

  const groupedByColumn = useMemo(() => {
    const sortRegistrations = (a, b) => {
      const queueA = Number(a.queue_position || Number.MAX_SAFE_INTEGER)
      const queueB = Number(b.queue_position || Number.MAX_SAFE_INTEGER)
      if (queueA !== queueB) return queueA - queueB
      return String(a.created_at || '').localeCompare(String(b.created_at || ''))
    }

    const buckets = new Map()
    boardColumns.forEach((column) => {
      buckets.set(column.key, [])
    })

    filteredRegistrations.forEach((registration) => {
      const code = getStatusCode(registration)
      let columnKey = 'waitlist'

      if (isAcceptedRegistration(registration) || code === 'awaiting_partner_acceptance') {
        columnKey = 'accepted'
      } else if (shouldRefundRegistration(registration)) {
        columnKey = 'refund'
      } else if (code === 'payment_pending') {
        columnKey = 'payment'
      } else if (code === 'waitlisted') {
        columnKey = 'waitlist'
      } else if (code === 'pending' || code === 'paid' || !code) {
        columnKey = 'payment'
      }

      const list = buckets.get(columnKey)
      if (list) list.push(registration)
    })

    for (const list of buckets.values()) {
      list.sort(sortRegistrations)
    }

    return { buckets }
  }, [filteredRegistrations, boardColumns])

  const selectedRegistration = useMemo(
    () => registrations.find((registration) => String(registration.id) === String(selectedRegistrationId)) || null,
    [registrations, selectedRegistrationId],
  )
  const selectedRankingSourceOptions = useMemo(
    () => (selectedRegistration ? getAllowedRankingSources(selectedRegistration) : ['FEP']),
    [selectedRegistration],
  )

  const clearDragState = () => {
    setDraggedRegistrationId(null)
    setDropTargetStatusId('')
  }

  const handleCardDragStart = (event, registrationId) => {
    const id = String(registrationId)
    event.dataTransfer.setData('text/plain', id)
    event.dataTransfer.effectAllowed = 'move'
    setDraggedRegistrationId(id)
  }

  const handleCardDragEnd = () => {
    clearDragState()
  }

  const handleColumnDragOver = (event, statusId) => {
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
    setDropTargetStatusId(String(statusId))
  }

  const handleStatusUpdate = async (registrationId, statusId) => {
    try {
      await adminRegistrationsApi.update(registrationId, { status_id: statusId })
      await load()
      return true
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar la inscripción.')
      return false
    }
  }

  const handleColumnDrop = async (event, targetStatusId) => {
    event.preventDefault()
    if (!targetStatusId) return

    const draggedId = draggedRegistrationId || event.dataTransfer.getData('text/plain')
    clearDragState()

    if (!draggedId) return

    const registration = registrations.find((item) => String(item.id) === String(draggedId))
    if (!registration) return
    if (String(registration.status?.id || '') === String(targetStatusId)) return

    try {
      setUpdatingRegistrationId(String(draggedId))
      const updated = await handleStatusUpdate(draggedId, targetStatusId)
      if (updated && String(selectedRegistrationId) === String(draggedId)) {
        setDetailForm((prev) => ({ ...prev, status_id: String(targetStatusId) }))
      }
    } finally {
      setUpdatingRegistrationId(null)
    }
  }

  const openRegistrationDetail = (registration) => {
    setSelectedRegistrationId(String(registration.id))
    setDetailForm({
      status_id: String(registration.status?.id || ''),
      notes_admin: registration.notes_admin || '',
    })
    setRankingDraft(buildRankingDraft(registration))
  }

  const closeRegistrationDetail = () => {
    setSelectedRegistrationId(null)
    setDetailForm({ status_id: '', notes_admin: '' })
    setRankingDraft([])
  }

  const handleDetailFieldChange = (field) => (event) => {
    setDetailForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const handleRankingFieldChange = (slot, field) => (event) => {
    const value = field === 'is_verified' ? event.target.checked : event.target.value
    setRankingDraft((prev) => prev.map((row) => (row.slot === slot ? { ...row, [field]: value } : row)))
  }

  const handleSaveDetail = async () => {
    if (!selectedRegistration) return
    try {
      setIsSavingDetail(true)
      setError('')
      await adminRegistrationsApi.update(selectedRegistration.id, {
        status_id: detailForm.status_id || null,
        notes_admin: detailForm.notes_admin || null,
      })
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos guardar los cambios de inscripción.')
    } finally {
      setIsSavingDetail(false)
    }
  }

  const handleSaveRankings = async () => {
    if (!selectedRegistration) return

    try {
      setIsSavingRankings(true)
      setError('')

      const rankingsPayload = rankingDraft.map((row) => ({
        slot: row.slot,
        ranking_value: row.ranking_value === '' || row.ranking_value === null ? null : Number(row.ranking_value),
        ranking_source: row.ranking_value === '' || row.ranking_value === null ? null : row.ranking_source,
        is_verified: Boolean(row.is_verified),
      }))

      await adminRegistrationsApi.updateRankings(selectedRegistration.id, { rankings: rankingsPayload })
      const refreshed = await load()
      const current = refreshed.find((item) => String(item.id) === String(selectedRegistration.id))
      if (current) {
        setRankingDraft(buildRankingDraft(current))
      }
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos guardar rankings.')
    } finally {
      setIsSavingRankings(false)
    }
  }

  useEffect(() => {
    if (!selectedRegistrationId) return undefined

    const handleEsc = (event) => {
      if (event.key === 'Escape') {
        closeRegistrationDetail()
      }
    }

    window.addEventListener('keydown', handleEsc)
    return () => window.removeEventListener('keydown', handleEsc)
  }, [selectedRegistrationId])

  const renderCard = (registration, { compact = false, draggable = true } = {}) => {
    const registrationId = String(registration.id)
    const isSelected = String(selectedRegistrationId) === registrationId
    const categoryName = registration.tournament_category?.category?.display_name
      || registration.tournament_category?.category?.name
      || 'Categoría'
    const isWildcard = Boolean(registration.is_wildcard)
    const hasRankings = hasRankingInfo(registration)
    const isOpenMode = isOpenTournamentRegistration(registration)
    const isOperationalCard = !compact && draggable

    return (
      <article
        key={registration.id}
        className={`registrations-kanban-card registrations-trello-card${compact ? ' is-compact' : ''}${!isOperationalCard ? ' is-readonly-card' : ''}${draggedRegistrationId === registrationId ? ' is-dragging' : ''}${updatingRegistrationId === registrationId ? ' is-updating' : ''}${isSelected ? ' is-selected' : ''}${isWildcard ? ' is-wildcard' : ''}`}
        draggable={isOperationalCard}
        role="button"
        tabIndex={0}
        onClick={() => openRegistrationDetail(registration)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            openRegistrationDetail(registration)
          }
        }}
        onDragStart={isOperationalCard ? (event) => handleCardDragStart(event, registration.id) : undefined}
        onDragEnd={isOperationalCard ? handleCardDragEnd : undefined}
      >
        <div className="registrations-trello-card-head">
          <strong title={registration.team?.display_name || 'Equipo'}>
            {registration.team?.display_name || 'Equipo'}
          </strong>
          <span className="tag muted">#{registration.queue_position ?? '—'}</span>
        </div>
        <span className="muted registrations-trello-players" title={getMembersLabel(registration)}>
          {getMembersLabel(registration)}
        </span>
        <div className="registrations-trello-card-foot">
          <span className="registrations-trello-category" title={categoryName}>{categoryName}</span>
          <span className="muted">{formatDateShort(registration.created_at)}</span>
        </div>
        <div className="registrations-trello-card-badges">
          {isWildcard ? <span className="tag registrations-tag-wildcard">Wildcard</span> : null}
          {(isOpenMode || (isWildcard && !hasRankings)) ? <span className="tag muted">Sin ranking</span> : null}
          {isWildcard ? (
            <span className="tag muted">{registration.wildcard_fee_waived ? 'Pago exonerado' : 'Pago normal'}</span>
          ) : null}
        </div>
      </article>
    )
  }

  return (
    <section className="admin-page registrations-trello-page">
      <div className="admin-page-header">
        <div>
          <h3>Inscripciones</h3>
          <p>Funnel optimizado por importancia con edición completa desde Trello.</p>
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
              .filter((tournament) => !filters.tournament_id || String(tournament.id) === String(filters.tournament_id))
              .flatMap((tournament) =>
                (tournament.categories || []).map((category) => (
                  <option key={category.category?.id} value={category.category?.id}>
                    {category.category?.display_name || category.category?.name}
                  </option>
                )))}
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

      <div className="registrations-trello-toolbar">
        <div className="registrations-trello-toolbar-meta">
          <span className="tag muted">{filteredRegistrations.length} inscripciones visibles</span>
          <button
            className="rules-toggle-btn"
            type="button"
            onClick={() => setRulesOpen((prev) => !prev)}
          >
            {rulesOpen ? 'Ocultar reglas' : 'Ver reglas de refund'}
          </button>
        </div>
        {rulesOpen && (
          <div className="registrations-rules-panel">
            <strong>Reglas de devolución (Refund)</strong>
            <ul>
              <li>Solo aplica en torneos <strong>PRO</strong>.</li>
              <li>Inscripciones <strong>pagadas pero no aceptadas</strong> → candidatas a refund.</li>
              <li>Inscripciones en <strong>waitlist no aceptadas</strong> cuando el torneo ya inició → candidatas a refund.</li>
              <li>Inscripciones <strong>aceptadas</strong> no califican para refund.</li>
            </ul>
          </div>
        )}
        <div className="registrations-trello-toolbar-filters">
          {QUICK_FILTERS.map((option) => (
            <button
              key={option.id}
              type="button"
              className={`registrations-focus-toggle${quickFilter === option.id ? ' is-active' : ''}`}
              onClick={() => setQuickFilter(option.id)}
            >
              {option.label}
              <span className="tag muted">{quickFilterCounts[option.id] ?? 0}</span>
            </button>
          ))}
        </div>
      </div>

      <div className={`registrations-trello-layout${selectedRegistration ? ' has-drawer' : ''}`}>
        <div className="registrations-trello-board-shell">
          {filteredRegistrations.length === 0 ? (
            <div className="empty-state">No hay inscripciones para los filtros seleccionados.</div>
          ) : (
            <div className="registrations-kanban-board registrations-trello-board">
              {boardColumns.map((column) => {
                const statusId = String(column.statusId || '')
                const items = groupedByColumn.buckets.get(column.key) || []

                return (
                  <section
                    key={column.key}
                    className={`registrations-kanban-column registrations-trello-column${!column.canDrop ? ' is-readonly' : ''}${statusId && dropTargetStatusId === statusId ? ' is-drop-target' : ''}`}
                    onDragOver={column.canDrop ? (event) => handleColumnDragOver(event, statusId) : undefined}
                    onDragEnter={column.canDrop ? () => setDropTargetStatusId(statusId) : undefined}
                    onDragLeave={column.canDrop ? () => setDropTargetStatusId((current) => (current === statusId ? '' : current)) : undefined}
                    onDrop={column.canDrop ? (event) => handleColumnDrop(event, statusId) : undefined}
                  >
                    <div className="registrations-kanban-column-header">
                      <h5>{column.label}</h5>
                      <span className="tag muted">{items.length}</span>
                    </div>

                    <div className="registrations-kanban-column-body">
                      {items.length === 0
                        ? <div className="registrations-kanban-empty">{column.canDrop ? 'Suelta una inscripción aquí.' : 'Sin inscripciones en esta columna.'}</div>
                        : items.map((registration) => renderCard(registration, { compact: false, draggable: column.canDrop }))}
                    </div>
                  </section>
                )
              })}
            </div>
          )}
        </div>

        {selectedRegistration && (
        <aside className="panel-card registrations-detail-drawer">
            <header className="registration-drawer-header">
              <div>
                <h4>{selectedRegistration.team?.display_name || 'Inscripción'}</h4>
                <p className="muted">
                  {selectedRegistration.tournament_category?.tournament?.name || 'Torneo'}
                  {' • '}
                  {selectedRegistration.tournament_category?.category?.display_name || selectedRegistration.tournament_category?.category?.name || 'Categoría'}
                </p>
              </div>
              <button className="ghost-button" type="button" onClick={closeRegistrationDetail}>
                  Cerrar
                </button>
              </header>

              <div className="registration-drawer-content">
                {isOpenTournamentRegistration(selectedRegistration) ? null : (
                <section className="registration-detail-panel">
                  <h5>Detalle de inscripción</h5>
                  <div className="registration-detail-grid">
                    <div>
                      <span>Estado actual</span>
                      <strong>{selectedRegistration.status?.label || 'Sin estado'}</strong>
                    </div>
                    <div>
                      <span>Posición cola</span>
                      <strong>{selectedRegistration.queue_position ?? '—'}</strong>
                    </div>
                    <div>
                      <span>Ranking equipo</span>
                      <strong>{selectedRegistration.team_ranking_score ?? 'Pendiente'}</strong>
                    </div>
                    <div>
                      <span>Fecha registro</span>
                      <strong>{formatDateShort(selectedRegistration.created_at)}</strong>
                    </div>
                    <div>
                      <span>Tipo</span>
                      <strong>{selectedRegistration.is_wildcard ? 'Wildcard' : 'Regular'}</strong>
                    </div>
                    <div>
                      <span>Pago wildcard</span>
                      <strong>{selectedRegistration.wildcard_fee_waived ? 'Exonerado' : 'Normal'}</strong>
                    </div>
                  </div>
                </section>
                )}

                {isOpenTournamentRegistration(selectedRegistration) ? (
                  <section className="registration-detail-panel">
                    <h5>Detalle de inscripción</h5>
                    <div className="registration-detail-grid">
                      <div>
                        <span>Estado actual</span>
                        <strong>{selectedRegistration.status?.label || 'Sin estado'}</strong>
                      </div>
                      <div>
                        <span>Posición cola</span>
                        <strong>{selectedRegistration.queue_position ?? '—'}</strong>
                      </div>
                      <div>
                        <span>Ranking equipo</span>
                        <strong>No aplica</strong>
                      </div>
                      <div>
                        <span>Fecha registro</span>
                        <strong>{formatDateShort(selectedRegistration.created_at)}</strong>
                      </div>
                      <div>
                        <span>Tipo</span>
                        <strong>{selectedRegistration.is_wildcard ? 'Wildcard' : 'Regular'}</strong>
                      </div>
                      <div>
                        <span>Pago wildcard</span>
                        <strong>{selectedRegistration.wildcard_fee_waived ? 'Exonerado' : 'Normal'}</strong>
                      </div>
                    </div>
                  </section>
                ) : null}

                <section className="registration-edit-panel">
                  <h5>Editar inscripción</h5>
                  <label>
                    Estado
                    <select value={detailForm.status_id} onChange={handleDetailFieldChange('status_id')}>
                      <option value="">Sin estado</option>
                      {statusOptions.map((status) => (
                        <option key={status.id} value={status.id}>
                          {status.label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label>
                    Nota admin
                    <textarea
                      value={detailForm.notes_admin}
                      onChange={handleDetailFieldChange('notes_admin')}
                      placeholder="Escribe una nota para control interno..."
                    />
                  </label>
                  <div className="form-actions">
                    <button
                      className="primary-button"
                      type="button"
                      onClick={handleSaveDetail}
                      disabled={isSavingDetail}
                    >
                      {isSavingDetail ? 'Guardando...' : 'Guardar inscripción'}
                    </button>
                  </div>
                </section>

                {isOpenTournamentRegistration(selectedRegistration) ? null : (
                  <section className="registration-edit-panel">
                    <h5>Ranking de inscripción</h5>
                    <div className="registration-ranking-list">
                      {rankingDraft.map((row) => (
                        <div key={row.slot} className="registration-ranking-row">
                          <div>
                            <span>Slot {row.slot}</span>
                            <strong>{row.player_name}</strong>
                          </div>
                          <label>
                            Ranking {row.ranking_source}
                            <input
                              type="number"
                              min="1"
                              value={row.ranking_value}
                              onChange={handleRankingFieldChange(row.slot, 'ranking_value')}
                              placeholder="Sin ranking"
                            />
                          </label>
                          <label>
                            Fuente
                            <select
                              value={row.ranking_source}
                              onChange={handleRankingFieldChange(row.slot, 'ranking_source')}
                            >
                              {selectedRankingSourceOptions.map((source) => (
                                <option key={source} value={source}>
                                  {source}
                                </option>
                              ))}
                            </select>
                          </label>
                          <label className="checkbox-row">
                            <input
                              type="checkbox"
                              checked={row.is_verified}
                              onChange={handleRankingFieldChange(row.slot, 'is_verified')}
                            />
                            Verificado
                          </label>
                        </div>
                      ))}
                    </div>
                    <div className="form-actions">
                      <button
                        className="secondary-button"
                        type="button"
                        onClick={handleSaveRankings}
                        disabled={isSavingRankings}
                      >
                        {isSavingRankings ? 'Guardando...' : 'Guardar ranking'}
                      </button>
                    </div>
                  </section>
                )}
              </div>
        </aside>
        )}
      </div>
    </section>
  )
}
