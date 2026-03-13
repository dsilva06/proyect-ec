import Home from '../pages/public/Home'
import InviteRedirect from '../pages/public/InviteRedirect'
import Login from '../pages/public/Login'
import Register from '../pages/public/Register'
import Tournament from '../pages/public/Tournament'
import VerifyEmailPending from '../pages/public/VerifyEmailPending'
import WildcardInvite from '../pages/public/WildcardInvite'
import PublicOnlyRoute from '../components/shared/PublicOnlyRoute'

export const publicRoutes = [
  { path: '/', element: <PublicOnlyRoute><Home /></PublicOnlyRoute> },
  { path: '/invite/:token', element: <InviteRedirect /> },
  { path: '/login', element: <PublicOnlyRoute><Login /></PublicOnlyRoute> },
  { path: '/register', element: <PublicOnlyRoute><Register /></PublicOnlyRoute> },
  { path: '/verify-email', element: <PublicOnlyRoute><VerifyEmailPending /></PublicOnlyRoute> },
  { path: '/tournament', element: <Tournament /> },
  { path: '/wildcard/:token', element: <WildcardInvite /> },
]
