import { httpClient } from '../../api/httpClient'

export const playerOpenEntriesApi = {
  list: () => httpClient.get('/api/player/open-entries'),
  show: (id) => httpClient.get(`/api/player/open-entries/${id}`),
  create: (payload) => httpClient.post('/api/player/registrations', payload),
  pay: (id) => httpClient.post(`/api/player/open-entries/${id}/pay`),
}

export const adminOpenEntriesApi = {
  list: (params = {}) => httpClient.get('/api/admin/open-entries', { params }),
  show: (id) => httpClient.get(`/api/admin/open-entries/${id}`),
  assignCategory: (id, payload) =>
    httpClient.post(`/api/admin/open-entries/${id}/assign-category`, payload),
}
