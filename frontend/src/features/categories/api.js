import { httpClient } from '../../api/httpClient'

export const adminCategoriesApi = {
  list: () => httpClient.get('/api/admin/categories'),
  create: (payload) => httpClient.post('/api/admin/categories', payload),
  update: (id, payload) => httpClient.put(`/api/admin/categories/${id}`, payload),
}
