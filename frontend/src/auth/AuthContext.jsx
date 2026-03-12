import { useCallback, useEffect, useMemo, useState } from 'react'
import { authApi } from '../features/auth/api'
import { AuthContext } from './context'
import { readAuthToken, readAuthUser, writeAuthToken, writeAuthUser } from './storage'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => (readAuthToken() ? readAuthUser() : null))
  const [status, setStatus] = useState(() => (readAuthToken() ? 'loading' : 'unauthenticated'))
  const [error, setError] = useState(null)

  const clearAuth = useCallback(() => {
    writeAuthToken(null)
    writeAuthUser(null)
    setUser(null)
  }, [])

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
      setUser(data.user)
      setStatus('authenticated')
      setError(null)

      return data.user
    } catch (err) {
      clearAuth()
      setStatus('unauthenticated')
      setError(err?.message || 'No pudimos validar la sesión.')
      return null
    }
  }, [clearAuth])

  useEffect(() => {
    refresh()
  }, [refresh])

  useEffect(() => {
    const handleSessionInvalid = () => {
      clearAuth()
      setStatus('unauthenticated')
      setError('Tu sesión expiró. Inicia sesión nuevamente.')
    }

    window.addEventListener('auth:session-invalid', handleSessionInvalid)

    return () => {
      window.removeEventListener('auth:session-invalid', handleSessionInvalid)
    }
  }, [clearAuth])

  const login = useCallback(
    async (payload) => {
      setError(null)

      try {
        const data = await authApi.login(payload)

        if (!data?.token || !data?.user) {
          throw new Error('Invalid auth payload')
        }

        writeAuthToken(data.token)
        writeAuthUser(data.user)
        setUser(data.user)
        setStatus('authenticated')

        return data.user
      } catch (err) {
        clearAuth()
        setStatus('unauthenticated')
        setError(err?.message || 'No pudimos iniciar sesión.')
        throw err
      }
    },
    [clearAuth],
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
        clearAuth()
        setError(null)
        setStatus('unauthenticated')
      }
    },
    [clearAuth],
  )

  const value = useMemo(
    () => ({
      user,
      status,
      error,
      isAuthenticated: status === 'authenticated',
      isLoading: status === 'loading',
      login,
      register,
      logout,
      refresh,
    }),
    [user, status, error, login, register, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}