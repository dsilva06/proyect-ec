import { httpClient } from '../../api/httpClient'

export const adminPlayersApi = {
  list: (params = {}) => httpClient.get('/api/admin/players', { params }),
  updateRanking: (id, payload) => httpClient.patch(`/api/admin/players/${id}/ranking`, payload),
  updateFepRanking: (id, payload) => httpClient.patch(`/api/admin/players/${id}/ranking-fep`, payload),
  palmares: (id, params = {}) => httpClient.get(`/api/admin/players/${id}/palmares`, { params }),
  listPrizePayouts: (id, params = {}) => httpClient.get(`/api/admin/players/${id}/prize-payouts`, { params }),
  createPrizePayout: (id, payload) => httpClient.post(`/api/admin/players/${id}/prize-payouts`, payload),
  updatePrizePayout: (id, payload) => httpClient.patch(`/api/admin/player-prize-payouts/${id}`, payload),
  deletePrizePayout: (id) => httpClient.delete(`/api/admin/player-prize-payouts/${id}`),
  getInternalRule: () => httpClient.get('/api/admin/internal-ranking-rule'),
  updateInternalRule: (payload) => httpClient.patch('/api/admin/internal-ranking-rule', payload),
}

export const playerRankingApi = {
  get: () => httpClient.get('/api/player/ranking'),
  update: (payload) => httpClient.put('/api/player/ranking', payload),
}
