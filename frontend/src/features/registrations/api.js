import { httpClient } from '../../api/httpClient'

export const adminRegistrationsApi = {
  list: (params = {}) => httpClient.get('/api/admin/registrations', { params }),
  update: (id, payload) => httpClient.patch(`/api/admin/registrations/${id}`, payload),
}

export const playerRegistrationsApi = {
  list: () => httpClient.get('/api/player/registrations'),
  create: (payload) => httpClient.post('/api/player/registrations', payload),
}
