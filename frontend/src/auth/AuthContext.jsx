import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { authApi } from '../features/auth/api'
import { AuthContext } from './context'
import {
  AUTH_TOKEN_STORAGE_KEY,
  readAuthLastActivityAt,
  readAuthToken,
  readAuthUser,
  writeAuthLastActivityAt,
  writeAuthToken,
  writeAuthUser,
} from './storage'

const IDLE_TIMEOUT_MINUTES = Math.max(
  1,
  Number.parseInt(import.meta.env.VITE_AUTH_IDLE_TIMEOUT_MINUTES || '30', 10) || 30,
)
const IDLE_TIMEOUT_MS = IDLE_TIMEOUT_MINUTES * 60 * 1000
const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'touchstart']

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => (readAuthToken() ? readAuthUser() : null))
  const [status, setStatus] = useState(() => (readAuthToken() ? 'loading' : 'unauthenticated'))
  const [error, setError] = useState(null)
  const idleTimerRef = useRef(null)

  const clearIdleTimer = useCallback(() => {
    if (idleTimerRef.current) {
      window.clearTimeout(idleTimerRef.current)
      idleTimerRef.current = null
    }
  }, [])

  const clearAuth = useCallback(() => {
    writeAuthToken(null)
    writeAuthUser(null)
    writeAuthLastActivityAt(null)
    setUser(null)
  }, [])

  const finishIdleLogout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // ignore API logout failure; local auth must still be cleared
    } finally {
      clearIdleTimer()
      clearAuth()
      setError('Tu sesión se cerró por inactividad. Inicia sesión nuevamente.')
      setStatus('unauthenticated')
    }
  }, [clearAuth, clearIdleTimer])

  const scheduleIdleLogout = useCallback(() => {
    clearIdleTimer()

    if (status !== 'authenticated') {
      return
    }

    const lastActivityAt = readAuthLastActivityAt() ?? Date.now()
    const remainingMs = IDLE_TIMEOUT_MS - (Date.now() - lastActivityAt)

    if (remainingMs <= 0) {
      void finishIdleLogout()
      return
    }

    idleTimerRef.current = window.setTimeout(() => {
      void finishIdleLogout()
    }, remainingMs)
  }, [clearIdleTimer, finishIdleLogout, status])

  const registerActivity = useCallback(() => {
    if (status !== 'authenticated') {
      return
    }

    writeAuthLastActivityAt(Date.now())
    scheduleIdleLogout()
  }, [scheduleIdleLogout, status])

  const refresh = useCallback(async () => {
    const token = readAuthToken()

    if (!token) {
      clearAuth()
      setStatus('unauthenticated')
      setError(null)
      return null
    }

    setStatus('loading')

    try {
      const data = await authApi.me()

      if (!data?.user) {
        throw new Error('Invalid auth payload')
      }

      writeAuthUser(data.user)
      writeAuthLastActivityAt(Date.now())
      setUser(data.user)
      setStatus('authenticated')
      setError(null)

      return data.user
    } catch (err) {
      clearAuth()
      clearIdleTimer()
      setStatus('unauthenticated')
      setError(err?.message || 'No pudimos validar la sesión.')
      return null
    }
  }, [clearAuth, clearIdleTimer])

  useEffect(() => {
    refresh()
  }, [refresh])

  useEffect(() => {
    const handleSessionInvalid = () => {
      clearIdleTimer()
      clearAuth()
      setStatus('unauthenticated')
      setError('Tu sesión expiró. Inicia sesión nuevamente.')
    }

    window.addEventListener('auth:session-invalid', handleSessionInvalid)

    return () => {
      window.removeEventListener('auth:session-invalid', handleSessionInvalid)
    }
  }, [clearAuth, clearIdleTimer])

  useEffect(() => {
    if (status !== 'authenticated') {
      clearIdleTimer()
      return undefined
    }

    if (!readAuthLastActivityAt()) {
      writeAuthLastActivityAt(Date.now())
    }

    scheduleIdleLogout()

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        registerActivity()
      }
    }

    const handleStorage = (event) => {
      if (event.key === AUTH_TOKEN_STORAGE_KEY && !event.newValue) {
        clearIdleTimer()
        clearAuth()
        setError(null)
        setStatus('unauthenticated')
        return
      }

      scheduleIdleLogout()
    }

    ACTIVITY_EVENTS.forEach((eventName) => {
      window.addEventListener(eventName, registerActivity, { passive: true })
    })
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('storage', handleStorage)

    return () => {
      ACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, registerActivity)
      })
      document.removeEventListener('visibilitychange', handleVisibilityChange)
      window.removeEventListener('storage', handleStorage)
      clearIdleTimer()
    }
  }, [clearAuth, clearIdleTimer, registerActivity, scheduleIdleLogout, status])

  const establishSession = useCallback((payload) => {
    if (!payload?.token || !payload?.user) {
      throw new Error('Invalid auth payload')
    }

    writeAuthToken(payload.token)
    writeAuthUser(payload.user)
    writeAuthLastActivityAt(Date.now())
    setUser(payload.user)
    setStatus('authenticated')
    setError(null)

    return payload.user
  }, [])

  const login = useCallback(
    async (payload) => {
      setError(null)

      try {
        const data = await authApi.login(payload)
        establishSession(data)

        return data.user
      } catch (err) {
        clearAuth()
        setStatus('unauthenticated')
        setError(err?.message || 'No pudimos iniciar sesión.')
        throw err
      }
    },
    [clearAuth, establishSession],
  )

  const register = useCallback(
    async (payload) => {
      setError(null)

      try {
        const data = await authApi.register(payload)
        clearAuth()
        setStatus('unauthenticated')
        return data
      } catch (err) {
        clearAuth()
        setStatus('unauthenticated')
        setError(err?.message || 'No pudimos completar el registro.')
        throw err
      }
    },
    [clearAuth],
  )

  const logout = useCallback(
    async () => {
      try {
        await authApi.logout()
      } catch {
        // ignore API logout failure; local auth must still be cleared
      } finally {
        clearIdleTimer()
        clearAuth()
        setError(null)
        setStatus('unauthenticated')
      }
    },
    [clearAuth, clearIdleTimer],
  )

  const value = useMemo(
    () => ({
      user,
      status,
      error,
      isAuthenticated: status === 'authenticated',
      isLoading: status === 'loading',
      login,
      establishSession,
      register,
      logout,
      refresh,
    }),
    [user, status, error, login, establishSession, register, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
