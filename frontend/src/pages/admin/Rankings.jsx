import { useEffect, useState } from 'react'
import { adminPlayersApi } from '../../features/players/api'

export default function Rankings() {
  const [players, setPlayers] = useState([])
  const [filters, setFilters] = useState({ search: '' })
  const [edits, setEdits] = useState({})
  const [error, setError] = useState('')

  const load = async (nextFilters = filters) => {
    setError('')
    try {
      const data = await adminPlayersApi.list(nextFilters)
      setPlayers(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los rankings.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleFilterChange = (event) => {
    const value = event.target.value
    const nextFilters = { ...filters, search: value }
    setFilters(nextFilters)
    load(nextFilters)
  }

  const handleEdit = (userId, field, value) => {
    setEdits((prev) => ({
      ...prev,
      [userId]: {
        ...(prev[userId] || {}),
        [field]: value,
      },
    }))
  }

  const handleSave = async (userId) => {
    const payload = edits[userId] || {}
    try {
      await adminPlayersApi.updateRanking(userId, {
        ranking_value: payload.ranking_value ? Number(payload.ranking_value) : null,
        ranking_source: payload.ranking_source || null,
      })
      setEdits((prev) => ({ ...prev, [userId]: null }))
      await load()
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos guardar el ranking.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Rankings de jugadores</h3>
          <p>Valida los rankings para ordenar las inscripciones.</p>
        </div>
        <div className="admin-page-actions">
          <input
            type="text"
            placeholder="Buscar por nombre o email"
            value={filters.search}
            onChange={handleFilterChange}
          />
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      {players.length === 0 ? (
        <div className="empty-state">No hay jugadores registrados.</div>
      ) : (
        <div className="registration-list">
          {players.map((player) => {
            const edit = edits[player.id] || {}
            const rankingValue = edit.ranking_value ?? player.ranking_value ?? ''
            const rankingSource = edit.ranking_source ?? player.ranking_source ?? ''
            return (
              <div key={player.id} className="registration-item">
                <div>
                  <strong>{player.name}</strong>
                  <span>{player.email}</span>
                </div>
                <div>
                  <span>Ranking</span>
                  <input
                    type="number"
                    min="1"
                    value={rankingValue}
                    onChange={(event) => handleEdit(player.id, 'ranking_value', event.target.value)}
                  />
                </div>
                <div>
                  <span>Fuente</span>
                  <select
                    value={rankingSource}
                    onChange={(event) => handleEdit(player.id, 'ranking_source', event.target.value)}
                  >
                    <option value="">Selecciona</option>
                    <option value="FEP">FEP</option>
                    <option value="FIP">FIP</option>
                    <option value="NONE">Sin ranking</option>
                  </select>
                </div>
                <div>
                  <span>Estado</span>
                  <strong>{player.ranking_updated_at ? 'Actualizado' : 'Pendiente'}</strong>
                </div>
                <div className="form-actions">
                  <button
                    className="secondary-button"
                    type="button"
                    onClick={() => handleSave(player.id)}
                  >
                    Guardar
                  </button>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </section>
  )
}
