export function getHomeRouteForRole(user) {
  if (!user) {
    return '/'
  }
  if (user.role === 'admin') {
    return '/admin'
  }
  return '/player'
}
