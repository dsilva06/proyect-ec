import { httpClient } from '../../api/httpClient'

export const statusesApi = {
  list: (params = {}) => httpClient.get('/api/statuses', { params, skipAuth: true }),
}
