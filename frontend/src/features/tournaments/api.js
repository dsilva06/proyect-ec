import { httpClient } from '../../api/httpClient'

export const adminTournamentsApi = {
  list: (params = {}) => httpClient.get('/api/admin/tournaments', { params }),
  create: (payload) => httpClient.post('/api/admin/tournaments', payload),
  update: (id, payload) => httpClient.put(`/api/admin/tournaments/${id}`, payload),
  updateStatus: (id, statusId) =>
    httpClient.patch(`/api/admin/tournaments/${id}/status`, { status_id: statusId }),
  remove: (id) => httpClient.delete(`/api/admin/tournaments/${id}`),
  addCategory: (id, payload) => httpClient.post(`/api/admin/tournaments/${id}/categories`, payload),
}

export const adminTournamentCategoriesApi = {
  update: (id, payload) => httpClient.patch(`/api/admin/tournament-categories/${id}`, payload),
  remove: (id) => httpClient.delete(`/api/admin/tournament-categories/${id}`),
}

export const playerTournamentsApi = {
  list: () => httpClient.get('/api/player/tournaments'),
  get: (id) => httpClient.get(`/api/player/tournaments/${id}`),
}

export const publicTournamentsApi = {
  list: () => httpClient.get('/api/public/tournaments', { skipAuth: true }),
}
