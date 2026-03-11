import { readAuthToken } from '../auth/storage'

const API_BASE_URL = (import.meta.env.VITE_API_URL || '').replace(/\/+$/, '')

if (!API_BASE_URL) {
  throw new Error('Missing VITE_API_URL')
}

function buildQuery(params) {
  const searchParams = new URLSearchParams()
  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    searchParams.append(key, value)
  })
  const query = searchParams.toString()
  return query ? `?${query}` : ''
}

const MESSAGE_TRANSLATIONS = {
  'invalid credentials': 'Credenciales inválidas.',
  'user is inactive': 'Tu usuario está inactivo. Contacta al administrador.',
  'too many requests': 'Demasiados intentos. Espera un momento e inténtalo de nuevo.',
  unauthorized: 'Tu sesión expiró. Inicia sesión nuevamente.',
  forbidden: 'No tienes permisos para realizar esta acción.',
  'resource not found': 'No se encontró el recurso solicitado.',
  'route not found': 'No se encontró el recurso solicitado.',
  'internal server error': 'Ocurrió un error interno. Intenta más tarde.',
  'validation error': 'Revisa los datos ingresados.',
}

const GENERIC_BACKEND_MESSAGES = new Set([
  'unauthorized',
  'forbidden',
  'resource not found',
  'route not found',
  'validation error',
  'internal server error',
  'too many requests',
])

function translateBackendMessage(rawMessage) {
  const normalized = rawMessage.trim().toLowerCase()
  return MESSAGE_TRANSLATIONS[normalized] || ''
}

const normalizeErrorMessage = (data, status) => {
  const rawMessage = typeof data?.message === 'string' ? data.message.trim() : ''
  const errors = data?.errors

  if (errors && typeof errors === 'object') {
    const firstField = Object.keys(errors)[0]
    if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
      return errors[firstField][0]
    }
  }

  if (/SQLSTATE|Unknown column|Base table or view not found|column not found/i.test(rawMessage)) {
    return 'La base de datos está desactualizada o incompleta. Ejecuta las migraciones e inténtalo de nuevo.'
  }

  const translatedMessage = rawMessage ? translateBackendMessage(rawMessage) : ''
  if (translatedMessage) return translatedMessage

  if (rawMessage && !GENERIC_BACKEND_MESSAGES.has(rawMessage.toLowerCase())) {
    return rawMessage
  }

  if (status === 401) return 'Tu sesión expiró. Inicia sesión nuevamente.'
  if (status === 403) return 'No tienes permisos para realizar esta acción.'
  if (status === 404) return 'No se encontró el recurso solicitado.'
  if (status === 422) return 'Revisa los datos ingresados.'
  if (status === 429) return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'
  if (status >= 500) return 'Ocurrió un error interno. Intenta más tarde.'

  return rawMessage || 'No pudimos completar la solicitud.'
}

async function request(path, { method = 'GET', body, headers = {}, skipAuth = false, params, ...options } = {}) {
  const token = readAuthToken()
  const query = params ? buildQuery(params) : ''
  let response
  try {
    response = await fetch(`${API_BASE_URL}${path}${query}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        ...(token && !skipAuth ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
      body: body && !(body instanceof FormData) ? JSON.stringify(body) : body,
      ...options,
    })
  } catch (err) {
    const error = new Error('No pudimos conectar con el servidor. Verifica tu conexión.')
    error.cause = err
    throw error
  }

  if (response.status === 204) {
    return null
  }

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    const rawMessage = typeof data?.message === 'string' ? data.message.trim() : ''
    const normalizedMessage = rawMessage.toLowerCase()
    const shouldInvalidateSession =
      response.status === 401 ||
      (response.status === 403 && normalizedMessage === 'user is inactive')

    if (typeof window !== 'undefined' && !skipAuth && shouldInvalidateSession) {
      window.dispatchEvent(
        new CustomEvent('auth:session-invalid', {
          detail: {
            status: response.status,
            message: rawMessage || null,
          },
        }),
      )
    }

    const friendlyMessage = normalizeErrorMessage(data, response.status)
    const error = new Error(friendlyMessage)
    error.status = response.status
    error.data = {
      ...(data || {}),
      message: friendlyMessage,
      raw_message: data?.message,
    }
    throw error
  }

  return data
}

export const httpClient = {
  request,
  get: (path, options) => request(path, { ...options, method: 'GET' }),
  post: (path, body, options) => request(path, { ...options, method: 'POST', body }),
  put: (path, body, options) => request(path, { ...options, method: 'PUT', body }),
  patch: (path, body, options) => request(path, { ...options, method: 'PATCH', body }),
  delete: (path, options) => request(path, { ...options, method: 'DELETE' }),
  baseUrl: API_BASE_URL,
}
