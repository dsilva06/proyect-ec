export async function apiClient(path, options = {}) {
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8001'
  const token = localStorage.getItem('auth_token')
  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...options.headers,
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers,
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    const message = payload?.message || 'Request failed.'
    throw new Error(message)
  }

  return payload
}
