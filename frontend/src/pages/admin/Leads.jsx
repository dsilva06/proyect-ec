import { useEffect, useMemo, useState } from 'react'
import { adminLeadsApi } from '../../features/leads/api'
import { statusesApi } from '../../features/statuses/api'

export default function Leads() {
  const [leads, setLeads] = useState([])
  const [statuses, setStatuses] = useState([])
  const [error, setError] = useState('')

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'lead'),
    [statuses],
  )

  const load = async () => {
    try {
      const [leadsData, statusesData] = await Promise.all([
        adminLeadsApi.list(),
        statusesApi.list(),
      ])
      setLeads(leadsData)
      setStatuses(statusesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los leads.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleStatusUpdate = async (leadId, statusId) => {
    try {
      await adminLeadsApi.update(leadId, { status_id: statusId })
      await load()
    } catch (err) {
      setError(err?.message || 'No pudimos actualizar el lead.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Leads</h3>
          <p>Contactos de interés desde la landing.</p>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      {leads.length === 0 ? (
        <div className="empty-state">No hay leads registrados.</div>
      ) : (
        <div className="registration-list">
          {leads.map((lead) => (
            <div key={lead.id} className="registration-item">
              <div>
                <strong>{lead.full_name}</strong>
                <span>{lead.email}</span>
                <span>{lead.company}</span>
              </div>
              <div>
                <span>Mensaje</span>
                <strong>{lead.message}</strong>
              </div>
              <div>
                <span>Estado</span>
                <select
                  value={lead.status?.id || ''}
                  onChange={(event) => handleStatusUpdate(lead.id, event.target.value)}
                >
                  {statusOptions.map((status) => (
                    <option key={status.id} value={status.id}>{status.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <span>Fecha</span>
                <strong>{lead.created_at?.slice(0, 10) || '—'}</strong>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
