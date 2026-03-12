const INVITE_TOKEN_STORAGE_KEY = 'padel-invite-token'

export const inviteStorage = {
  getToken() {
    try {
      return localStorage.getItem(INVITE_TOKEN_STORAGE_KEY)
    } catch {
      return null
    }
  },

  setToken(token) {
    try {
      if (token) {
        localStorage.setItem(INVITE_TOKEN_STORAGE_KEY, token)
      } else {
        localStorage.removeItem(INVITE_TOKEN_STORAGE_KEY)
      }
    } catch {
      // ignore storage errors
    }
  },

  clearToken() {
    try {
      localStorage.removeItem(INVITE_TOKEN_STORAGE_KEY)
    } catch {
      // ignore storage errors
    }
  },
}