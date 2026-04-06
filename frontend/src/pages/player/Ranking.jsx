import { useEffect, useState } from 'react'
import { playerRankingApi } from '../../features/players/api'
import { formatPlayerDate } from './ui'

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
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Ranking</span>
          <h3>Mantén tu perfil listo antes de inscribirte.</h3>
          <p>Tu ranking ayuda a ordenar tus inscripciones. Si no compites aún en un circuito, puedes dejarlo sin ranking.</p>
        </div>
        <div className="player-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <div className="player-section-grid player-section-grid-tight">
        <section className="panel-card player-surface-card player-ranking-summary">
          <span className="player-section-kicker">Estado actual</span>
          <h4>{user?.ranking_value ? `#${user.ranking_value}` : 'Sin ranking cargado'}</h4>
          <p>
            {user?.ranking_source
              ? `Fuente registrada: ${user.ranking_source}.`
              : 'Aún no has definido una fuente o circuito para tu ranking.'}
          </p>
          <div className="player-metadata-grid">
            <div>
              <span>Fuente</span>
              <strong>{user?.ranking_source || 'Sin definir'}</strong>
            </div>
            <div>
              <span>Última actualización</span>
              <strong>{user?.ranking_updated_at ? formatPlayerDate(user.ranking_updated_at, { year: 'numeric' }) : 'Nunca'}</strong>
            </div>
          </div>
        </section>

        <section className="panel-card player-surface-card">
          <div className="panel-header">
            <h4>Actualizar ranking</h4>
            {user?.ranking_updated_at ? <span className="tag">Actualizado</span> : <span className="tag muted">Pendiente</span>}
          </div>

          <form className="form-grid player-form-grid" onSubmit={handleSubmit}>
            <label>
              Ranking (número)
              <input
                type="number"
                min="1"
                value={form.ranking_value}
                onChange={(event) => setForm((prev) => ({ ...prev, ranking_value: event.target.value }))}
                placeholder="Ejemplo: 185"
              />
            </label>
            <label>
              Fuente / circuito
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
            <p className="player-field-help">
              Si tu categoría usa ranking, este dato te evita fricción al momento de registrarte desde el teléfono.
            </p>
            <div className="form-actions">
              <button className="primary-button" type="submit">Guardar ranking</button>
            </div>
          </form>

          {message && <p className="form-message success">{message}</p>}
        </section>
      </div>
    </section>
  )
}
