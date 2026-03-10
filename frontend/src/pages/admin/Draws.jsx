import { useEffect, useMemo, useState } from 'react'
import { adminBracketsApi } from '../../features/brackets/api'
import { adminMatchesApi } from '../../features/matches/api'
import { adminTournamentsApi } from '../../features/tournaments/api'
import { statusesApi } from '../../features/statuses/api'
import { cleanPayload } from '../../utils/cleanPayload'
import BracketView from '../../components/brackets/BracketView'

const initialForm = {
  tournament_id: '',
  tournament_category_id: '',
  status_id: '',
}

const getTeamLabel = (registration) => registration?.team?.display_name || 'Por definir'

const hasGeneratedBracket = (bracket) => ((bracket?.matches?.length || 0) > 0 || (bracket?.slots?.length || 0) > 0)

const toNumberOrNull = (value) => {
  if (value === '' || value === null || value === undefined) return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const buildInitialScoreForm = (match) => {
  const sets = Array.isArray(match?.score_json?.sets) ? match.score_json.sets : []

  return {
    set1_a: sets[0]?.a ?? '',
    set1_b: sets[0]?.b ?? '',
    set2_a: sets[1]?.a ?? '',
    set2_b: sets[1]?.b ?? '',
    set3_a: sets[2]?.a ?? '',
    set3_b: sets[2]?.b ?? '',
    winner_registration_id: match?.winner_registration?.id ? String(match.winner_registration.id) : '',
  }
}

const buildScoreSets = (scoreForm) => [
  {
    a: toNumberOrNull(scoreForm.set1_a),
    b: toNumberOrNull(scoreForm.set1_b),
  },
  {
    a: toNumberOrNull(scoreForm.set2_a),
    b: toNumberOrNull(scoreForm.set2_b),
  },
  {
    a: toNumberOrNull(scoreForm.set3_a),
    b: toNumberOrNull(scoreForm.set3_b),
  },
]

const resolveWinnerFromSets = (match, sets) => {
  let winsA = 0
  let winsB = 0

  sets.forEach((set) => {
    if (set.a === null || set.b === null) return
    if (set.a > set.b) winsA += 1
    if (set.b > set.a) winsB += 1
  })

  if (winsA === winsB) return null

  return winsA > winsB
    ? match?.registration_a?.id ?? null
    : match?.registration_b?.id ?? null
}

export default function Draws() {
  const [brackets, setBrackets] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [statuses, setStatuses] = useState([])
  const [form, setForm] = useState(initialForm)
  const [boardTournamentId, setBoardTournamentId] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [scoreMatch, setScoreMatch] = useState(null)
  const [scoreForm, setScoreForm] = useState(buildInitialScoreForm(null))
  const [scoreError, setScoreError] = useState('')

  const selectedBoardTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(boardTournamentId)) || null,
    [tournaments, boardTournamentId],
  )

  const selectedCreateTournament = useMemo(
    () => tournaments.find((tournament) => String(tournament.id) === String(form.tournament_id)) || null,
    [tournaments, form.tournament_id],
  )

  const categoryOptions = useMemo(() => {
    if (!selectedCreateTournament) return []
    return (selectedCreateTournament.categories || []).map((category) => ({
      id: category.id,
      label: `${category.category?.display_name || category.category?.name || 'Categoria'}`,
    }))
  }, [selectedCreateTournament])

  const statusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'bracket'),
    [statuses],
  )

  const matchStatusOptions = useMemo(
    () => statuses.filter((status) => status.module === 'match'),
    [statuses],
  )

  const completedMatchStatusId = useMemo(
    () => matchStatusOptions.find((status) => String(status.code) === 'completed')?.id || null,
    [matchStatusOptions],
  )

  const loadMeta = async () => {
    try {
      setError('')
      const [tournamentsData, statusesData] = await Promise.all([
        adminTournamentsApi.list(),
        statusesApi.list(),
      ])

      setTournaments(tournamentsData)
      setStatuses(statusesData)

      if (!boardTournamentId && tournamentsData.length > 0) {
        const firstId = String(tournamentsData[0].id)
        setBoardTournamentId(firstId)
      }
    } catch (err) {
      setError(err?.message || 'No pudimos cargar el modulo de cuadros.')
    }
  }

  const loadBrackets = async (tournamentId) => {
    if (!tournamentId) {
      setBrackets([])
      return
    }

    try {
      setError('')
      const data = await adminBracketsApi.list(cleanPayload({ tournament_id: tournamentId }))
      setBrackets(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los cuadros.')
    }
  }

  useEffect(() => {
    loadMeta()
  }, [])

  useEffect(() => {
    if (!boardTournamentId) {
      setBrackets([])
      return
    }

    loadBrackets(boardTournamentId)
  }, [boardTournamentId])

  useEffect(() => {
    if (!form.tournament_id && boardTournamentId) {
      setForm((prev) => ({ ...prev, tournament_id: boardTournamentId }))
    }
  }, [form.tournament_id, boardTournamentId])

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
    setMessage('')

    try {
      await adminBracketsApi.create({
        ...cleanPayload(form),
        status_id: form.status_id || statusOptions?.[0]?.id,
      })

      setForm((prev) => ({ ...initialForm, tournament_id: prev.tournament_id || boardTournamentId }))
      setMessage('Cuadro creado correctamente.')
      await loadBrackets(boardTournamentId)
    } catch (err) {
      setError(err?.message || 'No pudimos crear el cuadro.')
    }
  }

  const handleGenerate = async (bracket, mode = 'manual') => {
    setError('')
    setMessage('')

    try {
      if ((bracket.slots?.length || 0) > 0 || (bracket.matches?.length || 0) > 0) {
        const confirmRegenerate = window.confirm(
          'Esto borrara el cuadro actual y generara uno nuevo. Deseas continuar?',
        )
        if (!confirmRegenerate) return
      }

      const randomize = mode === 'randomize'
      await adminBracketsApi.generate(bracket.id, { randomize })
      setMessage(randomize ? 'Cuadro generado con randomize.' : 'Cuadro generado con sorteo manual.')
      await loadBrackets(boardTournamentId)
    } catch (err) {
      setError(err?.message || 'No pudimos generar el cuadro.')
    }
  }

  const handleDelete = async (bracket) => {
    setError('')
    setMessage('')

    const confirmDelete = window.confirm(
      'Esto eliminara el cuadro, sus matches y slots. Deseas continuar?',
    )
    if (!confirmDelete) return

    try {
      await adminBracketsApi.remove(bracket.id)
      setMessage('Cuadro eliminado.')
      await loadBrackets(boardTournamentId)
    } catch (err) {
      setError(err?.message || 'No pudimos eliminar el cuadro.')
    }
  }

  const openScoreModal = (match) => {
    if (!match?.id) return

    setScoreError('')
    setScoreMatch(match)
    setScoreForm(buildInitialScoreForm(match))
  }

  const closeScoreModal = () => {
    setScoreMatch(null)
    setScoreForm(buildInitialScoreForm(null))
    setScoreError('')
  }

  const handleScoreChange = (field) => (event) => {
    setScoreForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const handleSaveScore = async () => {
    if (!scoreMatch) return

    const sets = buildScoreSets(scoreForm)
    const winnerFromScore = resolveWinnerFromSets(scoreMatch, sets)
    const selectedWinner = toNumberOrNull(scoreForm.winner_registration_id)
    const winnerId = selectedWinner || winnerFromScore

    if (!winnerId) {
      setScoreError('Define el ganador con el marcador o selecciona la pareja ganadora.')
      return
    }

    const payload = {
      score_json: { sets },
      winner_registration_id: winnerId,
    }

    if (completedMatchStatusId) {
      payload.status_id = completedMatchStatusId
    }

    try {
      setScoreError('')
      await adminMatchesApi.update(scoreMatch.id, payload)
      setMessage('Score guardado. El ganador avanzara automaticamente en la llave.')
      closeScoreModal()
      await loadBrackets(boardTournamentId)
    } catch (err) {
      setScoreError(err?.data?.message || err?.message || 'No pudimos guardar el score.')
    }
  }

  return (
    <section className="admin-page draws-page">
      <div className="admin-page-header">
        <div>
          <h3>Cuadros</h3>
          <p>Trabaja varios cuadros en paralelo, filtrados por torneo, con carga de score por partido.</p>
        </div>
      </div>

      <div className="panel-card draws-toolbar-card">
        <div className="admin-page-actions draws-toolbar">
          <label>
            Torneo (filtro)
            <select value={boardTournamentId} onChange={(event) => setBoardTournamentId(event.target.value)}>
              <option value="">Selecciona un torneo</option>
              {tournaments.map((tournament) => (
                <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
              ))}
            </select>
          </label>
          <button className="secondary-button" type="button" onClick={loadMeta}>
            Actualizar torneos
          </button>
          <button className="secondary-button" type="button" onClick={() => loadBrackets(boardTournamentId)} disabled={!boardTournamentId}>
            Actualizar cuadros
          </button>
        </div>
      </div>

      <div className="admin-grid draws-main-grid">
        <div className="panel-card draws-create-card">
          <div className="panel-header">
            <h4>Crear cuadro</h4>
            <span className="tag muted">Eliminacion directa</span>
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
              Categoria del torneo
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
        </div>

        <div className="panel-card draws-brackets-card">
          <div className="panel-header">
            <h4>
              Cuadros del torneo
              {selectedBoardTournament ? `: ${selectedBoardTournament.name}` : ''}
            </h4>
            <span className="tag muted">{brackets.length}</span>
          </div>

          {!boardTournamentId ? (
            <div className="empty-state">Selecciona un torneo para trabajar sus cuadros.</div>
          ) : brackets.length === 0 ? (
            <div className="empty-state">No hay cuadros para este torneo.</div>
          ) : (
            <div className="draws-bracket-list">
              {brackets.map((bracket) => (
                <article key={bracket.id} className="panel-card draws-bracket-item">
                  <div className="panel-header">
                    <div>
                      <h5>{bracket.tournament_category?.category?.display_name || bracket.tournament_category?.category?.name || 'Categoria'}</h5>
                      <p className="muted">{bracket.status?.label || 'Sin estado'}</p>
                    </div>
                    <div className="form-actions">
                      <button className="secondary-button" type="button" onClick={() => handleGenerate(bracket, 'manual')}>
                        Generar manual
                      </button>
                      <button className="primary-button" type="button" onClick={() => handleGenerate(bracket, 'randomize')}>
                        Randomize
                      </button>
                      {bracket.status?.code === 'draft' ? (
                        <button className="ghost-button" type="button" onClick={() => handleDelete(bracket)}>
                          Eliminar
                        </button>
                      ) : null}
                    </div>
                  </div>

                  <div className="draws-bracket-meta">
                    <span className="tag muted">Slots: {bracket.slots?.length || 0}</span>
                    <span className="tag muted">Matches: {bracket.matches?.length || 0}</span>
                  </div>

                  {hasGeneratedBracket(bracket) ? (
                    <div className="draws-bracket-view">
                      <BracketView
                        bracket={bracket}
                        onMatchClick={openScoreModal}
                        matchActionLabel="Score"
                      />
                    </div>
                  ) : (
                    <div className="empty-state">Genera el cuadro para comenzar a cargar resultados.</div>
                  )}
                </article>
              ))}
            </div>
          )}
        </div>
      </div>

      {error && <p className="form-message error">{error}</p>}
      {message && <p className="form-message success">{message}</p>}

      {scoreMatch ? (
        <div className="modal-backdrop" onClick={closeScoreModal}>
          <div className="modal-card score-modal" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h3>Score del partido</h3>
                <p>Ronda {scoreMatch.round_number} • Partido {scoreMatch.match_number}</p>
              </div>
              <button className="ghost-button" type="button" onClick={closeScoreModal}>
                Cerrar
              </button>
            </div>
            <div className="modal-body">
              <div className="score-teams">
                <p><strong>Pareja A:</strong> {getTeamLabel(scoreMatch.registration_a)}</p>
                <p><strong>Pareja B:</strong> {getTeamLabel(scoreMatch.registration_b)}</p>
              </div>

              <div className="form-grid score-grid">
                <label>
                  Set 1 - A
                  <input type="number" min="0" value={scoreForm.set1_a} onChange={handleScoreChange('set1_a')} />
                </label>
                <label>
                  Set 1 - B
                  <input type="number" min="0" value={scoreForm.set1_b} onChange={handleScoreChange('set1_b')} />
                </label>
                <label>
                  Set 2 - A
                  <input type="number" min="0" value={scoreForm.set2_a} onChange={handleScoreChange('set2_a')} />
                </label>
                <label>
                  Set 2 - B
                  <input type="number" min="0" value={scoreForm.set2_b} onChange={handleScoreChange('set2_b')} />
                </label>
                <label>
                  Set 3 - A
                  <input type="number" min="0" value={scoreForm.set3_a} onChange={handleScoreChange('set3_a')} />
                </label>
                <label>
                  Set 3 - B
                  <input type="number" min="0" value={scoreForm.set3_b} onChange={handleScoreChange('set3_b')} />
                </label>
                <label className="field-span-2">
                  Ganador
                  <select
                    value={scoreForm.winner_registration_id}
                    onChange={handleScoreChange('winner_registration_id')}
                  >
                    <option value="">Automatico por score</option>
                    {scoreMatch.registration_a?.id ? (
                      <option value={scoreMatch.registration_a.id}>Pareja A - {getTeamLabel(scoreMatch.registration_a)}</option>
                    ) : null}
                    {scoreMatch.registration_b?.id ? (
                      <option value={scoreMatch.registration_b.id}>Pareja B - {getTeamLabel(scoreMatch.registration_b)}</option>
                    ) : null}
                  </select>
                </label>
              </div>

              <div className="form-actions">
                <button className="primary-button" type="button" onClick={handleSaveScore}>
                  Guardar score
                </button>
              </div>
              <p className="muted">Al guardar, el ganador avanza automaticamente a la siguiente fase de la llave.</p>
              {scoreError && <p className="form-message error">{scoreError}</p>}
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
