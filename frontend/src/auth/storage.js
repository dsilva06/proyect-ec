export const AUTH_TOKEN_STORAGE_KEY = 'auth_token'
export const AUTH_USER_STORAGE_KEY = 'padel-auth-user'
export const AUTH_VERIFICATION_CONTEXT_STORAGE_KEY = 'padel-auth-verification-context'

export function readAuthToken() {
  try {
    return localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)
  } catch {
    return null
  }
}

export function writeAuthToken(token) {
  try {
    if (token) {
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token)
    } else {
      localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY)
    }
  } catch {
    // ignore storage errors
  }
}

export function readAuthUser() {
  try {
    const stored = localStorage.getItem(AUTH_USER_STORAGE_KEY)
    return stored ? JSON.parse(stored) : null
  } catch {
    return null
  }
}

export function writeAuthUser(user) {
  try {
    if (user) {
      localStorage.setItem(AUTH_USER_STORAGE_KEY, JSON.stringify(user))
    } else {
      localStorage.removeItem(AUTH_USER_STORAGE_KEY)
    }
  } catch {
    // ignore storage errors
  }
}

export function readVerificationContext() {
  try {
    return sessionStorage.getItem(AUTH_VERIFICATION_CONTEXT_STORAGE_KEY)
  } catch {
    return null
  }
}

export function writeVerificationContext(context) {
  try {
    if (context) {
      sessionStorage.setItem(AUTH_VERIFICATION_CONTEXT_STORAGE_KEY, context)
    } else {
      sessionStorage.removeItem(AUTH_VERIFICATION_CONTEXT_STORAGE_KEY)
    }
  } catch {
    // ignore storage errors
  }
}
