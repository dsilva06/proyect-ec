import { useEffect, useMemo, useState } from 'react'
import { adminOpenEntriesApi } from '../../features/openEntries/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

const SEGMENT_LABELS = {
  men: 'Masculino',
  women: 'Femenino',
}

const ASSIGNMENT_LABELS = {
  pending: 'Pendiente',
  assigned: 'Asignado',
}

const formatDate = (value) => {
  if (!value) return '—'
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

const getAssignmentTone = (assignmentStatus) => {
  if (assignmentStatus === 'assigned') return 'success'
  return 'warning'
}

const getPaidTone = (entry) => {
  if (entry.paid_at || entry.payment_is_covered) return 'success'
  return 'muted'
}

export default function OpenEntries() {
  const [entries, setEntries] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [filterTournamentId, setFilterTournamentId] = useState('')
  const [filterSegment, setFilterSegment] = useState('')
  const [filterPaid, setFilterPaid] = useState('')
  const [filterAssigned, setFilterAssigned] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [selectedEntryId, setSelectedEntryId] = useState(null)
  const [assignCategoryId, setAssignCategoryId] = useState('')
  const [assigning, setAssigning] = useState(false)
  const [assignError, setAssignError] = useState('')
  const [assignMessage, setAssignMessage] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const params = {}
      if (filterTournamentId) params.tournament_id = filterTournamentId
      if (filterSegment) params.segment = filterSegment
      if (filterPaid !== '') params.paid = filterPaid
      if (filterAssigned !== '') params.assigned = filterAssigned

      const [entriesData, tournamentsData] = await Promise.all([
        adminOpenEntriesApi.list(params),
        adminTournamentsApi.list(),
      ])
      setEntries(entriesData)
      setTournaments(tournamentsData.filter((t) =>
        String(t.mode || '').toLowerCase() === 'open',
      ))
    } catch (err) {
      setError(err?.message || 'No pudimos cargar las entradas OPEN.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [filterTournamentId, filterSegment, filterPaid, filterAssigned])

  const selectedEntry = useMemo(
    () => entries.find((e) => String(e.id) === String(selectedEntryId)) || null,
    [entries, selectedEntryId],
  )

  // Categories available for assignment in the selected entry's tournament
  const assignableCategoriesForEntry = useMemo(() => {
    if (!selectedEntry) return []
    const tournament = tournaments.find(
      (t) => String(t.id) === String(selectedEntry.tournament_id),
    )
    return tournament?.categories || []
  }, [selectedEntry, tournaments])

  const handleSelectEntry = (entry) => {
    setSelectedEntryId(String(entry.id))
    setAssignCategoryId('')
    setAssignError('')
    setAssignMessage('')
  }

  const handleClosePanel = () => {
    setSelectedEntryId(null)
    setAssignCategoryId('')
    setAssignError('')
    setAssignMessage('')
  }

  const handleAssign = async (event) => {
    event.preventDefault()
    if (!assignCategoryId) {
      setAssignError('Selecciona una categoría para asignar.')
      return
    }
    setAssigning(true)
    setAssignError('')
    setAssignMessage('')
    try {
      await adminOpenEntriesApi.assignCategory(selectedEntry.id, {
        tournament_category_id: Number(assignCategoryId),
      })
      setAssignMessage('Pareja asignada correctamente. Se creó la inscripción definitiva.')
      await load()
      setSelectedEntryId(null)
    } catch (err) {
      const fieldError =
        err?.data?.errors?.tournament_category_id?.[0] ||
        err?.data?.errors?.open_entry_id?.[0]
      setAssignError(fieldError || err?.data?.message || err?.message || 'No pudimos completar la asignación.')
    } finally {
      setAssigning(false)
    }
  }

  // ── Funnel KPIs ────────────────────────────────────────────────────────────
  const kpis = useMemo(() => {
    const total = entries.length
    const paid = entries.filter((e) => e.paid_at || e.payment_is_covered).length
    const awaitingAssignment = entries.filter(
      (e) => (e.paid_at || e.payment_is_covered) && e.assignment_status !== 'assigned',
    ).length
    const assigned = entries.filter((e) => e.assignment_status === 'assigned').length
    return { total, paid, awaitingAssignment, assigned }
  }, [entries])

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Entradas OPEN</h3>
          <p>Gestión del embudo de intake: parejas enviadas, pagadas y pendientes de asignación.</p>
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {/* ── Funnel KPIs ─────────────────────────────────────────────────────── */}
      <div className="kpi-grid">
        <div className="kpi-card">
          <span>Total entradas</span>
          <strong>{kpis.total}</strong>
        </div>
        <div className="kpi-card">
          <span>Entradas pagadas</span>
          <strong>{kpis.paid}</strong>
        </div>
        <div className="kpi-card">
          <span>Pendientes de asignación</span>
          <strong>{kpis.awaitingAssignment}</strong>
        </div>
        <div className="kpi-card">
          <span>Asignadas a categoría</span>
          <strong>{kpis.assigned}</strong>
        </div>
      </div>

      {/* ── Filters ─────────────────────────────────────────────────────────── */}
      <div className="panel-card">
        <div className="panel-header">
          <h4>Filtros</h4>
        </div>
        <div className="filter-row">
          <label>
            Torneo
            <select
              value={filterTournamentId}
              onChange={(e) => setFilterTournamentId(e.target.value)}
            >
              <option value="">Todos</option>
              {tournaments.map((t) => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
          </label>
          <label>
            Segmento
            <select
              value={filterSegment}
              onChange={(e) => setFilterSegment(e.target.value)}
            >
              <option value="">Todos</option>
              <option value="men">Masculino</option>
              <option value="women">Femenino</option>
            </select>
          </label>
          <label>
            Pago
            <select
              value={filterPaid}
              onChange={(e) => setFilterPaid(e.target.value)}
            >
              <option value="">Todos</option>
              <option value="true">Pagado</option>
              <option value="false">Sin pagar</option>
            </select>
          </label>
          <label>
            Asignación
            <select
              value={filterAssigned}
              onChange={(e) => setFilterAssigned(e.target.value)}
            >
              <option value="">Todas</option>
              <option value="false">Pendiente de asignación</option>
              <option value="true">Asignadas</option>
            </select>
          </label>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      {loading ? (
        <div className="empty-state">Cargando entradas OPEN...</div>
      ) : entries.length === 0 ? (
        <div className="empty-state">No hay entradas OPEN con los filtros actuales.</div>
      ) : (
        <div className="panel-card">
          <div className="panel-header">
            <h4>Entradas</h4>
            <span className="tag muted">{entries.length}</span>
          </div>
          <div className="data-table-wrapper">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Pareja</th>
                  <th>Torneo</th>
                  <th>Segmento</th>
                  <th>Pago</th>
                  <th>Asignación</th>
                  <th>Categoría asignada</th>
                  <th>Fecha entrada</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => {
                  const captain = entry.submitted_by
                  const captainName = captain?.name || `Usuario ${entry.submitted_by_user_id}`
                  const partnerName = [entry.partner_first_name, entry.partner_last_name]
                    .filter(Boolean)
                    .join(' ') || entry.partner_email
                  const isPaid = Boolean(entry.paid_at || entry.payment_is_covered)
                  const isAssigned = entry.assignment_status === 'assigned'

                  return (
                    <tr key={entry.id}>
                      <td>
                        <div>
                          <strong>{captainName}</strong>
                          <span> / {partnerName}</span>
                        </div>
                        <small className="muted">{entry.partner_email}</small>
                      </td>
                      <td>{entry.tournament?.name || '—'}</td>
                      <td>{SEGMENT_LABELS[entry.segment] || entry.segment || '—'}</td>
                      <td>
                        <span className={`tag tone-${getPaidTone(entry)}`}>
                          {isPaid ? 'Pagado' : 'Sin pagar'}
                        </span>
                        {entry.paid_at && (
                          <div><small className="muted">{formatDate(entry.paid_at)}</small></div>
                        )}
                      </td>
                      <td>
                        <span className={`tag tone-${getAssignmentTone(entry.assignment_status)}`}>
                          {ASSIGNMENT_LABELS[entry.assignment_status] || entry.assignment_status}
                        </span>
                      </td>
                      <td>
                        {isAssigned
                          ? (entry.assigned_tournament_category?.category?.display_name ||
                             entry.assigned_tournament_category?.category?.name ||
                             '—')
                          : <span className="muted">Sin asignar</span>}
                      </td>
                      <td>{formatDate(entry.created_at)}</td>
                      <td>
                        {!isAssigned && isPaid ? (
                          <button
                            className="primary-button"
                            type="button"
                            onClick={() => handleSelectEntry(entry)}
                          >
                            Asignar categoría
                          </button>
                        ) : (
                          <button
                            className="ghost-button"
                            type="button"
                            onClick={() => handleSelectEntry(entry)}
                          >
                            Ver detalle
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Assignment panel ─────────────────────────────────────────────────── */}
      {selectedEntry ? (
        <div className="modal-backdrop" onClick={handleClosePanel}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h3>Detalle de entrada OPEN</h3>
                <p>
                  {selectedEntry.submitted_by?.name || `Usuario ${selectedEntry.submitted_by_user_id}`}
                  {' '}/ {[selectedEntry.partner_first_name, selectedEntry.partner_last_name].filter(Boolean).join(' ') || selectedEntry.partner_email}
                </p>
              </div>
              <button className="ghost-button" type="button" onClick={handleClosePanel}>
                Cerrar
              </button>
            </div>
            <div className="modal-body">
              <div className="tournament-meta">
                <div>
                  <span>Torneo</span>
                  <strong>{selectedEntry.tournament?.name || '—'}</strong>
                </div>
                <div>
                  <span>Segmento</span>
                  <strong>{SEGMENT_LABELS[selectedEntry.segment] || selectedEntry.segment}</strong>
                </div>
                <div>
                  <span>Email pareja</span>
                  <strong>{selectedEntry.partner_email}</strong>
                </div>
                <div>
                  <span>DNI pareja</span>
                  <strong>{selectedEntry.partner_dni || '—'}</strong>
                </div>
                <div>
                  <span>Pago</span>
                  <strong>
                    {(selectedEntry.paid_at || selectedEntry.payment_is_covered)
                      ? `Pagado ${formatDate(selectedEntry.paid_at)}`
                      : 'Sin pagar'}
                  </strong>
                </div>
                <div>
                  <span>Asignación</span>
                  <strong>
                    {ASSIGNMENT_LABELS[selectedEntry.assignment_status] || selectedEntry.assignment_status}
                  </strong>
                </div>
                {selectedEntry.assignment_status === 'assigned' && (
                  <>
                    <div>
                      <span>Categoría asignada</span>
                      <strong>
                        {selectedEntry.assigned_tournament_category?.category?.display_name ||
                         selectedEntry.assigned_tournament_category?.category?.name ||
                         '—'}
                      </strong>
                    </div>
                    <div>
                      <span>Asignado por</span>
                      <strong>{selectedEntry.assigned_by?.name || '—'}</strong>
                    </div>
                    <div>
                      <span>Fecha de asignación</span>
                      <strong>{formatDate(selectedEntry.assigned_at)}</strong>
                    </div>
                    {selectedEntry.registration_id && (
                      <div>
                        <span>Inscripción creada</span>
                        <strong>#{selectedEntry.registration_id}</strong>
                      </div>
                    )}
                  </>
                )}
              </div>

              {selectedEntry.assignment_status !== 'assigned' && (
                <>
                  {!(selectedEntry.paid_at || selectedEntry.payment_is_covered) && (
                    <div className="empty-state">
                      Esta entrada aún no tiene pago confirmado. Solo se pueden asignar categorías a parejas pagadas.
                    </div>
                  )}

                  {(selectedEntry.paid_at || selectedEntry.payment_is_covered) && (
                    <form className="registration-form" onSubmit={handleAssign}>
                      <h4>Asignar categoría</h4>
                      <p className="muted">
                        Selecciona la categoría del torneo para esta pareja.
                        Al asignar se creará la inscripción definitiva en estado pagado.
                      </p>
                      <label>
                        Categoría
                        <select
                          value={assignCategoryId}
                          onChange={(e) => setAssignCategoryId(e.target.value)}
                        >
                          <option value="">Selecciona una categoría</option>
                          {assignableCategoriesForEntry.map((cat) => (
                            <option key={cat.id} value={cat.id}>
                              {cat.category?.display_name || cat.category?.name || `Categoría ${cat.id}`}
                              {cat.max_teams ? ` (cupo: ${cat.max_teams})` : ''}
                            </option>
                          ))}
                        </select>
                      </label>
                      <div className="form-actions">
                        <button
                          className="primary-button"
                          type="submit"
                          disabled={assigning}
                        >
                          {assigning ? 'Asignando...' : 'Confirmar asignación'}
                        </button>
                      </div>
                      {assignError && <p className="form-message error">{assignError}</p>}
                      {assignMessage && <p className="form-message success">{assignMessage}</p>}
                    </form>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
