import { httpClient } from '../../api/httpClient'

export const adminPlayersApi = {
  list: (params = {}) => httpClient.get('/api/admin/players', { params }),
  updateRanking: (id, payload) => httpClient.patch(`/api/admin/players/${id}/ranking`, payload),
}

export const playerRankingApi = {
  get: () => httpClient.get('/api/player/ranking'),
  update: (payload) => httpClient.put('/api/player/ranking', payload),
}
