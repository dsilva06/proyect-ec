import { useEffect, useState } from 'react'
import { playerRankingApi } from '../../features/players/api'

export default function Ranking() {
  const [form, setForm] = useState({ ranking_value: '', ranking_source: '' })
  const [user, setUser] = useState(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const load = async () => {
    setError('')
    try {
      const data = await playerRankingApi.get()
      setUser(data)
      setForm({
        ranking_value: data.ranking_value ?? '',
        ranking_source: data.ranking_source ?? '',
      })
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tu ranking.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')

    try {
      const payload = {
        ranking_value: form.ranking_value ? Number(form.ranking_value) : null,
        ranking_source: form.ranking_source || null,
      }
      const updated = await playerRankingApi.update(payload)
      setUser(updated)
      setMessage('Ranking actualizado.')
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos actualizar tu ranking.')
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Mi ranking</h3>
          <p>Ingresa tu ranking para ordenar tus inscripciones.</p>
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="panel-card">
        <div className="panel-header">
          <h4>Datos de ranking</h4>
          {user?.ranking_updated_at ? <span className="tag">Actualizado</span> : <span className="tag muted">Pendiente</span>}
        </div>

        <form className="form-grid" onSubmit={handleSubmit}>
          <label>
            Ranking (número)
            <input
              type="number"
              min="1"
              value={form.ranking_value}
              onChange={(event) => setForm((prev) => ({ ...prev, ranking_value: event.target.value }))}
            />
          </label>
          <label>
            Fuente / Circuito
            <select
              value={form.ranking_source}
              onChange={(event) => setForm((prev) => ({ ...prev, ranking_source: event.target.value }))}
            >
              <option value="">Selecciona</option>
              <option value="FEP">FEP</option>
              <option value="FIP">FIP</option>
              <option value="NONE">Sin ranking</option>
            </select>
          </label>
          <div className="form-actions">
            <button className="primary-button" type="submit">Guardar</button>
          </div>
        </form>

        {message && <p className="form-message success">{message}</p>}
        {user?.ranking_updated_at ? (
          <p className="form-message">Actualizado el {user.ranking_updated_at.slice(0, 10)}.</p>
        ) : null}
      </div>
    </section>
  )
}
