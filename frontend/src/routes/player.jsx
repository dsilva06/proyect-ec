import PlayerDashboard from '../pages/player/Dashboard'
import PlayerInvitations from '../pages/player/Invitations'
import PlayerRanking from '../pages/player/Ranking'
import PlayerRegistrations from '../pages/player/Registrations'
import PlayerPayments from '../pages/player/Payments'
import PlayerProfile from '../pages/player/Profile'
import Tournament from '../pages/public/Tournament'

export const playerRoutes = [
  { path: '/player', element: <PlayerDashboard /> },
  { path: '/player/profile', element: <PlayerProfile /> },
  { path: '/player/tournaments', element: <Tournament /> },
  { path: '/player/invitations', element: <PlayerInvitations /> },
  { path: '/player/ranking', element: <PlayerRanking /> },
  { path: '/player/registrations', element: <PlayerRegistrations /> },
  { path: '/player/payments', element: <PlayerPayments /> },
]
