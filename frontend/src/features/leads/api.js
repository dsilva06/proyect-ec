import { httpClient } from '../../api/httpClient'

export const publicLeadsApi = {
  create: (payload) => httpClient.post('/api/public/leads', payload, { skipAuth: true }),
}

export const adminLeadsApi = {
  list: (params = {}) => httpClient.get('/api/admin/leads', { params }),
  update: (id, payload) => httpClient.patch(`/api/admin/leads/${id}`, payload),
}
