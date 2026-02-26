import { useEffect, useMemo, useState } from 'react'
import { adminCategoriesApi } from '../../features/categories/api'
import { adminPlayersApi } from '../../features/players/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

const defaultFilters = {
  search: '',
  category_id: '',
}

const defaultPayoutForm = {
  tournament_id: '',
  tournament_category_id: '',
  position: 'champion',
  amount_eur_cents: '',
  notes: '',
}

const formatMoney = (value) => {
  const cents = Number(value || 0)
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
  }).format(cents / 100)
}

const formatDate = (value) => {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleString('es-ES')
}

const getAllowedPositions = (levelCode) => {
  if ((levelCode || '').toLowerCase() === 'segunda') {
    return [
      { value: 'champion', label: 'Campeón' },
      { value: 'runner_up', label: 'Subcampeón' },
      { value: 'semifinalist', label: 'Semifinalista' },
    ]
  }

  return [
    { value: 'champion', label: 'Campeón' },
    { value: 'runner_up', label: 'Subcampeón' },
  ]
}

export default function Rankings() {
  const [players, setPlayers] = useState([])
  const [categories, setCategories] = useState([])
  const [tournaments, setTournaments] = useState([])
  const [filters, setFilters] = useState(defaultFilters)
  const [rankingEdits, setRankingEdits] = useState({})
  const [rule, setRule] = useState({ win_points: 10, final_played_bonus: 5, final_won_bonus: 8 })
  const [ruleDraft, setRuleDraft] = useState({ win_points: 10, final_played_bonus: 5, final_won_bonus: 8 })
  const [selectedPlayer, setSelectedPlayer] = useState(null)
  const [palmares, setPalmares] = useState(null)
  const [payouts, setPayouts] = useState([])
  const [payoutForm, setPayoutForm] = useState(defaultPayoutForm)
  const [payoutEdits, setPayoutEdits] = useState({})
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [isLoadingModal, setIsLoadingModal] = useState(false)

  const loadBaseData = async () => {
    try {
      const [categoriesData, tournamentsData, ruleData] = await Promise.all([
        adminCategoriesApi.list(),
        adminTournamentsApi.list(),
        adminPlayersApi.getInternalRule(),
      ])
      setCategories(categoriesData)
      setTournaments(tournamentsData)
      setRule(ruleData)
      setRuleDraft({
        win_points: ruleData.win_points,
        final_played_bonus: ruleData.final_played_bonus,
        final_won_bonus: ruleData.final_won_bonus,
      })
    } catch (err) {
      setError(err?.message || 'No pudimos cargar la configuración de jugadores.')
    }
  }

  const loadPlayers = async (nextFilters = filters) => {
    setError('')
    try {
      const data = await adminPlayersApi.list(nextFilters)
      setPlayers(data)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar los jugadores.')
    }
  }

  const loadPlayerDetails = async (playerId, categoryId = filters.category_id) => {
    const params = categoryId ? { category_id: categoryId } : {}
    const [palmaresData, payoutsData] = await Promise.all([
      adminPlayersApi.palmares(playerId, params),
      adminPlayersApi.listPrizePayouts(playerId, params),
    ])

    setPalmares(palmaresData)
    setPayouts(payoutsData.items || [])
    setPayoutEdits({})
    setPayoutForm((prev) => ({
      ...defaultPayoutForm,
      tournament_id: prev.tournament_id,
      tournament_category_id: prev.tournament_category_id,
    }))
  }

  useEffect(() => {
    loadBaseData()
    loadPlayers(defaultFilters)
  }, [])

  const handleFilterChange = (field) => (event) => {
    const value = event.target.value
    const nextFilters = {
      ...filters,
      [field]: value,
    }
    setFilters(nextFilters)
    loadPlayers(nextFilters)
  }

  const handleRankingEdit = (playerId, value) => {
    setRankingEdits((prev) => ({
      ...prev,
      [playerId]: value,
    }))
  }

  const handleSaveFep = async (player) => {
    const draft = rankingEdits[player.id]
    const ranking_fep_value = draft === '' || draft === undefined
      ? null
      : Number(draft)

    try {
      setError('')
      await adminPlayersApi.updateFepRanking(player.id, { ranking_fep_value })
      setMessage('Ranking FEP actualizado.')
      await loadPlayers(filters)
      if (selectedPlayer && selectedPlayer.id === player.id) {
        await loadPlayerDetails(player.id)
      }
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos guardar el ranking FEP.')
    }
  }

  const openPlayerModal = async (player) => {
    setSelectedPlayer(player)
    setIsLoadingModal(true)
    try {
      await loadPlayerDetails(player.id)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos cargar el palmarés del jugador.')
    } finally {
      setIsLoadingModal(false)
    }
  }

  const closePlayerModal = () => {
    setSelectedPlayer(null)
    setPalmares(null)
    setPayouts([])
    setPayoutForm(defaultPayoutForm)
    setPayoutEdits({})
  }

  const handleRuleDraftChange = (field) => (event) => {
    setRuleDraft((prev) => ({
      ...prev,
      [field]: event.target.value,
    }))
  }

  const handleSaveRule = async () => {
    try {
      setError('')
      const payload = {
        win_points: Number(ruleDraft.win_points || 0),
        final_played_bonus: Number(ruleDraft.final_played_bonus || 0),
        final_won_bonus: Number(ruleDraft.final_won_bonus || 0),
      }
      const nextRule = await adminPlayersApi.updateInternalRule(payload)
      setRule(nextRule)
      setRuleDraft({
        win_points: nextRule.win_points,
        final_played_bonus: nextRule.final_played_bonus,
        final_won_bonus: nextRule.final_won_bonus,
      })
      setMessage('Regla de puntos internos actualizada.')
      await loadPlayers(filters)
      if (selectedPlayer) {
        await loadPlayerDetails(selectedPlayer.id)
      }
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos actualizar la regla de puntos.')
    }
  }

  const selectedTournament = useMemo(
    () => tournaments.find((item) => String(item.id) === String(payoutForm.tournament_id)) || null,
    [tournaments, payoutForm.tournament_id],
  )

  const payoutCategoryOptions = useMemo(
    () => (selectedTournament?.categories || []).map((category) => ({
      id: category.id,
      name: category.category?.display_name || category.category?.name || 'Categoría',
      level_code: category.category?.level_code,
    })),
    [selectedTournament],
  )

  const selectedPayoutCategory = useMemo(
    () => payoutCategoryOptions.find((item) => String(item.id) === String(payoutForm.tournament_category_id)) || null,
    [payoutCategoryOptions, payoutForm.tournament_category_id],
  )

  const payoutPositionOptions = getAllowedPositions(selectedPayoutCategory?.level_code)

  const handlePayoutFormChange = (field) => (event) => {
    const value = event.target.value
    setPayoutForm((prev) => ({
      ...prev,
      [field]: value,
      ...(field === 'tournament_id' ? { tournament_category_id: '' } : null),
      ...(field === 'tournament_category_id' ? { position: getAllowedPositions(
        payoutCategoryOptions.find((item) => String(item.id) === String(value))?.level_code,
      )[0]?.value || 'champion' } : null),
    }))
  }

  const handleCreatePayout = async (event) => {
    event.preventDefault()
    if (!selectedPlayer) return

    try {
      setError('')
      const payload = {
        tournament_id: Number(payoutForm.tournament_id),
        tournament_category_id: Number(payoutForm.tournament_category_id),
        position: payoutForm.position,
        amount_eur_cents: Number(payoutForm.amount_eur_cents),
        notes: payoutForm.notes || null,
      }
      await adminPlayersApi.createPrizePayout(selectedPlayer.id, payload)
      setMessage('Premio registrado.')
      await loadPlayerDetails(selectedPlayer.id)
      setPayoutForm((prev) => ({
        ...defaultPayoutForm,
        tournament_id: prev.tournament_id,
        tournament_category_id: prev.tournament_category_id,
      }))
      await loadPlayers(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos registrar el premio.')
    }
  }

  const handlePayoutEdit = (id, field, value) => {
    setPayoutEdits((prev) => ({
      ...prev,
      [id]: {
        ...(prev[id] || {}),
        [field]: value,
      },
    }))
  }

  const handleSavePayout = async (payout) => {
    const edit = payoutEdits[payout.id] || {}
    try {
      setError('')
      await adminPlayersApi.updatePrizePayout(payout.id, {
        position: edit.position || payout.position,
        amount_eur_cents: edit.amount_eur_cents !== undefined
          ? Number(edit.amount_eur_cents)
          : payout.amount_eur_cents,
        notes: edit.notes !== undefined ? (edit.notes || null) : payout.notes,
      })
      setMessage('Premio actualizado.')
      if (selectedPlayer) {
        await loadPlayerDetails(selectedPlayer.id)
      }
      await loadPlayers(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos actualizar el premio.')
    }
  }

  const handleDeletePayout = async (payout) => {
    const confirmed = window.confirm('¿Eliminar este premio?')
    if (!confirmed) return

    try {
      setError('')
      await adminPlayersApi.deletePrizePayout(payout.id)
      setMessage('Premio eliminado.')
      if (selectedPlayer) {
        await loadPlayerDetails(selectedPlayer.id)
      }
      await loadPlayers(filters)
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos eliminar el premio.')
    }
  }

  return (
    <section className="admin-page players-page">
      <div className="admin-page-header">
        <div>
          <h3>Jugadores</h3>
          <p>Ranking interno por categoría, ranking FEP y palmarés completo del jugador.</p>
        </div>
        <div className="admin-page-actions">
          <select value={filters.category_id} onChange={handleFilterChange('category_id')}>
            <option value="">Todas las categorías</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.display_name || category.name}
              </option>
            ))}
          </select>
          <input
            type="text"
            placeholder="Buscar por nombre o email"
            value={filters.search}
            onChange={handleFilterChange('search')}
          />
        </div>
      </div>

      {message && <div className="form-message success">{message}</div>}
      {error && <div className="form-message error">{error}</div>}

      <div className="admin-grid players-layout">
        <div className="panel-card players-rule-card">
          <div className="panel-header">
            <h4>Regla puntos internos</h4>
          </div>
          <div className="form-grid players-rule-grid">
            <label>
              Victoria
              <input
                type="number"
                min="0"
                value={ruleDraft.win_points}
                onChange={handleRuleDraftChange('win_points')}
              />
            </label>
            <label>
              Bono final jugada
              <input
                type="number"
                min="0"
                value={ruleDraft.final_played_bonus}
                onChange={handleRuleDraftChange('final_played_bonus')}
              />
            </label>
            <label>
              Bono final ganada
              <input
                type="number"
                min="0"
                value={ruleDraft.final_won_bonus}
                onChange={handleRuleDraftChange('final_won_bonus')}
              />
            </label>
            <div className="form-actions">
              <button className="secondary-button" type="button" onClick={handleSaveRule}>Guardar regla</button>
            </div>
          </div>
          <p className="muted players-rule-summary">
            Actual: {rule.win_points} por victoria, +{rule.final_played_bonus} final jugada, +{rule.final_won_bonus} final ganada.
          </p>
        </div>

        <div className="panel-card players-list-card">
          <div className="panel-header">
            <h4>Listado de jugadores</h4>
            <span className="tag muted">{players.length}</span>
          </div>

          {players.length === 0 ? (
            <div className="empty-state">No hay jugadores para estos filtros.</div>
          ) : (
            <div className="registration-list players-list">
              {players.map((player) => {
                const draftFep = rankingEdits[player.id]
                const fepValue = draftFep !== undefined ? draftFep : (player.ranking_fep_value ?? '')

                return (
                  <div key={player.id} className="registration-item player-item">
                    <div>
                      <strong>{player.name}</strong>
                      <span>{player.email}</span>
                      <span className="muted">Interno: {player.internal_rank ? `#${player.internal_rank}` : '—'} · {player.internal_points ?? '—'} pts</span>
                    </div>
                    <div>
                      <span>Ranking FEP</span>
                      <input
                        type="number"
                        min="1"
                        value={fepValue}
                        onChange={(event) => handleRankingEdit(player.id, event.target.value)}
                      />
                    </div>
                    <div>
                      <span>Palmarés</span>
                      <strong>{player.matches_won ?? 0} G / {player.matches_played ?? 0} PJ</strong>
                    </div>
                    <div>
                      <span>Premios EUR</span>
                      <strong>{formatMoney(player.prize_total_eur_cents)}</strong>
                    </div>
                    <div className="form-actions">
                      <button className="secondary-button" type="button" onClick={() => handleSaveFep(player)}>
                        Guardar FEP
                      </button>
                      <button className="ghost-button" type="button" onClick={() => openPlayerModal(player)}>
                        Ver jugador
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {selectedPlayer && (
        <div className="modal-backdrop" onClick={closePlayerModal}>
          <div className="modal-card players-modal" onClick={(event) => event.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h4>{selectedPlayer.name}</h4>
                <p className="muted">{selectedPlayer.email}</p>
              </div>
              <button className="ghost-button" type="button" onClick={closePlayerModal}>Cerrar</button>
            </div>

            {isLoadingModal || !palmares ? (
              <div className="empty-state">Cargando palmarés...</div>
            ) : (
              <div className="modal-body players-modal-body">
                <div className="players-palmares-grid">
                  <div className="kpi-card"><span>Torneos jugados</span><strong>{palmares.tournaments_played}</strong></div>
                  <div className="kpi-card"><span>Partidos ganados</span><strong>{palmares.matches_won}</strong></div>
                  <div className="kpi-card"><span>Puntos ganados</span><strong>{palmares.internal_points ?? 0}</strong></div>
                  <div className="kpi-card"><span>Finales jugadas</span><strong>{palmares.finals_played}</strong></div>
                  <div className="kpi-card"><span>Finales ganadas</span><strong>{palmares.finals_won}</strong></div>
                  <div className="kpi-card"><span>Premios EUR</span><strong>{formatMoney(palmares.prize_total_eur_cents)}</strong></div>
                </div>

                <section className="panel-card players-payouts-card">
                  <div className="panel-header">
                    <h5>Premios manuales (EUR)</h5>
                    <span className="tag muted">{payouts.length}</span>
                  </div>

                  <form className="form-grid players-payout-create" onSubmit={handleCreatePayout}>
                    <label>
                      Torneo
                      <select value={payoutForm.tournament_id} onChange={handlePayoutFormChange('tournament_id')}>
                        <option value="">Selecciona</option>
                        {tournaments.map((tournament) => (
                          <option key={tournament.id} value={tournament.id}>{tournament.name}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Categoría
                      <select
                        value={payoutForm.tournament_category_id}
                        onChange={handlePayoutFormChange('tournament_category_id')}
                      >
                        <option value="">Selecciona</option>
                        {payoutCategoryOptions.map((item) => (
                          <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Posición
                      <select value={payoutForm.position} onChange={handlePayoutFormChange('position')}>
                        {payoutPositionOptions.map((position) => (
                          <option key={position.value} value={position.value}>{position.label}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Monto EUR (centavos)
                      <input
                        type="number"
                        min="0"
                        value={payoutForm.amount_eur_cents}
                        onChange={handlePayoutFormChange('amount_eur_cents')}
                      />
                    </label>
                    <label className="field-span-2">
                      Nota
                      <input type="text" value={payoutForm.notes} onChange={handlePayoutFormChange('notes')} />
                    </label>
                    <div className="form-actions field-span-2">
                      <button className="secondary-button" type="submit">Registrar premio</button>
                    </div>
                  </form>

                  {payouts.length === 0 ? (
                    <div className="empty-state">No hay premios cargados para este jugador.</div>
                  ) : (
                    <div className="registration-list players-payout-list">
                      {payouts.map((item) => {
                        const edit = payoutEdits[item.id] || {}
                        const levelCode = ((tournaments
                          .find((tournament) => String(tournament.id) === String(item.tournament_id))
                          ?.categories || [])
                          .find((category) => String(category.id) === String(item.tournament_category_id))
                          ?.category?.level_code)
                        const options = getAllowedPositions(levelCode)

                        return (
                          <div key={item.id} className="registration-item players-payout-item">
                            <div>
                              <strong>{item.tournament_name}</strong>
                              <span>{item.category_name}</span>
                              <span className="muted">{formatDate(item.created_at)}</span>
                            </div>
                            <div>
                              <span>Posición</span>
                              <select
                                value={edit.position ?? item.position}
                                onChange={(event) => handlePayoutEdit(item.id, 'position', event.target.value)}
                              >
                                {options.map((position) => (
                                  <option key={position.value} value={position.value}>{position.label}</option>
                                ))}
                              </select>
                            </div>
                            <div>
                              <span>Monto (cent)</span>
                              <input
                                type="number"
                                min="0"
                                value={edit.amount_eur_cents ?? item.amount_eur_cents}
                                onChange={(event) => handlePayoutEdit(item.id, 'amount_eur_cents', event.target.value)}
                              />
                            </div>
                            <div>
                              <span>EUR</span>
                              <strong>{formatMoney(edit.amount_eur_cents ?? item.amount_eur_cents)}</strong>
                            </div>
                            <div className="field-span-2">
                              <span>Nota</span>
                              <input
                                type="text"
                                value={edit.notes ?? item.notes ?? ''}
                                onChange={(event) => handlePayoutEdit(item.id, 'notes', event.target.value)}
                              />
                            </div>
                            <div className="form-actions field-span-2">
                              <button className="secondary-button" type="button" onClick={() => handleSavePayout(item)}>
                                Guardar
                              </button>
                              <button className="ghost-button" type="button" onClick={() => handleDeletePayout(item)}>
                                Eliminar
                              </button>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  )}
                </section>
              </div>
            )}
          </div>
        </div>
      )}
    </section>
  )
}
