import { httpClient } from '../../api/httpClient'

export const authApi = {
  register: (payload) => httpClient.post('/api/auth/register', payload, { skipAuth: true }),
  login: (payload) => httpClient.post('/api/auth/login', payload, { skipAuth: true }),
  me: () => httpClient.get('/api/auth/me'),
  logout: () => httpClient.post('/api/auth/logout'),
  resendVerification: (payload) => httpClient.post('/api/auth/email/resend', payload, { skipAuth: true }),
}