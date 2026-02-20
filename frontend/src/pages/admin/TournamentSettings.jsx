import { useEffect, useMemo, useState } from 'react'
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
  description: '',
  start_date: '',
  end_date: '',
  registration_open_at: '',
  registration_close_at: '',
  day_start_time: '',
  day_end_time: '',
  match_duration_minutes: '',
  courts_count: '',
  city: '',
  venue_name: '',
  venue_address: '',
}

const GROUP_LABELS = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  mixto: 'Mixto',
}


export default function TournamentSettings() {
  const [tournaments, setTournaments] = useState([])
  const [categories, setCategories] = useState([])
  const [statuses, setStatuses] = useState([])
  const [form, setForm] = useState(initialTournamentForm)
  const [view, setView] = useState('create')
  const [activeTournamentId, setActiveTournamentId] = useState(null)
  const [categorySelection, setCategorySelection] = useState([])
  const [categoryDefaults, setCategoryDefaults] = useState({
    max_teams: 32,
    wildcard_slots: 0,
    entry_fee_amount: 20,
    currency: 'USD',
    acceptance_type: 'waitlist',
    min_fip_rank: '',
    max_fip_rank: '',
    min_fep_rank: '',
    max_fep_rank: '',
  })
  const [categoryForms, setCategoryForms] = useState({})
  const [categoryEdits, setCategoryEdits] = useState({})
  const [tournamentEdits, setTournamentEdits] = useState({})
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'tournament'),
    [statuses],
  )

  const activeTournaments = useMemo(() => {
    const activeCodes = new Set(['registration_open', 'registration_closed', 'in_progress', 'published'])
    return tournaments.filter((tournament) => activeCodes.has(tournament.status?.code))
  }, [tournaments])

  const activeTournament = useMemo(() => {
    if (!activeTournamentId) {
      return activeTournaments[0] || null
    }
    return activeTournaments.find((tournament) => String(tournament.id) === String(activeTournamentId)) || null
  }, [activeTournaments, activeTournamentId])

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
      if (tournamentsData.length === 0) {
        setView('create')
      } else {
        const activeCodes = new Set(['registration_open', 'registration_closed', 'in_progress', 'published'])
        const hasActive = tournamentsData.some((tournament) => activeCodes.has(tournament.status?.code))
        setView(hasActive ? 'active' : 'all')
      }
      setActiveTournamentId((current) => {
        if (current) return current
        const active = tournamentsData.find((tournament) => {
          const activeCodes = new Set(['registration_open', 'registration_closed', 'in_progress', 'published'])
          return activeCodes.has(tournament.status?.code)
        })
        return active?.id ?? null
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
      })
      const tournament = await adminTournamentsApi.create(payload)

      if (categorySelection.length) {
        await Promise.all(
          categorySelection.map((categoryId) =>
            adminTournamentsApi.addCategory(tournament.id, {
              category_id: categoryId,
              max_teams: Number(categoryDefaults.max_teams || 0),
              wildcard_slots: Number(categoryDefaults.wildcard_slots || 0),
              entry_fee_amount: Number(categoryDefaults.entry_fee_amount || 0),
              currency: categoryDefaults.currency || 'USD',
              acceptance_type: categoryDefaults.acceptance_type || 'waitlist',
              min_fip_rank: categoryDefaults.min_fip_rank || null,
              max_fip_rank: categoryDefaults.max_fip_rank || null,
              min_fep_rank: categoryDefaults.min_fep_rank || null,
              max_fep_rank: categoryDefaults.max_fep_rank || null,
            }),
          ),
        )
      }

      setForm(initialTournamentForm)
      setCategorySelection([])
      setMessage('Torneo creado correctamente.')
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos crear el torneo.')
    }
  }

  const handleStatusChange = async (tournamentId, statusId) => {
    try {
      await adminTournamentsApi.updateStatus(tournamentId, statusId)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar el estado.')
    }
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
          entry_fee_amount: 20,
          currency: 'USD',
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
      await adminTournamentsApi.addCategory(tournamentId, cleanPayload({
        ...payload,
        entry_fee_amount: Number(payload.entry_fee_amount || 0),
        max_teams: Number(payload.max_teams || 0),
        wildcard_slots: Number(payload.wildcard_slots || 0),
        acceptance_type: payload.acceptance_type || 'waitlist',
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

  const renderTournamentCard = (tournament) => {
    const categoryForm = categoryForms[tournament.id] || {}
    return (
      <article key={tournament.id} className="tournament-card">
        <div className="tournament-card-header">
          <div>
            <h3>{tournament.name}</h3>
            <p>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</p>
          </div>
          <select
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
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Editar torneo</h4>
          </div>
          <div className="form-grid">
            <label>
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
            <label>
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
              Hora inicio día
              <input
                type="time"
                value={tournamentEdits[tournament.id]?.day_start_time ?? tournament.day_start_time ?? ''}
                onChange={(event) => handleTournamentEdit(tournament.id, 'day_start_time', event.target.value)}
              />
            </label>
            <label>
              Hora fin día
              <input
                type="time"
                value={tournamentEdits[tournament.id]?.day_end_time ?? tournament.day_end_time ?? ''}
                onChange={(event) => handleTournamentEdit(tournament.id, 'day_end_time', event.target.value)}
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
            <div className="form-actions">
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

        <div>
          <h4>Categorías del torneo</h4>
          {tournament.categories?.length ? (
            <div className="registration-list">
              {tournament.categories.map((category) => {
                const edits = categoryEdits[category.id] || {}
                return (
                  <div key={category.id} className="registration-item">
                    <div>
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
                      <span>Fee (monto)</span>
                      <input
                        type="number"
                        step="1"
                        value={edits.entry_fee_amount ?? category.entry_fee_amount}
                        onChange={(event) =>
                          handleCategoryEdit(category.id, 'entry_fee_amount', event.target.value)
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
                    <div className="form-actions">
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

        <div className="panel-card">
          <div className="panel-header">
            <h4>Agregar categoría</h4>
          </div>
          <div className="form-grid">
            <label>
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
              Fee (monto)
              <input
                type="number"
                step="1"
                value={categoryForm.entry_fee_amount || 20}
                onChange={(event) =>
                  handleCategoryFormChange(tournament.id, 'entry_fee_amount', event.target.value)
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
            <div className="form-actions">
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
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Torneos</h3>
          <p>Crea y configura torneos, categorías y fechas clave.</p>
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="admin-tabs">
        <button
          className={`admin-tab${view === 'create' ? ' active' : ''}`}
          type="button"
          onClick={() => setView('create')}
        >
          Crear torneo
        </button>
        <button
          className={`admin-tab${view === 'active' ? ' active' : ''}`}
          type="button"
          onClick={() => setView('active')}
        >
          Torneo activo
        </button>
        <button
          className={`admin-tab${view === 'all' ? ' active' : ''}`}
          type="button"
          onClick={() => setView('all')}
        >
          Todos los torneos
        </button>
      </div>

      {view === 'create' && (
        <div className="admin-grid">
          <div className="panel-card">
            <div className="panel-header">
              <h4>Crear torneo</h4>
              <span className="tag muted">Admin</span>
            </div>
            <form className="form-grid" onSubmit={handleCreateTournament}>
              <label>
                Nombre del torneo
                <input type="text" value={form.name} onChange={handleTournamentChange('name')} />
              </label>
              <label>
                Descripción
                <textarea value={form.description} onChange={handleTournamentChange('description')} />
              </label>
              <label>
                Modalidad
                <select value={form.mode} onChange={handleTournamentChange('mode')}>
                  <option value="amateur">Amateur</option>
                  <option value="pro">Pro</option>
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
              <label>
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
                Hora inicio día
                <input
                  type="time"
                  value={form.day_start_time}
                  onChange={handleTournamentChange('day_start_time')}
                />
              </label>
              <label>
                Hora fin día
                <input
                  type="time"
                  value={form.day_end_time}
                  onChange={handleTournamentChange('day_end_time')}
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
              <div className="panel-card">
                <div className="panel-header">
                  <h4>Categorías del torneo</h4>
                  <span className="tag muted">{categorySelection.length} seleccionadas</span>
                </div>
                <div className="form-grid">
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
                    Fee (monto)
                    <input
                      type="number"
                      step="1"
                      value={categoryDefaults.entry_fee_amount}
                      onChange={handleCategoryDefaultsChange('entry_fee_amount')}
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
                  <div className="registration-list">
                    {Object.entries(categoriesByGroup).map(([group, items]) => (
                      <div key={group} className="registration-item">
                        <div>
                          <strong>{GROUP_LABELS[group] || 'Otros'}</strong>
                          <span>{items.length} categorías</span>
                        </div>
                        <div className="form-actions">
                          {items.map((category) => (
                            <label key={category.id} style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                              <input
                                type="checkbox"
                                checked={categorySelection.includes(category.id)}
                                onChange={() => toggleCategorySelection(category.id)}
                              />
                              <span>{category.display_name || category.name}</span>
                            </label>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              <div className="form-actions">
                <button className="primary-button" type="submit">Crear torneo</button>
              </div>
            </form>
            {error && <p className="form-message error">{error}</p>}
            {message && <p className="form-message success">{message}</p>}
          </div>
        </div>
      )}

      {view === 'active' && (
        <div className="active-layout">
          <div className="panel-card active-list">
            <div className="panel-header">
              <h4>Torneos activos</h4>
              <span className="tag muted">{activeTournaments.length}</span>
            </div>
            {activeTournaments.length === 0 ? (
              <div className="empty-state">No hay torneos activos todavía.</div>
            ) : (
              <div className="registration-list">
                {activeTournaments.map((tournament) => (
                  <button
                    key={tournament.id}
                    type="button"
                    className={`active-item${String(activeTournamentId) === String(tournament.id) ? ' active' : ''}`}
                    onClick={() => setActiveTournamentId(tournament.id)}
                  >
                    <div>
                      <strong>{tournament.name}</strong>
                      <span>{tournament.city || 'Ciudad'} • {tournament.venue_name || 'Sede'}</span>
                    </div>
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
                  </button>
                ))}
              </div>
            )}
          </div>
          <div className="active-detail">
            {activeTournament ? (
              renderTournamentCard(activeTournament)
            ) : (
              <div className="empty-state">Selecciona un torneo activo.</div>
            )}
          </div>
        </div>
      )}

      {view === 'all' && (
        <div className="admin-stack">
          {tournaments.length === 0 ? (
            <div className="empty-state">Aún no hay torneos creados.</div>
          ) : (
            <div className="tournament-list">
              {tournaments.map((tournament) => renderTournamentCard(tournament))}
            </div>
          )}
        </div>
      )}
    </section>
  )
}
