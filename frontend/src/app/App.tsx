import { lazy, Suspense } from 'react'
import { Route, Routes } from 'react-router'
import { AuthPage } from '../features/auth/AuthPage'
import { SessionProvider } from '../features/auth/SessionProvider'
import { RecoveryPage } from '../features/auth/RecoveryPage'
import { HomePage } from '../features/home/HomePage'
import { PublicCases } from '../features/cases/PublicCases'
import { CaseDetail } from '../features/discovery/CaseDetail'
import { NotFoundPage } from '../features/errors/NotFoundPage'
import { AppLayout } from './AppLayout'
import { LocaleProvider } from './LocaleProvider'
import { useLocale } from './locale'
import { Loading } from '../shared/ui/Loading'

const Dashboard = lazy(() => import('../features/auth/Dashboard').then(module => ({ default: module.Dashboard })))
const DonationRoutes = lazy(() => import('../features/donations/DonationPages').then(module => ({ default: module.DonationRoutes })))
const EmailVerified = lazy(() => import('../features/auth/Dashboard').then(module => ({ default: module.EmailVerified })))

function RouteLoading() {
  const { t } = useLocale()
  return <div className="container-shell py-10"><Loading label={t.loading} /></div>
}

export function App() {
  return <LocaleProvider><SessionProvider>
    <Suspense fallback={<RouteLoading />}>
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="cases" element={<PublicCases />} />
        <Route path="cases/:reference" element={<CaseDetail />} />
        <Route path="urgent" element={<PublicCases key="urgent" urgent />} />
        <Route path="donations/*" element={<DonationRoutes />} />
        <Route path="login" element={<AuthPage key="login" />} />
        <Route path="register" element={<AuthPage key="register" register />} />
        <Route path="dashboard/*" element={<Dashboard />} />
        <Route path="forgot-password" element={<RecoveryPage key="forgot" />} />
        <Route path="reset-password" element={<RecoveryPage key="reset" reset />} />
        <Route path="email-verified" element={<EmailVerified />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
    </Suspense>
  </SessionProvider></LocaleProvider>
}
