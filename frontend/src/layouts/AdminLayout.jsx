import { Outlet } from 'react-router-dom'

export default function AdminLayout() {
  return (
    <div style={{ padding: '32px' }}>
      <h2>Admin</h2>
      <Outlet />
    </div>
  )
}
