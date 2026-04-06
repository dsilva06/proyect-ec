import { httpClient } from '../../api/httpClient'

export const playerProfileApi = {
  get: () => httpClient.get('/api/player/me'),
}
