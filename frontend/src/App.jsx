import { Routes, Route, Navigate } from 'react-router-dom'
import PublicLayout from './layouts/PublicLayout'
import AdminLayout from './layouts/AdminLayout'
import PlayerLayout from './layouts/PlayerLayout'
import AdminRoute from './components/shared/AdminRoute'
import PlayerRoute from './components/shared/PlayerRoute'
import { publicRoutes } from './routes/public'
import { adminRoutes } from './routes/admin'
import { playerRoutes } from './routes/player'

function App() {
  return (
    <Routes>
      <Route element={<PublicLayout />}>
        {publicRoutes.map((route) => (
          <Route key={route.path} path={route.path} element={route.element} />
        ))}
      </Route>

      <Route element={<AdminRoute />}>
        <Route element={<AdminLayout />}>
          {adminRoutes.map((route) => (
            <Route key={route.path} path={route.path} element={route.element} />
          ))}
        </Route>
      </Route>

      <Route element={<PlayerRoute />}>
        <Route element={<PlayerLayout />}>
          {playerRoutes.map((route) => (
            <Route key={route.path} path={route.path} element={route.element} />
          ))}
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
