import Home from '../pages/public/Home'
import InviteRedirect from '../pages/public/InviteRedirect'
import Login from '../pages/public/Login'
import Register from '../pages/public/Register'
import Tournament from '../pages/public/Tournament'
import WildcardInvite from '../pages/public/WildcardInvite'

export const publicRoutes = [
  { path: '/', element: <Home /> },
  { path: '/invite/:token', element: <InviteRedirect /> },
  { path: '/login', element: <Login /> },
  { path: '/register', element: <Register /> },
  { path: '/tournament', element: <Tournament /> },
  { path: '/wildcard/:token', element: <WildcardInvite /> },
]
