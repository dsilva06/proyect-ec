import { httpClient } from '../../api/httpClient'

export const playerTeamsApi = {
  create: (payload) => httpClient.post('/api/player/teams', payload),
}
