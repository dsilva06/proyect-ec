import { useEffect, useMemo, useState } from 'react'
import { adminBracketSlotsApi, adminBracketsApi } from '../../features/brackets/api'
import { adminMatchesApi } from '../../features/matches/api'
import { adminRegistrationsApi } from '../../features/registrations/api'
import { adminTournamentsApi } from '../../features/tournaments/api'
import { statusesApi } from '../../features/statuses/api'
import { cleanPayload } from '../../utils/cleanPayload'
import BracketView from '../../components/brackets/BracketView'
import DateTimePicker from '../../components/shared/DateTimePicker'

const initialForm = {
  tournament_id: '',
  tournament_category_id: '',
  status_id: '',
}

export default function Draws() {
  const [brackets, setBrackets] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [statuses, setStatuses] = useState([])
  const [form, setForm] = useState(initialForm)
  const [error, setError] = useState('')
  const [activeBracketId, setActiveBracketId] = useState(null)
  const [slotError, setSlotError] = useState('')
  const [scheduleMatch, setScheduleMatch] = useState(null)
  const [scheduleForm, setScheduleForm] = useState({
    scheduled_at: '',
    court: '',
    estimated_duration_minutes: '',
    status_id: '',
  })
  const [matchError, setMatchError] = useState('')

  const selectedTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(form.tournament_id)),
    [tournaments, form.tournament_id],
  )

  const categoryOptions = useMemo(() => {
    if (!selectedTournament) return []
    return (selectedTournament.categories || []).map((category) => ({
      id: category.id,
      label: `${category.category?.display_name || category.category?.name || 'Categoría'}`,
    }))
  }, [selectedTournament])

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'bracket'),
    [statuses],
  )

  const matchStatusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'match'),
    [statuses],
  )

  const activeBracket = useMemo(
    () => brackets.find((bracket) => bracket.id === activeBracketId),
    [brackets, activeBracketId],
  )

  const sortedSlots = useMemo(() => {
    if (!activeBracket?.slots) return []
    return [...activeBracket.slots].sort((a, b) => a.slot_number - b.slot_number)
  }, [activeBracket])

  const registrationOptions = useMemo(
    () =>
      registrations.map((registration) => ({
        id: registration.id,
        label: `${registration.team?.display_name || 'Equipo'} • ${registration.status?.label || '—'}${registration.is_wildcard ? ' • WC' : ''}`,
      })),
    [registrations],
  )

  const load = async () => {
    try {
      const [bracketsData, tournamentsData, statusesData] = await Promise.all([
        adminBracketsApi.list(),
        adminTournamentsApi.list(),
        statusesApi.list(),
      ])
      setBrackets(bracketsData)
      setTournaments(tournamentsData)
      setStatuses(statusesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los cuadros.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  useEffect(() => {
    setSlotError('')
    if (!activeBracketId) {
      setRegistrations([])
      return
    }

    const bracket = brackets.find((item) => item.id === activeBracketId)
    const tournamentId = bracket?.tournament_category?.tournament?.id
    const categoryId = bracket?.tournament_category?.category?.id

    if (!tournamentId || !categoryId) {
      setRegistrations([])
      return
    }

    const loadRegistrations = async () => {
      try {
        const registrationsData = await adminRegistrationsApi.list({
          tournament_id: tournamentId,
          category_id: categoryId,
        })
        setRegistrations(registrationsData)
      } catch (err) {
        setSlotError(err?.message || 'No pudimos cargar las inscripciones.')
      }
    }

    loadRegistrations()
  }, [activeBracketId, brackets])

  const handleChange = (field) => (event) => {
    const value = event.target.value
    setForm((prev) => ({
      ...prev,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
    }))
  }

  const handleCreate = async (event) => {
    event.preventDefault()
    setError('')
    try {
      await adminBracketsApi.create({
        ...cleanPayload(form),
        status_id: form.status_id || statusOptions?.[0]?.id,
      })
      setForm(initialForm)
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos crear el cuadro.')
    }
  }

  const handleGenerate = async (bracket) => {
    setError('')
    try {
      if ((bracket.slots?.length || 0) > 0 || (bracket.matches?.length || 0) > 0) {
        const confirmRegenerate = window.confirm(
          'Esto borrará el cuadro actual y generará uno nuevo. ¿Deseas continuar?',
        )
        if (!confirmRegenerate) return
      }

      await adminBracketsApi.generate(bracket.id)
      await load()
      setActiveBracketId(bracket.id)
    } catch (err) {
      setError(err?.message || 'No pudimos generar el cuadro.')
    }
  }

  const handleSlotChange = async (slotId, registrationId) => {
    setSlotError('')
    try {
      await adminBracketSlotsApi.update(slotId, {
        registration_id: registrationId ? Number(registrationId) : null,
      })
      await load()
    } catch (err) {
      setSlotError(err?.data?.message || err?.message || 'No pudimos actualizar el slot.')
    }
  }

  const handleDelete = async (bracket) => {
    setError('')
    const confirmDelete = window.confirm(
      'Esto eliminará el cuadro, sus matches y slots. ¿Deseas continuar?',
    )
    if (!confirmDelete) return

    try {
      await adminBracketsApi.remove(bracket.id)
      if (activeBracketId === bracket.id) {
        setActiveBracketId(null)
      }
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos eliminar el cuadro.')
    }
  }

  const handleMatchClick = (match) => {
    setMatchError('')
    setScheduleMatch(match)
    setScheduleForm({
      scheduled_at: match.not_before_at || match.scheduled_at || '',
      court: match.court || '',
      estimated_duration_minutes: match.estimated_duration_minutes || '',
      status_id: match.status?.id || '',
    })
  }

  const handleScheduleChange = (field) => (valueOrEvent) => {
    const value = valueOrEvent?.target ? valueOrEvent.target.value : valueOrEvent
    setScheduleForm((prev) => ({ ...prev, [field]: value }))
  }

  const handleScheduleSave = async () => {
    if (!scheduleMatch) return
    setMatchError('')
    try {
      const payload = {
        scheduled_at: scheduleForm.scheduled_at || null,
        court: scheduleForm.court || null,
        estimated_duration_minutes: scheduleForm.estimated_duration_minutes
          ? Number(scheduleForm.estimated_duration_minutes)
          : null,
      }
      if (scheduleForm.status_id) {
        payload.status_id = scheduleForm.status_id
      }

      await adminMatchesApi.update(scheduleMatch.id, payload)
      setScheduleMatch(null)
      await load()
    } catch (err) {
      setMatchError(err?.data?.message || err?.message || 'No pudimos actualizar el partido.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Cuadros</h3>
          <p>Gestiona cuadros por categoría y publica resultados.</p>
        </div>
      </div>

      <div className="admin-grid">
        <div className="panel-card">
          <div className="panel-header">
            <h4>Crear cuadro</h4>
          </div>
          <form className="form-grid" onSubmit={handleCreate}>
            <label>
              Torneo
              <select value={form.tournament_id} onChange={handleChange('tournament_id')}>
                <option value="">Selecciona</option>
                {tournaments.map((tournament) => (
                  <option key={tournament.id} value={tournament.id}>
                    {tournament.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Categoría del torneo
              <select value={form.tournament_category_id} onChange={handleChange('tournament_category_id')}>
                <option value="">Selecciona</option>
                {categoryOptions.length === 0 ? (
                  <option value="" disabled>Selecciona un torneo primero</option>
                ) : (
                  categoryOptions.map((option) => (
                    <option key={option.id} value={option.id}>{option.label}</option>
                  ))
                )}
              </select>
            </label>
            <div className="registration-item">
              <div>
                <span>Formato</span>
                <strong>Eliminación directa</strong>
              </div>
            </div>
            <label>
              Estado
              <select value={form.status_id} onChange={handleChange('status_id')}>
                <option value="">Borrador</option>
                {statusOptions.map((status) => (
                  <option key={status.id} value={status.id}>{status.label}</option>
                ))}
              </select>
            </label>
            <div className="form-actions">
              <button className="primary-button" type="submit">Crear cuadro</button>
            </div>
          </form>
          {error && <p className="form-message error">{error}</p>}
        </div>

        <div className="panel-card">
          <div className="panel-header">
            <h4>Cuadros existentes</h4>
            <span className="tag muted">{brackets.length}</span>
          </div>
          {brackets.length === 0 ? (
            <div className="empty-state">No hay cuadros todavía.</div>
          ) : (
            <div className="registration-list">
              {brackets.map((bracket) => (
                <div key={bracket.id} className="registration-item bracket-item">
                  <div>
                    <strong>{bracket.tournament_category?.category?.display_name || bracket.tournament_category?.category?.name || 'Categoría'}</strong>
                    <span>{bracket.tournament_category?.tournament?.name || 'Torneo'}</span>
                    <span>Eliminación directa</span>
                  </div>
                  <div>
                    <span>Estado</span>
                    <strong>{bracket.status?.label || '—'}</strong>
                  </div>
                  <div>
                    <span>Slots</span>
                    <strong>{bracket.slots?.length || 0}</strong>
                  </div>
                  <div className="form-actions">
                    <button
                      className="secondary-button"
                      type="button"
                      onClick={() => handleGenerate(bracket)}
                    >
                      Generar cuadro
                    </button>
                    {bracket.status?.code === 'draft' ? (
                      <button
                        className="ghost-button"
                        type="button"
                        onClick={() => handleDelete(bracket)}
                      >
                        Eliminar cuadro
                      </button>
                    ) : null}
                    <button
                      className="ghost-button"
                      type="button"
                      onClick={() => setActiveBracketId(activeBracketId === bracket.id ? null : bracket.id)}
                    >
                      Ver cuadro
                    </button>
                  </div>
                  {activeBracketId === bracket.id ? (
                    <div className="bracket-view">
                      <BracketView bracket={bracket} onMatchClick={handleMatchClick} />
                      <div className="slot-editor">
                        <div className="panel-header">
                          <h5>Editar slots</h5>
                          <span className="tag muted">{sortedSlots.length}</span>
                        </div>
                        {sortedSlots.length === 0 ? (
                          <p className="muted">Genera el cuadro para asignar posiciones.</p>
                        ) : (
                          <div className="form-grid">
                            {sortedSlots.map((slot) => (
                              <label key={slot.id}>
                                Slot {slot.slot_number}
                                <select
                                  value={slot.registration?.id || ''}
                                  onChange={(event) => handleSlotChange(slot.id, event.target.value)}
                                >
                                  <option value="">Vacío</option>
                                  {registrationOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                      {option.label}
                                    </option>
                                  ))}
                                </select>
                              </label>
                            ))}
                          </div>
                        )}
                        {slotError && <p className="form-message error">{slotError}</p>}
                      </div>
                    </div>
                  ) : null}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {scheduleMatch ? (
        <div className="modal-backdrop" onClick={() => setScheduleMatch(null)}>
          <div className="modal-card" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h3>Programar partido</h3>
                <p>
                  Ronda {scheduleMatch.round_number} • Partido {scheduleMatch.match_number}
                </p>
              </div>
              <button className="ghost-button" type="button" onClick={() => setScheduleMatch(null)}>
                Cerrar
              </button>
            </div>
            <div className="modal-body">
              <div className="form-grid">
                <label>
                  No antes de
                  <DateTimePicker value={scheduleForm.scheduled_at} onChange={handleScheduleChange('scheduled_at')} />
                </label>
                <label>
                  Cancha
                  <input type="text" value={scheduleForm.court} onChange={handleScheduleChange('court')} />
                </label>
                <label>
                  Duración estimada (min)
                  <input
                    type="number"
                    min="10"
                    value={scheduleForm.estimated_duration_minutes}
                    onChange={handleScheduleChange('estimated_duration_minutes')}
                  />
                </label>
                <label>
                  Estado
                  <select value={scheduleForm.status_id} onChange={handleScheduleChange('status_id')}>
                    <option value="">Selecciona</option>
                    {matchStatusOptions.map((status) => (
                      <option key={status.id} value={status.id}>{status.label}</option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="form-actions">
                <button className="primary-button" type="button" onClick={handleScheduleSave}>
                  Guardar
                </button>
              </div>
              {matchError && <p className="form-message error">{matchError}</p>}
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
