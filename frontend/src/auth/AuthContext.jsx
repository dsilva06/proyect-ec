import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { authApi } from '../api/apiClient'

const AuthContext = createContext(null)
const STORAGE_KEY = 'padel-auth-user'
const TOKEN_KEY = 'auth_token'

function getStoredUser() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored ? JSON.parse(stored) : null
  } catch (error) {
    return null
  }
}

function setStoredUser(user) {
  try {
    if (user) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(user))
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  } catch (error) {
    // ignore storage errors
  }
}

function setStoredToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(getStoredUser())
  const [status, setStatus] = useState('loading')
  const [error, setError] = useState(null)

  const refresh = useCallback(async () => {
    const token = localStorage.getItem(TOKEN_KEY)

    if (!token) {
      setStoredUser(null)
      setUser(null)
      setStatus('unauthenticated')
      return null
    }

    try {
      const data = await authApi.me()
      setStoredUser(data.user)
      setUser(data.user)
      setStatus('authenticated')
      return data.user
    } catch (err) {
      setStoredUser(null)
      setStoredToken(null)
      setUser(null)
      setStatus('unauthenticated')
      throw err
    }
  }, [])

  useEffect(() => {
    refresh().catch(() => {
      setStatus('unauthenticated')
    })
  }, [refresh])

  const login = useCallback(async (payload) => {
    setError(null)
    const data = await authApi.login(payload)
    setStoredToken(data.token)
    setStoredUser(data.user)
    setUser(data.user)
    setStatus('authenticated')
    return data.user
  }, [])

  const register = useCallback(async (payload) => {
    setError(null)
    const data = await authApi.register(payload)
    setStoredToken(data.token)
    setStoredUser(data.user)
    setUser(data.user)
    setStatus('authenticated')
    return data.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch (err) {
      // ignore logout errors
    } finally {
      setStoredToken(null)
      setStoredUser(null)
      setUser(null)
      setStatus('unauthenticated')
    }
  }, [])

  const value = useMemo(() => ({
    user,
    status,
    error,
    login,
    register,
    logout,
    refresh,
  }), [user, status, error, login, register, logout, refresh])

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
