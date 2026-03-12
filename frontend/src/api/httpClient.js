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
  'account is inactive': 'Tu usuario está inactivo. Contacta al administrador.',
  'user is inactive': 'Tu usuario está inactivo. Contacta al administrador.',
  'please verify your email before logging in.': 'Debes verificar tu correo antes de iniciar sesión.',
  'email is already verified.': 'Tu correo ya está verificado.',
  'verification email sent.': 'Te enviamos un nuevo correo de verificación.',
  'invalid verification link.': 'El enlace de verificación es inválido o expiró.',
  unauthorized: 'Tu sesión expiró. Inicia sesión nuevamente.',
  forbidden: 'No tienes permisos para realizar esta acción.',
  'resource not found': 'No se encontró el recurso solicitado.',
  'route not found': 'No se encontró el recurso solicitado.',
  'internal server error': 'Ocurrió un error interno. Intenta más tarde.',
  'validation error': 'Revisa los datos ingresados.',
  'too many requests': 'Demasiados intentos. Espera un momento e inténtalo de nuevo.',
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

function getFirstValidationError(errors) {
  if (!errors || typeof errors !== 'object') return ''

  const firstField = Object.keys(errors)[0]
  if (!firstField) return ''

  const firstValue = errors[firstField]
  if (Array.isArray(firstValue) && firstValue[0]) {
    return String(firstValue[0])
  }

  return ''
}

function normalizeErrorMessage(data, status) {
  const rawMessage = typeof data?.message === 'string' ? data.message.trim() : ''
  const validationMessage = getFirstValidationError(data?.errors)

  if (validationMessage) {
    return validationMessage
  }

  if (/SQLSTATE|Unknown column|Base table or view not found|column not found/i.test(rawMessage)) {
    return 'La base de datos está desactualizada o incompleta. Ejecuta las migraciones e inténtalo de nuevo.'
  }

  if (data?.error_code === 'EMAIL_NOT_VERIFIED') {
    return 'Debes verificar tu correo antes de iniciar sesión.'
  }

  if (data?.error_code === 'VERIFICATION_EMAIL_SEND_FAILED') {
    const supportEmail = data?.support_email ? ` Contacta soporte: ${data.support_email}.` : ''
    return `No se pudo completar el registro en este momento.${supportEmail}`
  }

  const translatedMessage = rawMessage ? translateBackendMessage(rawMessage) : ''
  if (translatedMessage) {
    return translatedMessage
  }

  if (rawMessage && !GENERIC_BACKEND_MESSAGES.has(rawMessage.toLowerCase())) {
    return rawMessage
  }

  if (status === 401) return 'Tu sesión expiró. Inicia sesión nuevamente.'
  if (status === 403) return 'No tienes permisos para realizar esta acción.'
  if (status === 404) return 'No se encontró el recurso solicitado.'
  if (status === 422) return 'Revisa los datos ingresados.'
  if (status === 429) return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'
  if (status >= 500) return 'Ocurrió un error interno. Intenta más tarde.'

  return 'No pudimos completar la solicitud.'
}

function shouldInvalidateSession(responseStatus, data, skipAuth) {
  if (skipAuth) return false

  if (responseStatus === 401) return true

  if (responseStatus !== 403) return false

  const rawMessage = typeof data?.message === 'string' ? data.message.trim().toLowerCase() : ''
  const errorCode = typeof data?.error_code === 'string' ? data.error_code.trim().toUpperCase() : ''

  if (errorCode === 'EMAIL_NOT_VERIFIED') return false
  if (rawMessage === 'please verify your email before logging in.') return false

  if (rawMessage === 'user is inactive' || rawMessage === 'account is inactive') {
    return true
  }

  return false
}

async function parseResponseBody(response) {
  if (response.status === 204) {
    return null
  }

  const contentType = response.headers.get('content-type') || ''

  if (contentType.includes('application/json')) {
    return response.json().catch(() => null)
  }

  return response.text().catch(() => null)
}

async function request(
  path,
  { method = 'GET', body, headers = {}, skipAuth = false, params, ...options } = {},
) {
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

  const parsedBody = await parseResponseBody(response)
  const data = parsedBody && typeof parsedBody === 'object' ? parsedBody : null

  if (!response.ok) {
    const invalidateSession = shouldInvalidateSession(response.status, data, skipAuth)

    if (typeof window !== 'undefined' && invalidateSession) {
      window.dispatchEvent(
        new CustomEvent('auth:session-invalid', {
          detail: {
            status: response.status,
            message: typeof data?.message === 'string' ? data.message : null,
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
      raw_message: typeof data?.message === 'string' ? data.message : null,
    }
    throw error
  }

  if (data !== null) {
    return data
  }

  return parsedBody
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