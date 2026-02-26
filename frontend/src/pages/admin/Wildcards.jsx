import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { statusesApi } from '../../features/statuses/api'
import { adminTournamentsApi } from '../../features/tournaments/api'
import { adminWildcardsApi } from '../../features/wildcards/api'
import { cleanPayload } from '../../utils/cleanPayload'

const initialForm = {
  tournament_id: '',
  tournament_category_id: '',
  mode: 'link',
  email: '',
  player_name: '',
  partner_email: '',
  partner_name: '',
  wildcard_fee_waived: false,
  expires_at: '',
}

const toDateInput = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const year = parsed.getFullYear()
  const month = String(parsed.getMonth() + 1).padStart(2, '0')
  const day = String(parsed.getDate()).padStart(2, '0')
  const hours = String(parsed.getHours()).padStart(2, '0')
  const minutes = String(parsed.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

const formatDateTime = (value) => {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleString('es-ES')
}

const getInviteModeLabel = (invite) => (invite.has_registration ? 'Inscripción creada' : 'Invitación por link')

export default function Wildcards() {
  const [searchParams] = useSearchParams()
  const initialTournamentId = searchParams.get('tournament_id') || ''
  const initialCategoryId = searchParams.get('tournament_category_id') || ''

  const [form, setForm] = useState(() => ({
    ...initialForm,
    tournament_id: initialTournamentId,
    tournament_category_id: initialCategoryId,
  }))
  const [filters, setFilters] = useState(() => ({
    tournament_id: initialTournamentId,
    tournament_category_id: initialCategoryId,
    status_id: '',
    search: '',
  }))
  const [tournaments, setTournaments] = useState([])
  const [statuses, setStatuses] = useState([])
  const [invitations, setInvitations] = useState([])
  const [editingInvitation, setEditingInvitation] = useState(null)
  const [editForm, setEditForm] = useState(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isSavingEdit, setIsSavingEdit] = useState(false)

  const invitationStatusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'invitation'),
    [statuses],
  )

  const findTournamentByCategory = (categoryId) =>
    tournaments.find((tournament) =>
      (tournament.categories || []).some((category) => String(category.id) === String(categoryId)))

  const selectedTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(form.tournament_id)),
    [tournaments, form.tournament_id],
  )

  const categoryOptions = useMemo(() => {
    if (!selectedTournament) return []
    return (selectedTournament.categories || []).map((category) => ({
      id: category.id,
      label: category.category?.display_name || category.category?.name || 'Categoría',
    }))
  }, [selectedTournament])

  const editTournament = useMemo(() => {
    if (!editForm?.tournament_id) return null
    return tournaments.find((tournament) => String(tournament.id) === String(editForm.tournament_id)) || null
  }, [tournaments, editForm])

  const editCategoryOptions = useMemo(() => {
    if (!editTournament) return []
    return (editTournament.categories || []).map((category) => ({
      id: category.id,
      label: category.category?.display_name || category.category?.name || 'Categoría',
    }))
  }, [editTournament])

  const load = async (overrideFilters = filters) => {
    try {
      setError('')
      const [tournamentsData, statusesData, invitesData] = await Promise.all([
        adminTournamentsApi.list(),
        statusesApi.list(),
        adminWildcardsApi.list(cleanPayload(overrideFilters)),
      ])
      setTournaments(tournamentsData)
      setStatuses(statusesData)
      setInvitations(invitesData)
      return { tournamentsData, invitesData }
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los wildcards.')
      return null
    }
  }

  useEffect(() => {
    load()
  }, [])

  useEffect(() => {
    if (!form.tournament_id && form.tournament_category_id && tournaments.length > 0) {
      const parentTournament = findTournamentByCategory(form.tournament_category_id)
      if (parentTournament) {
        setForm((prev) => ({ ...prev, tournament_id: String(parentTournament.id) }))
      }
    }

    if (!filters.tournament_id && filters.tournament_category_id && tournaments.length > 0) {
      const parentTournament = findTournamentByCategory(filters.tournament_category_id)
      if (parentTournament) {
        setFilters((prev) => ({ ...prev, tournament_id: String(parentTournament.id) }))
      }
    }
  }, [tournaments, form.tournament_id, form.tournament_category_id, filters.tournament_id, filters.tournament_category_id])

  const handleFormChange = (field) => (event) => {
    const value = field === 'wildcard_fee_waived' ? event.target.checked : event.target.value
    setForm((prev) => ({
      ...prev,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
    }))
  }

  const handleFiltersChange = (field) => (event) => {
    const value = event.target.value
    const nextFilters = {
      ...filters,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
    }
    setFilters(nextFilters)
    load(nextFilters)
  }

  const handleCreate = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')
    setIsSubmitting(true)

    try {
      const payload = cleanPayload({
        mode: form.mode,
        tournament_category_id: form.tournament_category_id,
        email: form.email,
        player_name: form.player_name,
        partner_email: form.partner_email,
        partner_name: form.partner_name,
        wildcard_fee_waived: form.wildcard_fee_waived,
        expires_at: form.expires_at || undefined,
      })

      const result = await adminWildcardsApi.create(payload)
      const inviteLabel = result?.token ? `Token: ${result.token}` : 'Wildcard creado correctamente.'
      setMessage(inviteLabel)
      setForm((prev) => ({
        ...initialForm,
        tournament_id: prev.tournament_id,
        tournament_category_id: prev.tournament_category_id,
      }))
      await load(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos crear el wildcard.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const openEditModal = (invite) => {
    const tournamentId = String(invite.tournament_category?.tournament?.id || '')
    setEditingInvitation(invite)
    setEditForm({
      tournament_id: tournamentId,
      tournament_category_id: String(invite.tournament_category_id || ''),
      email: invite.email || '',
      partner_email: invite.partner_email || '',
      partner_name: invite.partner_name || '',
      wildcard_fee_waived: Boolean(invite.wildcard_fee_waived),
      status_id: String(invite.status?.id || ''),
      expires_at: toDateInput(invite.expires_at),
    })
  }

  const closeEditModal = () => {
    setEditingInvitation(null)
    setEditForm(null)
  }

  const handleEditFormChange = (field) => (event) => {
    if (!editForm) return
    const value = field === 'wildcard_fee_waived' ? event.target.checked : event.target.value
    setEditForm((prev) => ({
      ...prev,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
    }))
  }

  const handleUpdate = async (event) => {
    event.preventDefault()
    if (!editingInvitation || !editForm) return

    setError('')
    setMessage('')
    setIsSavingEdit(true)

    try {
      const payload = {
        tournament_category_id: editForm.tournament_category_id || undefined,
        email: editForm.email || undefined,
        wildcard_fee_waived: Boolean(editForm.wildcard_fee_waived),
        status_id: editForm.status_id || undefined,
        expires_at: editForm.expires_at || null,
      }
      payload.partner_email = editForm.partner_email === '' ? null : editForm.partner_email
      payload.partner_name = editForm.partner_name === '' ? null : editForm.partner_name

      await adminWildcardsApi.update(editingInvitation.id, payload)
      setMessage('Wildcard actualizado correctamente.')
      closeEditModal()
      await load(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos actualizar el wildcard.')
    } finally {
      setIsSavingEdit(false)
    }
  }

  const handleDelete = async (invite) => {
    const confirmed = window.confirm('¿Seguro que deseas eliminar este wildcard?')
    if (!confirmed) return

    setError('')
    setMessage('')

    try {
      await adminWildcardsApi.remove(invite.id)
      setMessage('Wildcard eliminado.')
      await load(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos eliminar el wildcard.')
    }
  }

  const handleCopyLink = async (invite) => {
    try {
      const link = invite.invite_url || `${window.location.origin}/wildcard/${invite.token}`
      await navigator.clipboard.writeText(link)
      setMessage('Link de invitación copiado.')
    } catch {
      setError('No pudimos copiar el link al portapapeles.')
    }
  }

  const filterTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(filters.tournament_id)),
    [tournaments, filters.tournament_id],
  )

  const filterCategoryOptions = useMemo(() => {
    if (!filterTournament) return []
    return (filterTournament.categories || []).map((category) => ({
      id: category.id,
      label: category.category?.display_name || category.category?.name || 'Categoría',
    }))
  }, [filterTournament])

  return (
    <section className="admin-page wildcards-page">
      <div className="admin-page-header">
        <div>
          <h3>Wildcards</h3>
          <p>Crea, gestiona y consulta wildcards con detalle de estado, torneo y registro.</p>
        </div>
      </div>

      {message && <div className="form-message success">{message}</div>}
      {error && <div className="form-message error">{error}</div>}

      <div className="wildcards-layout">
        <div className="panel-card wildcards-form-card">
          <div className="panel-header">
            <h4>Crear wildcard</h4>
            {(initialTournamentId || initialCategoryId) && <span className="tag muted">Contexto desde torneo</span>}
          </div>
          <form className="form-grid" onSubmit={handleCreate}>
            <label>
              Torneo
              <select value={form.tournament_id} onChange={handleFormChange('tournament_id')}>
                <option value="">Selecciona</option>
                {tournaments.map((tournament) => (
                  <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
                ))}
              </select>
            </label>
            <label>
              Categoría del torneo
              <select value={form.tournament_category_id} onChange={handleFormChange('tournament_category_id')}>
                <option value="">Selecciona</option>
                {categoryOptions.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </select>
            </label>
            <label>
              Modo
              <select value={form.mode} onChange={handleFormChange('mode')}>
                <option value="link">Link de invitación</option>
                <option value="manual">Crear inscripción directa</option>
              </select>
            </label>
            <label>
              Expira en
              <input type="datetime-local" value={form.expires_at} onChange={handleFormChange('expires_at')} />
            </label>
            <label>
              Email jugador
              <input type="email" value={form.email} onChange={handleFormChange('email')} required />
            </label>
            <label>
              Nombre jugador
              <input type="text" value={form.player_name} onChange={handleFormChange('player_name')} />
            </label>
            <label>
              Email partner
              <input type="email" value={form.partner_email} onChange={handleFormChange('partner_email')} />
            </label>
            <label>
              Nombre partner
              <input type="text" value={form.partner_name} onChange={handleFormChange('partner_name')} />
            </label>
            <label className="checkbox-row field-span-2">
              <input
                type="checkbox"
                checked={form.wildcard_fee_waived}
                onChange={handleFormChange('wildcard_fee_waived')}
              />
              Exonerar pago para este wildcard
            </label>
            <div className="form-actions field-span-2">
              <button className="primary-button" type="submit" disabled={isSubmitting}>
                {isSubmitting ? 'Creando...' : 'Crear wildcard'}
              </button>
            </div>
          </form>
        </div>

        <div className="panel-card wildcards-list-card">
          <div className="panel-header">
            <h4>Wildcards</h4>
            <span className="tag muted">{invitations.length}</span>
          </div>

          <div className="wildcards-filter-row">
            <select value={filters.tournament_id} onChange={handleFiltersChange('tournament_id')}>
              <option value="">Todos los torneos</option>
              {tournaments.map((tournament) => (
                <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
              ))}
            </select>
            <select value={filters.tournament_category_id} onChange={handleFiltersChange('tournament_category_id')}>
              <option value="">Todas las categorías</option>
              {filterCategoryOptions.map((option) => (
                <option key={option.id} value={option.id}>{option.label}</option>
              ))}
            </select>
            <select value={filters.status_id} onChange={handleFiltersChange('status_id')}>
              <option value="">Todos los estados</option>
              {invitationStatusOptions.map((status) => (
                <option key={status.id} value={status.id}>{status.label}</option>
              ))}
            </select>
            <input
              type="text"
              value={filters.search}
              onChange={handleFiltersChange('search')}
              placeholder="Buscar email, partner o token"
            />
          </div>

          {invitations.length === 0 ? (
            <div className="empty-state">No hay wildcards para estos filtros.</div>
          ) : (
            <div className="wildcards-list">
              {invitations.map((invite) => (
                <article key={invite.id} className="wildcard-item">
                  <div className="wildcard-item-main">
                    <div className="wildcard-item-head">
                      <strong>{invite.email || 'Sin email'}</strong>
                      <span className="tag muted">{getInviteModeLabel(invite)}</span>
                    </div>
                    <p className="muted">
                      {invite.tournament_category?.tournament?.name || 'Torneo'}
                      {' • '}
                      {invite.tournament_category?.category?.display_name || invite.tournament_category?.category?.name || 'Categoría'}
                    </p>
                    <div className="wildcard-item-meta">
                      <span><strong>Partner:</strong> {invite.partner_email || invite.partner_name || '—'}</span>
                      <span><strong>Estado:</strong> {invite.status?.label || 'Sin estado'}</span>
                      <span><strong>Expira:</strong> {formatDateTime(invite.expires_at)}</span>
                      <span><strong>Token:</strong> {invite.token}</span>
                      <span><strong>Pago:</strong> {invite.wildcard_fee_waived ? 'Exonerado' : 'Normal'}</span>
                      <span>
                        <strong>Inscripción:</strong>{' '}
                        {invite.registration ? `${invite.registration.team?.display_name || 'Equipo'} (${invite.registration.status?.label || 'Sin estado'})` : 'No creada'}
                      </span>
                    </div>
                  </div>

                  <div className="wildcard-item-actions">
                    <button className="secondary-button" type="button" onClick={() => openEditModal(invite)}>
                      Editar
                    </button>
                    <button className="ghost-button" type="button" onClick={() => handleCopyLink(invite)}>
                      Copiar link
                    </button>
                    <button
                      className="ghost-button"
                      type="button"
                      onClick={() => handleDelete(invite)}
                      disabled={Boolean(invite.has_registration)}
                      title={invite.has_registration ? 'No se puede eliminar: ya tiene inscripción asociada.' : 'Eliminar wildcard'}
                    >
                      Eliminar
                    </button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </div>
      </div>

      {editingInvitation && editForm && (
        <div className="modal-backdrop" onClick={closeEditModal}>
          <div className="modal-card" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h4>Editar wildcard</h4>
                <p className="muted">{editingInvitation.email}</p>
              </div>
              <button className="ghost-button" type="button" onClick={closeEditModal}>Cerrar</button>
            </div>

            <form className="form-grid" onSubmit={handleUpdate}>
              <label>
                Torneo
                <select
                  value={editForm.tournament_id}
                  onChange={handleEditFormChange('tournament_id')}
                  disabled={Boolean(editingInvitation.registration)}
                >
                  <option value="">Selecciona</option>
                  {tournaments.map((tournament) => (
                    <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
                  ))}
                </select>
              </label>
              <label>
                Categoría
                <select
                  value={editForm.tournament_category_id}
                  onChange={handleEditFormChange('tournament_category_id')}
                  disabled={Boolean(editingInvitation.registration)}
                >
                  <option value="">Selecciona</option>
                  {editCategoryOptions.map((option) => (
                    <option key={option.id} value={option.id}>{option.label}</option>
                  ))}
                </select>
              </label>
              <label>
                Estado
                <select value={editForm.status_id} onChange={handleEditFormChange('status_id')}>
                  <option value="">Sin estado</option>
                  {invitationStatusOptions.map((status) => (
                    <option key={status.id} value={status.id}>{status.label}</option>
                  ))}
                </select>
              </label>
              <label>
                Expira en
                <input
                  type="datetime-local"
                  value={editForm.expires_at}
                  onChange={handleEditFormChange('expires_at')}
                />
              </label>
              <label>
                Email jugador
                <input type="email" value={editForm.email} onChange={handleEditFormChange('email')} />
              </label>
              <label>
                Email partner
                <input type="email" value={editForm.partner_email} onChange={handleEditFormChange('partner_email')} />
              </label>
              <label className="field-span-2">
                Nombre partner
                <input type="text" value={editForm.partner_name} onChange={handleEditFormChange('partner_name')} />
              </label>
              <label className="checkbox-row field-span-2">
                <input
                  type="checkbox"
                  checked={editForm.wildcard_fee_waived}
                  onChange={handleEditFormChange('wildcard_fee_waived')}
                />
                Exonerar pago para este wildcard
              </label>

              {editingInvitation.registration && (
                <p className="form-message">Este wildcard ya creó inscripción, por eso la categoría queda bloqueada.</p>
              )}

              <div className="form-actions field-span-2">
                <button className="primary-button" type="submit" disabled={isSavingEdit}>
                  {isSavingEdit ? 'Guardando...' : 'Guardar cambios'}
                </button>
                <button className="ghost-button" type="button" onClick={closeEditModal}>Cancelar</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
