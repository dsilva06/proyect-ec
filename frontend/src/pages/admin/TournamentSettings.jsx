import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import DatePicker from '../../components/shared/DatePicker'
import DateTimePicker from '../../components/shared/DateTimePicker'
import { adminCategoriesApi } from '../../features/categories/api'
import { statusesApi } from '../../features/statuses/api'
import {
  adminTournamentCategoriesApi,
  adminTournamentsApi,
} from '../../features/tournaments/api'
import { cleanPayload } from '../../utils/cleanPayload'

const initialTournamentForm = {
  name: '',
  mode: 'amateur',
  classification_method: 'self_selected',
  description: '',
  start_date: '',
  end_date: '',
  entry_fee_amount: '',
  entry_fee_currency: 'EUR',
  registration_open_at: '',
  registration_close_at: '',
  match_duration_minutes: '',
  courts_count: '',
  prize_money: '',
  prize_currency: 'EUR',
  city: '',
  venue_name: '',
  venue_address: '',
}

const GROUP_LABELS = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  mixto: 'Mixto',
}

const TOURNAMENT_BOARD_COLUMNS = [
  { key: 'planning', label: 'Planificación' },
  { key: 'registration', label: 'Inscripciones' },
  { key: 'live', label: 'En juego' },
  { key: 'closed', label: 'Cerrados' },
]

const TOURNAMENT_COLUMN_STATUS_CODES = {
  planning: ['draft'],
  registration: ['registration_open', 'registration_closed'],
  live: ['in_progress'],
  closed: ['published', 'completed', 'cancelled'],
}

const resolveTournamentBoardColumn = (statusCode) => {
  const code = String(statusCode || '').toLowerCase()

  if (!code || code === 'draft' || code === 'created') return 'planning'
  if (code === 'registration_open' || code === 'registration_closed') return 'registration'
  if (code === 'in_progress') return 'live'
  if (code === 'published' || code === 'completed' || code === 'cancelled') return 'closed'

  return 'planning'
}

const getTournamentModeLabel = (mode) => {
  const normalized = String(mode || '').toLowerCase()
  if (normalized === 'pro') return 'PRO'
  if (normalized === 'open') return 'OPEN'
  return 'Amateur'
}

const getClassificationMethodLabel = (classificationMethod) => {
  const normalized = String(classificationMethod || '').toLowerCase()
  if (normalized === 'referee_assigned') return 'Asignación por árbitro'
  return 'Selección de categoría'
}

const tournamentUsesRanking = (mode) => String(mode || '').toLowerCase() !== 'open'

const buildRankingRangePayload = (values, usesRanking) => {
  if (!usesRanking) {
    return {
      min_fip_rank: null,
      max_fip_rank: null,
      min_fep_rank: null,
      max_fep_rank: null,
    }
  }

  return {
    min_fip_rank: values.min_fip_rank || null,
    max_fip_rank: values.max_fip_rank || null,
    min_fep_rank: values.min_fep_rank || null,
    max_fep_rank: values.max_fep_rank || null,
  }
}


