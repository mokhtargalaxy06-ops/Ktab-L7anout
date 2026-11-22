import { Route, Routes } from 'react-router'
import { AuthPage } from '../features/auth/AuthPage'
import { SessionProvider } from '../features/auth/SessionProvider'
import { HomePage } from '../features/home/HomePage'
import { NotFoundPage } from '../features/errors/NotFoundPage'
import { AppLayout } from './AppLayout'
import { LocaleProvider } from './LocaleProvider'

export function App() {
  return <LocaleProvider><SessionProvider>
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="login" element={<AuthPage key="login" />} />
        <Route path="register" element={<AuthPage key="register" register />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  </SessionProvider></LocaleProvider>
}
