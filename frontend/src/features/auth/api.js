import { httpClient } from '../../api/httpClient'

export const authApi = {
  login: (payload) => httpClient.post('/api/auth/login', payload, { skipAuth: true }),
  register: (payload) => httpClient.post('/api/auth/register', payload, { skipAuth: true }),
  logout: () => httpClient.post('/api/auth/logout'),
  me: () => httpClient.get('/api/auth/me'),
}
