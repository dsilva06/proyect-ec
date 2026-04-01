import { httpClient } from '../../api/httpClient'

function normalizeVerificationUrl(rawUrl) {
  if (!rawUrl || typeof rawUrl !== 'string') return ''
  return rawUrl.trim()
}

async function verifyEmailByUrl(url) {
  const normalizedUrl = normalizeVerificationUrl(url)

  if (!normalizedUrl) {
    throw new Error('Falta la URL de verificación.')
  }

  if (!/^https?:\/\//i.test(normalizedUrl)) {
    throw new Error('Formato de URL de verificación inválido.')
  }

  let response

  try {
    response = await fetch(normalizedUrl, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
      },
    })
  } catch (error) {
    const err = new Error('No pudimos conectar con el servidor para verificar el correo.')
    err.cause = error
    throw err
  }

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    const message = data?.message || 'No pudimos verificar el correo.'
    const err = new Error(message)
    err.status = response.status
    err.data = data
    throw err
  }

  return data
}

export const authApi = {
  register: (payload) => httpClient.post('/api/auth/register', payload, { skipAuth: true }),
  login: (payload) => httpClient.post('/api/auth/login', payload, { skipAuth: true }),
  me: () => httpClient.get('/api/auth/me'),
  logout: () => httpClient.post('/api/auth/logout'),
  resendVerification: (payload) => httpClient.post('/api/auth/email/resend', payload, { skipAuth: true }),
  verifyEmailByUrl,
}