export default function TournamentSettings() {
  const [tournaments, setTournaments] = useState([])
  const [categories, setCategories] = useState([])
  const [statuses, setStatuses] = useState([])
  const [form, setForm] = useState(initialTournamentForm)
  const [isCreateOpen, setIsCreateOpen] = useState(false)
  const [selectedTournamentId, setSelectedTournamentId] = useState(null)
  const [search, setSearch] = useState('')
  const [categorySearch, setCategorySearch] = useState('')
  const [categorySelection, setCategorySelection] = useState([])
  const [categoryDefaults, setCategoryDefaults] = useState({
    max_teams: 32,
    wildcard_slots: 0,
    acceptance_type: 'waitlist',
    min_fip_rank: '',
    max_fip_rank: '',
    min_fep_rank: '',
    max_fep_rank: '',
  })
  const [categoryForms, setCategoryForms] = useState({})
  const [categoryEdits, setCategoryEdits] = useState({})
  const [tournamentEdits, setTournamentEdits] = useState({})
  const [draggedTournamentId, setDraggedTournamentId] = useState(null)
  const [dropTargetColumnKey, setDropTargetColumnKey] = useState('')
  const [updatingTournamentId, setUpdatingTournamentId] = useState(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'tournament'),
    [statuses],
  )

  const statusByCode = useMemo(() => {
    const map = new Map()
    statusOptions.forEach((status) => {
      map.set(String(status.code || '').toLowerCase(), status)
    })
    return map
  }, [statusOptions])

  const boardColumns = useMemo(() => TOURNAMENT_BOARD_COLUMNS.map((column) => {
    const targetStatus = TOURNAMENT_COLUMN_STATUS_CODES[column.key]
      .map((code) => statusByCode.get(code))
      .find(Boolean)

    return {
      ...column,
      targetStatusId: targetStatus ? String(targetStatus.id) : '',
      canDrop: Boolean(targetStatus),
    }
  }), [statusByCode])

  const selectedTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(selectedTournamentId)) || null,
    [tournaments, selectedTournamentId],
  )

  const categoriesByGroup = useMemo(() => {
    return categories.reduce((acc, category) => {
      const group = category.group_code || 'otros'
      if (!acc[group]) {
        acc[group] = []
      }
      acc[group].push(category)
      return acc
    }, {})
  }, [categories])

  const categoriesById = useMemo(
    () => categories.reduce((acc, category) => ({ ...acc, [category.id]: category }), {}),
    [categories],
  )

  const normalizedCategorySearch = categorySearch.trim().toLowerCase()

  const visibleCategoriesByGroup = useMemo(() => {
    if (!normalizedCategorySearch) return categoriesByGroup

    return Object.entries(categoriesByGroup).reduce((acc, [group, items]) => {
      const filteredItems = items.filter((category) =>
        `${category.display_name || ''} ${category.name || ''}`.toLowerCase().includes(normalizedCategorySearch))
      if (filteredItems.length) {
        acc[group] = filteredItems
      }
      return acc
    }, {})
  }, [categoriesByGroup, normalizedCategorySearch])

  const visibleCategoryIds = useMemo(
    () =>
      Object.values(visibleCategoriesByGroup)
        .flat()
        .map((category) => category.id),
    [visibleCategoriesByGroup],
  )

  const filteredTournaments = useMemo(() => {
    const term = search.trim().toLowerCase()
    if (!term) return tournaments

    return tournaments.filter((tournament) => {
      const base = `${tournament.name || ''} ${tournament.city || ''} ${tournament.venue_name || ''} ${tournament.status?.label || ''}`.toLowerCase()
      if (base.includes(term)) return true

      return (tournament.categories || []).some((category) =>
        `${category.category?.display_name || ''} ${category.category?.name || ''}`.toLowerCase().includes(term))
    })
  }, [tournaments, search])

  const tournamentsByColumn = useMemo(() => {
    const buckets = new Map()
    boardColumns.forEach((column) => buckets.set(column.key, []))

    filteredTournaments.forEach((tournament) => {
      const column = resolveTournamentBoardColumn(tournament.status?.code)
      const list = buckets.get(column)
      if (list) list.push(tournament)
    })

    for (const list of buckets.values()) {
      list.sort((a, b) => String(a.start_date || '').localeCompare(String(b.start_date || '')))
    }

    return buckets
  }, [boardColumns, filteredTournaments])

  const load = async () => {
    try {
      const [tournamentsData, categoriesData, statusesData] = await Promise.all([
        adminTournamentsApi.list(),
        adminCategoriesApi.list(),
        statusesApi.list(),
      ])
      setTournaments(tournamentsData)
      setCategories(categoriesData)
      setStatuses(statusesData)
      setIsCreateOpen((current) => (tournamentsData.length === 0 ? true : current))
      setSelectedTournamentId((current) => {
        if (current && tournamentsData.some((tournament) => String(tournament.id) === String(current))) {
          return current
        }
        return tournamentsData[0]?.id ?? null
      })
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los torneos.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleTournamentChange = (field) => (event) => {
    setForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const handleValueChange = (field) => (value) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const toggleCategorySelection = (categoryId) => {
    setCategorySelection((prev) => {
      if (prev.includes(categoryId)) {
        return prev.filter((id) => id !== categoryId)
      }
      return [...prev, categoryId]
    })
  }

  const addCategorySelection = (ids) => {
    if (!ids.length) return
    setCategorySelection((prev) => [...new Set([...prev, ...ids])])
  }

  const removeCategorySelection = (ids) => {
    if (!ids.length) return
    setCategorySelection((prev) => prev.filter((id) => !ids.includes(id)))
  }

  const toggleGroupCategorySelection = (groupIds) => {
    if (!groupIds.length) return
    const allSelected = groupIds.every((id) => categorySelection.includes(id))
    if (allSelected) {
      removeCategorySelection(groupIds)
      return
    }
    addCategorySelection(groupIds)
  }

  const selectVisibleCategories = () => addCategorySelection(visibleCategoryIds)
  const clearVisibleCategories = () => removeCategorySelection(visibleCategoryIds)

  const handleCategoryDefaultsChange = (field) => (event) => {
    setCategoryDefaults((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const handleCreateTournament = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')

    try {
      const payload = cleanPayload({
        ...form,
        entry_fee_amount: Number(form.entry_fee_amount || 0),
      })
      const tournament = await adminTournamentsApi.create(payload)
      const rankingRangePayload = buildRankingRangePayload(categoryDefaults, tournamentUsesRanking(form.mode))

      if (categorySelection.length) {
        await Promise.all(
          categorySelection.map((categoryId) =>
            adminTournamentsApi.addCategory(tournament.id, {
              category_id: categoryId,
              max_teams: Number(categoryDefaults.max_teams || 0),
              wildcard_slots: Number(categoryDefaults.wildcard_slots || 0),
              acceptance_type: categoryDefaults.acceptance_type || 'waitlist',
              ...rankingRangePayload,
            }),
          ),
        )
      }

      setForm(initialTournamentForm)
      setCategorySelection([])
      setCategorySearch('')
      setMessage('Torneo creado correctamente.')
      setSelectedTournamentId(tournament.id)
      setIsCreateOpen(false)
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos crear el torneo.')
    }
  }

  const handleStatusChange = async (tournamentId, statusId) => {
    try {
      setError('')
      setMessage('')
      setUpdatingTournamentId(String(tournamentId))
      await adminTournamentsApi.updateStatus(tournamentId, statusId)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar el estado.')
    } finally {
      setUpdatingTournamentId(null)
    }
  }

  const clearDragState = () => {
    setDraggedTournamentId(null)
    setDropTargetColumnKey('')
  }

  const handleTournamentDragStart = (event, tournamentId) => {
    const id = String(tournamentId)
    event.dataTransfer.setData('text/plain', id)
    event.dataTransfer.effectAllowed = 'move'
    setDraggedTournamentId(id)
  }

  const handleTournamentDragEnd = () => {
    clearDragState()
  }

  const handleColumnDragOver = (event, columnKey, canDrop) => {
    if (!canDrop) return
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
    setDropTargetColumnKey(String(columnKey))
  }

  const handleColumnDrop = async (event, column) => {
    event.preventDefault()

    if (!column.canDrop || !column.targetStatusId) {
      clearDragState()
      return
    }

    const droppedTournamentId = String(
      event.dataTransfer.getData('text/plain') || draggedTournamentId || '',
    )

    if (!droppedTournamentId) {
      clearDragState()
      return
    }

    const tournament = tournaments.find((item) => String(item.id) === droppedTournamentId)
    if (!tournament) {
      clearDragState()
      return
    }

    const currentColumnKey = resolveTournamentBoardColumn(tournament.status?.code)
    if (currentColumnKey === column.key) {
      clearDragState()
      return
    }

    await handleStatusChange(droppedTournamentId, column.targetStatusId)
    clearDragState()
  }

  const handleTournamentEdit = (tournamentId, field, value) => {
    setTournamentEdits((prev) => ({
      ...prev,
      [tournamentId]: {
        ...(prev[tournamentId] || {}),
        [field]: value,
      },
    }))
  }

  const handleUpdateTournament = async (tournamentId) => {
    const payload = tournamentEdits[tournamentId]
    if (!payload) return

    try {
      await adminTournamentsApi.update(tournamentId, cleanPayload(payload))
      setTournamentEdits((prev) => ({ ...prev, [tournamentId]: null }))
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar el torneo.')
    }
  }

  const handleDeleteTournament = async (tournamentId) => {
    const confirmDelete = window.confirm('¿Seguro que deseas eliminar este torneo?')
    if (!confirmDelete) return

    try {
      await adminTournamentsApi.remove(tournamentId)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos eliminar el torneo.')
    }
  }

  const handleCategoryFormChange = (tournamentId, field, value) => {
    setCategoryForms((prev) => ({
      ...prev,
      [tournamentId]: {
        ...(prev[tournamentId] || {
          category_id: '',
          max_teams: 32,
          wildcard_slots: 0,
          acceptance_type: 'waitlist',
          min_fip_rank: '',
          max_fip_rank: '',
          min_fep_rank: '',
          max_fep_rank: '',
        }),
        [field]: value,
      },
    }))
  }

  const handleAddCategory = async (tournamentId) => {
    const payload = categoryForms[tournamentId]
    if (!payload?.category_id) {
      setError('Selecciona una categoría para el torneo.')
      return
    }

    try {
      const tournament = tournaments.find((item) => String(item.id) === String(tournamentId))
      const rankingRangePayload = buildRankingRangePayload(payload, tournamentUsesRanking(tournament?.mode))
      await adminTournamentsApi.addCategory(tournamentId, cleanPayload({
        ...payload,
        max_teams: Number(payload.max_teams || 0),
        wildcard_slots: Number(payload.wildcard_slots || 0),
        acceptance_type: payload.acceptance_type || 'waitlist',
        ...rankingRangePayload,
      }))
      setCategoryForms((prev) => ({ ...prev, [tournamentId]: null }))
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos agregar la categoría.')
    }
  }

  const handleCategoryEdit = (categoryId, field, value) => {
    setCategoryEdits((prev) => ({
      ...prev,
      [categoryId]: {
        ...(prev[categoryId] || {}),
        [field]: value,
      },
    }))
  }

  const handleUpdateCategory = async (categoryId) => {
    const payload = categoryEdits[categoryId]
    if (!payload) return

    try {
      await adminTournamentCategoriesApi.update(categoryId, cleanPayload(payload))
      setCategoryEdits((prev) => ({ ...prev, [categoryId]: null }))
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar la categoría.')
    }
  }

  const handleRemoveCategory = async (categoryId) => {
    try {
      await adminTournamentCategoriesApi.remove(categoryId)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos eliminar la categoría.')
    }
  }

  const formatDateShort = (value) => {
    if (!value) return ''
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return String(value)
    return parsed.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    })
  }

  const formatDateTimeShort = (value) => {
    if (!value) return ''
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return String(value)
    return parsed.toLocaleString('en-US', {
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
    return startLabel || endLabel || 'TBD'
  }

  const formatMoney = (value, currency = 'USD') => {
    if (value === null || value === undefined || value === '') return 'TBD'
    const amount = Number(value)
    if (!Number.isFinite(amount)) return 'TBD'
    const resolvedCurrency = currency === 'EUR' ? 'EUR' : 'USD'

    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: resolvedCurrency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount)
  }

  const openTournamentModal = (tournamentId) => {
    setSelectedTournamentId(tournamentId)
  }

  const closeTournamentModal = () => {
    setSelectedTournamentId(null)
  }

  const renderTournamentPreviewCard = (tournament) => {
    const categoryCount = tournament.categories?.length || 0
    const tournamentId = String(tournament.id)

    return (
      <article
        key={tournament.id}
        className={`registrations-kanban-card tournaments-trello-card${draggedTournamentId === tournamentId ? ' is-dragging' : ''}${updatingTournamentId === tournamentId ? ' is-updating' : ''}`}
        role="button"
        tabIndex={0}
        draggable
        onClick={() => openTournamentModal(tournament.id)}
        onDragStart={(event) => handleTournamentDragStart(event, tournament.id)}
        onDragEnd={handleTournamentDragEnd}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            openTournamentModal(tournament.id)
          }
        }}
      >
        <div className="registrations-trello-card-head">
          <strong title={tournament.name}>{tournament.name}</strong>
          <span className="tag muted">{tournament.status?.label || 'Sin estado'}</span>
        </div>
        <span className="muted registrations-trello-players">
          {tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}
        </span>
        <div className="registrations-kanban-meta">
          <div>
            <span className="muted">Fechas</span>
            <strong>{formatRange(tournament.start_date, tournament.end_date, formatDateShort)}</strong>
          </div>
          <div>
            <span className="muted">Inscripciones</span>
            <strong>{formatRange(tournament.registration_open_at, tournament.registration_close_at, formatDateTimeShort)}</strong>
          </div>
          <div>
            <span className="muted">Prize money</span>
            <strong>{formatMoney(tournament.prize_money, tournament.prize_currency)}</strong>
          </div>
          <div>
            <span className="muted">Costo inscripción</span>
            <strong>{formatMoney(tournament.entry_fee_amount, tournament.entry_fee_currency)}</strong>
          </div>
        </div>
        <div className="registrations-trello-card-foot">
          <span className="registrations-trello-category">{getTournamentModeLabel(tournament.mode)}</span>
          <span className="tag muted">{getClassificationMethodLabel(tournament.classification_method)}</span>
          <span className="tag muted">{categoryCount} categorías</span>
        </div>
      </article>
    )
  }

  const renderTournamentCard = (tournament) => {
    const categoryForm = categoryForms[tournament.id] || {}
    const currentMode = tournamentEdits[tournament.id]?.mode ?? tournament.mode
    const usesRanking = tournamentUsesRanking(currentMode)
    return (
      <article key={tournament.id} className="tournament-card settings-tournament-card">
        <div className="tournament-card-header">
          <div>
            <h3>{tournament.name}</h3>
            <p>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</p>
          </div>
          <select
            className="status-select"
            value={tournament.status?.id || ''}
            onChange={(event) => handleStatusChange(tournament.id, event.target.value)}
          >
            {statusOptions.map((status) => (
              <option key={status.id} value={status.id}>
                {status.label}
              </option>
            ))}
          </select>
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
            <span>Prize money</span>
            <strong>{formatMoney(tournament.prize_money, tournament.prize_currency)}</strong>
          </div>
          <div>
            <span>Costo inscripción</span>
            <strong>{formatMoney(tournament.entry_fee_amount, tournament.entry_fee_currency)}</strong>
          </div>
        </div>

        <div className="panel-card tournament-editor-card">
          <div className="panel-header">
            <h4>Editar torneo</h4>
          </div>
          <div className="form-grid tournament-edit-grid">
            <label className="field-span-2">
              Descripción
              <textarea
                value={tournamentEdits[tournament.id]?.description ?? tournament.description ?? ''}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'description', event.target.value)
                }
              />
            </label>
            <label>
              Nombre
              <input
                type="text"
                value={tournamentEdits[tournament.id]?.name ?? tournament.name}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'name', event.target.value)
                }
              />
            </label>
            <label>
              Ciudad
              <input
                type="text"
                value={tournamentEdits[tournament.id]?.city ?? tournament.city ?? ''}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'city', event.target.value)
                }
              />
            </label>
            <label>
              Sede
              <input
                type="text"
                value={tournamentEdits[tournament.id]?.venue_name ?? tournament.venue_name ?? ''}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'venue_name', event.target.value)
                }
              />
            </label>
            <label className="field-span-2">
              Dirección
              <input
                type="text"
                value={tournamentEdits[tournament.id]?.venue_address ?? tournament.venue_address ?? ''}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'venue_address', event.target.value)
                }
              />
            </label>
            <label>
              Modalidad
              <select
                value={tournamentEdits[tournament.id]?.mode ?? tournament.mode ?? 'amateur'}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'mode', event.target.value)
                }
              >
                <option value="amateur">Amateur</option>
                <option value="pro">Pro</option>
                <option value="open">OPEN</option>
              </select>
            </label>
            <label>
              Clasificación
              <select
                value={tournamentEdits[tournament.id]?.classification_method ?? tournament.classification_method ?? 'self_selected'}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'classification_method', event.target.value)
                }
              >
                <option value="self_selected">Selección de categoría</option>
                <option value="referee_assigned">Asignación por árbitro</option>
              </select>
            </label>
            <label>
              Fecha inicio
              <DatePicker
                value={tournamentEdits[tournament.id]?.start_date ?? tournament.start_date ?? ''}
                onChange={(value) => handleTournamentEdit(tournament.id, 'start_date', value)}
              />
            </label>
            <label>
              Fecha fin
              <DatePicker
                value={tournamentEdits[tournament.id]?.end_date ?? tournament.end_date ?? ''}
                onChange={(value) => handleTournamentEdit(tournament.id, 'end_date', value)}
              />
            </label>
            <label>
              Costo inscripción
              <input
                type="number"
                min="0"
                step="1"
                className="no-spinner"
                value={tournamentEdits[tournament.id]?.entry_fee_amount ?? tournament.entry_fee_amount ?? ''}
                onChange={(event) =>
                  handleTournamentEdit(tournament.id, 'entry_fee_amount', event.target.value)
                }
              />
            </label>
            <label>
              Moneda inscripción
              <select
                value={tournamentEdits[tournament.id]?.entry_fee_currency ?? tournament.entry_fee_currency ?? 'EUR'}
                onChange={(event) => handleTournamentEdit(tournament.id, 'entry_fee_currency', event.target.value)}
              >
                <option value="EUR">EUR</option>
              </select>
            </label>
            <label>
              Apertura inscripciones
              <DateTimePicker
                value={tournamentEdits[tournament.id]?.registration_open_at ?? tournament.registration_open_at ?? ''}
                onChange={(value) => handleTournamentEdit(tournament.id, 'registration_open_at', value)}
              />
            </label>
            <label>
              Cierre inscripciones
              <DateTimePicker
                value={tournamentEdits[tournament.id]?.registration_close_at ?? tournament.registration_close_at ?? ''}
                onChange={(value) => handleTournamentEdit(tournament.id, 'registration_close_at', value)}
              />
            </label>
            <label>
              Duración estimada (min)
              <input
                type="number"
                min="20"
                value={tournamentEdits[tournament.id]?.match_duration_minutes ?? tournament.match_duration_minutes ?? ''}
                onChange={(event) => handleTournamentEdit(tournament.id, 'match_duration_minutes', event.target.value)}
              />
            </label>
            <label>
              Canchas disponibles
              <input
                type="number"
                min="1"
                value={tournamentEdits[tournament.id]?.courts_count ?? tournament.courts_count ?? ''}
                onChange={(event) => handleTournamentEdit(tournament.id, 'courts_count', event.target.value)}
              />
            </label>
            <label>
              Prize money
              <input
                type="number"
                min="0"
                step="1"
                className="no-spinner"
                value={tournamentEdits[tournament.id]?.prize_money ?? tournament.prize_money ?? ''}
                onChange={(event) => handleTournamentEdit(tournament.id, 'prize_money', event.target.value)}
              />
            </label>
            <label>
              Moneda premio
              <select
                value={tournamentEdits[tournament.id]?.prize_currency ?? tournament.prize_currency ?? 'EUR'}
                onChange={(event) => handleTournamentEdit(tournament.id, 'prize_currency', event.target.value)}
              >
                <option value="EUR">EUR</option>
              </select>
            </label>
            <div className="form-actions field-span-2">
              <button
                className="secondary-button"
                type="button"
                onClick={() => handleUpdateTournament(tournament.id)}
              >
                Guardar cambios
              </button>
              <button
                className="ghost-button"
                type="button"
                onClick={() => handleDeleteTournament(tournament.id)}
              >
                Eliminar torneo
              </button>
            </div>
          </div>
        </div>

        <div className="panel-card tournament-categories-card">
          <div className="panel-header">
            <h4>Categorías del torneo</h4>
            <span className="tag muted">{tournament.categories?.length || 0}</span>
          </div>
          {tournament.categories?.length ? (
            <div className="registration-list tournament-category-list">
              {tournament.categories.map((category) => {
                const edits = categoryEdits[category.id] || {}
                return (
                  <div key={category.id} className="registration-item tournament-category-item">
                    <div className="tournament-category-name">
                      <strong>{category.category?.display_name || category.category?.name}</strong>
                    </div>
                    <div>
                      <span>Max equipos</span>
                      <input
                        type="number"
                        value={edits.max_teams ?? category.max_teams}
                        onChange={(event) =>
                          handleCategoryEdit(category.id, 'max_teams', event.target.value)
                        }
                      />
                    </div>
                    <div>
                      <span>Wildcards</span>
                      <input
                        type="number"
                        min="0"
                        value={edits.wildcard_slots ?? category.wildcard_slots ?? 0}
                        onChange={(event) =>
                          handleCategoryEdit(category.id, 'wildcard_slots', event.target.value)
                        }
                      />
                    </div>
                    <div>
                      <span>Aceptación</span>
                      <select
                        value={edits.acceptance_type ?? category.acceptance_type ?? 'waitlist'}
                        onChange={(event) =>
                          handleCategoryEdit(category.id, 'acceptance_type', event.target.value)
                        }
                      >
                        <option value="waitlist">Waitlist</option>
                        <option value="immediate">Inmediata</option>
                      </select>
                    </div>
                    {usesRanking ? (
                      <>
                        <div>
                          <span>FIP min</span>
                          <input
                            type="number"
                            value={edits.min_fip_rank ?? category.min_fip_rank ?? ''}
                            onChange={(event) =>
                              handleCategoryEdit(category.id, 'min_fip_rank', event.target.value)
                            }
                          />
                        </div>
                        <div>
                          <span>FIP max</span>
                          <input
                            type="number"
                            value={edits.max_fip_rank ?? category.max_fip_rank ?? ''}
                            onChange={(event) =>
                              handleCategoryEdit(category.id, 'max_fip_rank', event.target.value)
                            }
                          />
                        </div>
                        <div>
                          <span>FEP min</span>
                          <input
                            type="number"
                            value={edits.min_fep_rank ?? category.min_fep_rank ?? ''}
                            onChange={(event) =>
                              handleCategoryEdit(category.id, 'min_fep_rank', event.target.value)
                            }
                          />
                        </div>
                        <div>
                          <span>FEP max</span>
                          <input
                            type="number"
                            value={edits.max_fep_rank ?? category.max_fep_rank ?? ''}
                            onChange={(event) =>
                              handleCategoryEdit(category.id, 'max_fep_rank', event.target.value)
                            }
                          />
                        </div>
                      </>
                    ) : null}
                    <div className="form-actions category-row-actions">
                      <Link
                        className="ghost-button"
                        to={`/admin/wildcards?tournament_id=${tournament.id}&tournament_category_id=${category.id}`}
                      >
                        Crear wildcard
                      </Link>
                      <button
                        className="secondary-button"
                        type="button"
                        onClick={() => handleUpdateCategory(category.id)}
                      >
                        Guardar
                      </button>
                      <button
                        className="ghost-button"
                        type="button"
                        onClick={() => handleRemoveCategory(category.id)}
                      >
                        Eliminar
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="muted">Sin categorías asignadas.</p>
          )}
        </div>

        <div className="panel-card tournament-add-category-card">
          <div className="panel-header">
            <h4>Agregar categoría</h4>
          </div>
          <div className="form-grid add-category-grid">
            <label className="field-span-2">
              Categoría
              <select
                value={categoryForm.category_id || ''}
                onChange={(event) =>
                  handleCategoryFormChange(tournament.id, 'category_id', event.target.value)
                }
              >
                <option value="">Selecciona</option>
                {Object.entries(categoriesByGroup).map(([group, items]) => (
                  <optgroup key={group} label={GROUP_LABELS[group] || 'Otros'}>
                    {items.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.display_name || category.name}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </label>
            <label>
              Max equipos
              <input
                type="number"
                value={categoryForm.max_teams || 32}
                onChange={(event) =>
                  handleCategoryFormChange(tournament.id, 'max_teams', event.target.value)
                }
              />
            </label>
            <label>
              Wildcards
              <input
                type="number"
                min="0"
                value={categoryForm.wildcard_slots || 0}
                onChange={(event) =>
                  handleCategoryFormChange(tournament.id, 'wildcard_slots', event.target.value)
                }
              />
            </label>
            <label>
              Tipo de aceptación
              <select
                value={categoryForm.acceptance_type || 'waitlist'}
                onChange={(event) =>
                  handleCategoryFormChange(tournament.id, 'acceptance_type', event.target.value)
                }
              >
                <option value="waitlist">Waitlist</option>
                <option value="immediate">Inmediata</option>
              </select>
            </label>
            {usesRanking ? (
              <>
                <label>
                  FIP min
                  <input
                    type="number"
                    value={categoryForm.min_fip_rank || ''}
                    onChange={(event) =>
                      handleCategoryFormChange(tournament.id, 'min_fip_rank', event.target.value)
                    }
                  />
                </label>
                <label>
                  FIP max
                  <input
                    type="number"
                    value={categoryForm.max_fip_rank || ''}
                    onChange={(event) =>
                      handleCategoryFormChange(tournament.id, 'max_fip_rank', event.target.value)
                    }
                  />
                </label>
                <label>
                  FEP min
                  <input
                    type="number"
                    value={categoryForm.min_fep_rank || ''}
                    onChange={(event) =>
                      handleCategoryFormChange(tournament.id, 'min_fep_rank', event.target.value)
                    }
                  />
                </label>
                <label>
                  FEP max
                  <input
                    type="number"
                    value={categoryForm.max_fep_rank || ''}
                    onChange={(event) =>
                      handleCategoryFormChange(tournament.id, 'max_fep_rank', event.target.value)
                    }
                  />
                </label>
              </>
            ) : null}
            <div className="form-actions field-span-2">
              <button
                className="primary-button"
                type="button"
                onClick={() => handleAddCategory(tournament.id)}
              >
                Agregar
              </button>
            </div>
          </div>
        </div>
      </article>
    )
  }

  return (
    <section className="admin-page tournament-settings-page tournaments-trello-page">
      <div className="admin-page-header">
        <div>
          <h3>Torneos</h3>
          <p>Vista tipo Trello con resumen por torneo y edición completa en modal.</p>
        </div>
        <div className="admin-page-actions">
          <button
            className="primary-button"
            type="button"
            onClick={() => setIsCreateOpen((current) => !current)}
          >
            {isCreateOpen ? 'Cerrar creación' : 'Crear torneo'}
          </button>
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
          <input
            type="text"
            value={search}
            placeholder="Buscar torneo, ciudad, sede o categoría..."
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>
      </div>

      {error && <div className="form-message error">{error}</div>}
      {message && <div className="form-message success">{message}</div>}

      {isCreateOpen && (
        <div className="admin-grid">
          <div className="panel-card tournament-create-card">
            <div className="panel-header">
              <h4>Crear torneo</h4>
              <span className="tag muted">Admin</span>
            </div>
            <form className="form-grid tournament-create-grid" onSubmit={handleCreateTournament}>
              <label>
                Nombre del torneo
                <input type="text" value={form.name} onChange={handleTournamentChange('name')} />
              </label>
              <label className="field-span-2">
                Descripción
                <textarea value={form.description} onChange={handleTournamentChange('description')} />
              </label>
              <label>
                Modalidad
                <select value={form.mode} onChange={handleTournamentChange('mode')}>
                  <option value="amateur">Amateur</option>
                  <option value="pro">Pro</option>
                  <option value="open">OPEN</option>
                </select>
              </label>
              <label>
                Clasificación
                <select value={form.classification_method} onChange={handleTournamentChange('classification_method')}>
                  <option value="self_selected">Selección de categoría</option>
                  <option value="referee_assigned">Asignación por árbitro</option>
                </select>
              </label>
              <label>
                Ciudad
                <input type="text" value={form.city} onChange={handleTournamentChange('city')} />
              </label>
              <label>
                Sede
                <input
                  type="text"
                  value={form.venue_name}
                  onChange={handleTournamentChange('venue_name')}
                />
              </label>
              <label className="field-span-2">
                Dirección
                <input
                  type="text"
                  value={form.venue_address}
                  onChange={handleTournamentChange('venue_address')}
                />
              </label>
              <label>
                Fecha inicio
                <DatePicker value={form.start_date} onChange={handleValueChange('start_date')} />
              </label>
              <label>
                Fecha fin
                <DatePicker value={form.end_date} onChange={handleValueChange('end_date')} />
              </label>
              <label>
                Costo inscripción
                <input
                  type="number"
                  min="0"
                  step="1"
                  className="no-spinner"
                  value={form.entry_fee_amount}
                  onChange={handleTournamentChange('entry_fee_amount')}
                />
              </label>
              <label>
                Moneda inscripción
                <select
                  value={form.entry_fee_currency}
                  onChange={handleTournamentChange('entry_fee_currency')}
                >
                  <option value="EUR">EUR</option>
                </select>
              </label>
              <label>
                Apertura inscripciones
                <DateTimePicker
                  value={form.registration_open_at}
                  onChange={handleValueChange('registration_open_at')}
                />
              </label>
              <label>
                Cierre inscripciones
                <DateTimePicker
                  value={form.registration_close_at}
                  onChange={handleValueChange('registration_close_at')}
                />
              </label>
              <label>
                Duración estimada (min)
                <input
                  type="number"
                  min="20"
                  value={form.match_duration_minutes}
                  onChange={handleTournamentChange('match_duration_minutes')}
                />
              </label>
              <label>
                Canchas disponibles
                <input
                  type="number"
                  min="1"
                  value={form.courts_count}
                  onChange={handleTournamentChange('courts_count')}
                />
              </label>
              <label>
                Prize money
                <input
                  type="number"
                  min="0"
                  step="1"
                  className="no-spinner"
                  value={form.prize_money}
                  onChange={handleTournamentChange('prize_money')}
                />
              </label>
              <label>
                Moneda premio
                <select
                  value={form.prize_currency}
                  onChange={handleTournamentChange('prize_currency')}
                >
                  <option value="EUR">EUR</option>
                </select>
              </label>
              <div className="panel-card tournament-default-categories field-span-2">
                <div className="panel-header">
                  <h4>Categorías del torneo</h4>
                  <span className="tag muted">{categorySelection.length} seleccionadas</span>
                </div>
                <div className="form-grid category-defaults-grid">
                  <label>
                    Max equipos (default)
                    <input
                      type="number"
                      value={categoryDefaults.max_teams}
                      onChange={handleCategoryDefaultsChange('max_teams')}
                    />
                  </label>
                  <label>
                    Wildcards (default)
                    <input
                      type="number"
                      min="0"
                      value={categoryDefaults.wildcard_slots}
                      onChange={handleCategoryDefaultsChange('wildcard_slots')}
                    />
                  </label>
                  <label>
                    Tipo de aceptación
                    <select
                      value={categoryDefaults.acceptance_type}
                      onChange={handleCategoryDefaultsChange('acceptance_type')}
                    >
                      <option value="waitlist">Waitlist</option>
                      <option value="immediate">Inmediata</option>
                    </select>
                  </label>
                  {tournamentUsesRanking(form.mode) ? (
                    <>
                      <label>
                        FIP min (default)
                        <input
                          type="number"
                          value={categoryDefaults.min_fip_rank}
                          onChange={handleCategoryDefaultsChange('min_fip_rank')}
                        />
                      </label>
                      <label>
                        FIP max (default)
                        <input
                          type="number"
                          value={categoryDefaults.max_fip_rank}
                          onChange={handleCategoryDefaultsChange('max_fip_rank')}
                        />
                      </label>
                      <label>
                        FEP min (default)
                        <input
                          type="number"
                          value={categoryDefaults.min_fep_rank}
                          onChange={handleCategoryDefaultsChange('min_fep_rank')}
                        />
                      </label>
                      <label>
                        FEP max (default)
                        <input
                          type="number"
                          value={categoryDefaults.max_fep_rank}
                          onChange={handleCategoryDefaultsChange('max_fep_rank')}
                        />
                      </label>
                    </>
                  ) : null}
                  <div className="category-selection-toolbar field-span-2">
                    <label className="category-selection-search">
                      Buscar categoría
                      <input
                        type="text"
                        value={categorySearch}
                        placeholder="Ej: Open, 1era, Mixto..."
                        onChange={(event) => setCategorySearch(event.target.value)}
                      />
                    </label>
                    <div className="category-selection-actions">
                      <button className="secondary-button" type="button" onClick={selectVisibleCategories}>
                        Seleccionar visibles
                      </button>
                      <button className="ghost-button" type="button" onClick={clearVisibleCategories}>
                        Limpiar visibles
                      </button>
                      <button className="ghost-button" type="button" onClick={() => setCategorySelection([])}>
                        Limpiar todo
                      </button>
                    </div>
                  </div>
                  <div className="registration-list category-selection-list field-span-2">
                    {Object.entries(visibleCategoriesByGroup).length === 0 ? (
                      <div className="empty-state">
                        No hay categorías que coincidan con esa búsqueda.
                      </div>
                    ) : Object.entries(visibleCategoriesByGroup).map(([group, items]) => {
                      const groupIds = items.map((category) => category.id)
                      const selectedInGroup = groupIds.filter((id) => categorySelection.includes(id)).length
                      const allSelected = groupIds.length > 0 && selectedInGroup === groupIds.length
                      return (
                        <div key={group} className="category-group-card">
                          <div className="category-group-head">
                            <div className="category-group-summary">
                              <strong>{GROUP_LABELS[group] || 'Otros'}</strong>
                              <span>{selectedInGroup}/{items.length} seleccionadas</span>
                            </div>
                            <button
                              type="button"
                              className="ghost-button category-group-toggle"
                              onClick={() => toggleGroupCategorySelection(groupIds)}
                            >
                              {allSelected ? 'Limpiar grupo' : 'Seleccionar grupo'}
                            </button>
                          </div>
                          <div className="category-checkbox-group">
                            {items.map((category) => {
                              const selected = categorySelection.includes(category.id)
                              return (
                                <label
                                  key={category.id}
                                  className={`category-chip ${selected ? 'is-selected' : ''}`}
                                >
                                  <input
                                    type="checkbox"
                                    checked={selected}
                                    onChange={() => toggleCategorySelection(category.id)}
                                  />
                                  <span className="category-chip-mark">{selected ? '✓' : '+'}</span>
                                  <span>{category.display_name || category.name}</span>
                                </label>
                              )
                            })}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                  {categorySelection.length > 0 ? (
                    <div className="category-selection-preview field-span-2">
                      {categorySelection.map((categoryId) => {
                        const category = categoriesById[categoryId]
                        if (!category) return null
                        return (
                          <button
                            key={categoryId}
                            type="button"
                            className="category-selection-pill"
                            onClick={() => toggleCategorySelection(categoryId)}
                            title="Quitar categoría"
                          >
                            {category.display_name || category.name} ✕
                          </button>
                        )
                      })}
                    </div>
                  ) : null}
                </div>
              </div>
              <div className="form-actions field-span-2">
                <button className="primary-button" type="submit">Crear torneo</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {filteredTournaments.length === 0 ? (
        <div className="empty-state">No hay torneos para mostrar con ese filtro.</div>
      ) : (
        <div className="registrations-kanban-board tournaments-trello-board">
          {boardColumns.map((column) => {
            const items = tournamentsByColumn.get(column.key) || []
            return (
              <section
                key={column.key}
                className={`registrations-kanban-column tournaments-trello-column${!column.canDrop ? ' is-readonly' : ''}${dropTargetColumnKey === column.key ? ' is-drop-target' : ''}`}
                onDragOver={(event) => handleColumnDragOver(event, column.key, column.canDrop)}
                onDrop={(event) => handleColumnDrop(event, column)}
              >
                <div className="registrations-kanban-column-header">
                  <h5>{column.label}</h5>
                  <span className="tag muted">{items.length}</span>
                </div>
                <div className="registrations-kanban-column-body">
                  {items.length === 0
                    ? <div className="registrations-kanban-empty">{column.canDrop ? 'Suelta un torneo aquí.' : 'Sin torneos en esta etapa.'}</div>
                    : items.map((tournament) => renderTournamentPreviewCard(tournament))}
                </div>
              </section>
            )
          })}
        </div>
      )}

      {selectedTournament ? (
        <div className="modal-backdrop" onClick={closeTournamentModal}>
          <div className="modal-card tournaments-settings-modal" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h4>{selectedTournament.name}</h4>
                <p className="muted">Edición completa de torneo y categorías</p>
              </div>
              <button className="ghost-button" type="button" onClick={closeTournamentModal}>
                Cerrar
              </button>
            </div>
            <div className="modal-body tournaments-settings-modal-body">
              {renderTournamentCard(selectedTournament)}
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
