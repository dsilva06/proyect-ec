const INVITE_TOKEN_KEY = 'pending_invite_token'

export const inviteStorage = {
  getToken() {
    return localStorage.getItem(INVITE_TOKEN_KEY)
  },
  setToken(token) {
    if (token) {
      localStorage.setItem(INVITE_TOKEN_KEY, token)
    }
  },
  clearToken() {
    localStorage.removeItem(INVITE_TOKEN_KEY)
  },
}
