import { httpClient } from '../../api/httpClient'

export const adminWildcardsApi = {
  list: (params = {}) => httpClient.get('/api/admin/wildcards', { params }),
  create: (payload) => httpClient.post('/api/admin/wildcards', payload),
  update: (id, payload) => httpClient.patch(`/api/admin/wildcards/${id}`, payload),
  remove: (id) => httpClient.delete(`/api/admin/wildcards/${id}`),
}

export const playerWildcardsApi = {
  show: (token) => httpClient.get(`/api/player/wildcards/${token}`),
  claim: (token, payload) => httpClient.post(`/api/player/wildcards/${token}/claim`, payload),
}

export const publicWildcardsApi = {
  show: (token) => httpClient.get(`/api/public/wildcards/${token}`, { skipAuth: true }),
}
