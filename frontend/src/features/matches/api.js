import { httpClient } from '../../api/httpClient'

export const adminMatchesApi = {
  list: (params = {}) => httpClient.get('/api/admin/matches', { params }),
  create: (payload) => httpClient.post('/api/admin/matches', payload),
  update: (id, payload) => httpClient.patch(`/api/admin/matches/${id}`, payload),
  remove: (id) => httpClient.delete(`/api/admin/matches/${id}`),
  delay: (id) => httpClient.post(`/api/admin/matches/${id}/delay`, {}),
}
