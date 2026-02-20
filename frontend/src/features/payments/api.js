import { httpClient } from '../../api/httpClient'

export const adminPaymentsApi = {
  list: (params = {}) => httpClient.get('/api/admin/payments', { params }),
  update: (id, payload) => httpClient.patch(`/api/admin/payments/${id}`, payload),
}

export const playerPaymentsApi = {
  list: () => httpClient.get('/api/player/payments'),
}
