import Dashboard from '../pages/admin/Dashboard'
import Registrations from '../pages/admin/Registrations'
import Payments from '../pages/admin/Payments'
import Draws from '../pages/admin/Draws'
import Matches from '../pages/admin/Matches'
import TournamentSettings from '../pages/admin/TournamentSettings'
import Leads from '../pages/admin/Leads'
import Loyalty from '../pages/admin/Loyalty'

export const adminRoutes = [
  { path: '/admin', element: <Dashboard /> },
  { path: '/admin/registrations', element: <Registrations /> },
  { path: '/admin/payments', element: <Payments /> },
  { path: '/admin/draws', element: <Draws /> },
  { path: '/admin/matches', element: <Matches /> },
  { path: '/admin/settings', element: <TournamentSettings /> },
  { path: '/admin/leads', element: <Leads /> },
  { path: '/admin/loyalty', element: <Loyalty /> },
]
