import { httpClient } from '../../api/httpClient'

export const adminBracketsApi = {
  list: (params = {}) => httpClient.get('/api/admin/brackets', { params }),
  create: (payload) => httpClient.post('/api/admin/brackets', payload),
  update: (id, payload) => httpClient.patch(`/api/admin/brackets/${id}`, payload),
  generate: (id, payload = {}) => httpClient.post(`/api/admin/brackets/${id}/generate`, payload),
  remove: (id) => httpClient.delete(`/api/admin/brackets/${id}`),
}

export const adminBracketSlotsApi = {
  create: (payload) => httpClient.post('/api/admin/bracket-slots', payload),
  update: (id, payload) => httpClient.patch(`/api/admin/bracket-slots/${id}`, payload),
}

export const playerBracketsApi = {
  list: (params = {}) => httpClient.get('/api/player/brackets', { params }),
}
