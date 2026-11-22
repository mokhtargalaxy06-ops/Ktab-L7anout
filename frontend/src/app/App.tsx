import { Route, Routes } from 'react-router'
import { AuthPage } from '../features/auth/AuthPage'
import { SessionProvider } from '../features/auth/SessionProvider'
import { Dashboard, EmailVerified } from '../features/auth/Dashboard'
import { RecoveryPage } from '../features/auth/RecoveryPage'
import { HomePage } from '../features/home/HomePage'
import { PublicCases } from '../features/cases/PublicCases'
import { NotFoundPage } from '../features/errors/NotFoundPage'
import { AppLayout } from './AppLayout'
import { LocaleProvider } from './LocaleProvider'

export function App() {
  return <LocaleProvider><SessionProvider>
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="cases" element={<PublicCases />} />
        <Route path="login" element={<AuthPage key="login" />} />
        <Route path="register" element={<AuthPage key="register" register />} />
        <Route path="dashboard/*" element={<Dashboard />} />
        <Route path="forgot-password" element={<RecoveryPage key="forgot" />} />
        <Route path="reset-password" element={<RecoveryPage key="reset" reset />} />
        <Route path="email-verified" element={<EmailVerified />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  </SessionProvider></LocaleProvider>
}
