import { Navigate, Outlet } from 'react-router-dom'
import { useMe } from '@/lib/query/hooks'

/** Guard sesi: redirect ke /login bila GET /api/auth/me 401. */
export function RequireAuth() {
  const { data, isPending } = useMe()

  if (isPending) return null
  if (!data) return <Navigate to="/login" replace />

  return <Outlet />
}
