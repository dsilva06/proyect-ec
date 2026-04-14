import { Navigate } from 'react-router-dom'
import Dashboard from '../pages/admin/Dashboard'
import Registrations from '../pages/admin/Registrations'
import OpenEntries from '../pages/admin/OpenEntries'
import Payments from '../pages/admin/Payments'
import Draws from '../pages/admin/Draws'
import Matches from '../pages/admin/Matches'
import TournamentSettings from '../pages/admin/TournamentSettings'
import Rankings from '../pages/admin/Rankings'
import Leads from '../pages/admin/Leads'
import Wildcards from '../pages/admin/Wildcards'

export const adminRoutes = [
  { path: '/admin', element: <Dashboard /> },
  { path: '/admin/registrations', element: <Registrations /> },
  { path: '/admin/open-entries', element: <OpenEntries /> },
  { path: '/admin/payments', element: <Payments /> },
  { path: '/admin/draws', element: <Draws /> },
  { path: '/admin/matches', element: <Matches /> },
  { path: '/admin/settings', element: <TournamentSettings /> },
  { path: '/admin/players', element: <Rankings /> },
  { path: '/admin/rankings', element: <Navigate to="/admin/players" replace /> },
  { path: '/admin/leads', element: <Leads /> },
  { path: '/admin/wildcards', element: <Wildcards /> },
]
