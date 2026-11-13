import { createBrowserRouter } from 'react-router-dom'
import { RequireAuth } from '@/routes/RequireAuth'
import { AnalysesNewPage } from '@/routes/pages/AnalysesNewPage'
import { AnalysesResultPage } from '@/routes/pages/AnalysesResultPage'
import { DashboardPage } from '@/routes/pages/DashboardPage'
import { LoginPage } from '@/routes/pages/LoginPage'
import { RegisterPage } from '@/routes/pages/RegisterPage'
import { RpsDetailPage } from '@/routes/pages/RpsDetailPage'

/**
 * Route sesuai docs/FRONTEND.md section 2 (RPS upload disatukan di Dashboard).
 */
export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/register', element: <RegisterPage /> },
  {
    element: <RequireAuth />,
    children: [
      { path: '/', element: <DashboardPage /> },
      { path: '/dashboard', element: <DashboardPage /> },
      { path: '/rps/:id', element: <RpsDetailPage /> },
      { path: '/analyses/new', element: <AnalysesNewPage /> },
      { path: '/analyses/:id', element: <AnalysesResultPage /> },
    ],
  },
])
