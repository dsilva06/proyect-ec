import { httpClient } from '../../api/httpClient'

export const publicTeamInvitesApi = {
  get: (token) => httpClient.get(`/api/team-invites/${token}`, { skipAuth: true }),
}

export const playerTeamInvitesApi = {
  list: () => httpClient.get('/api/player/team-invites'),
  claim: (token) => httpClient.post('/api/player/team-invites/claim', { token }),
  accept: (id) => httpClient.post(`/api/player/team-invites/${id}/accept`),
}
