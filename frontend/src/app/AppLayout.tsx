import { Globe2, HeartHandshake } from 'lucide-react'
import { isAxiosError } from 'axios'
import { useState } from 'react'
import { Link, NavLink, Outlet } from 'react-router'
import { useSession } from '../features/auth/session'
import { caseCopy } from '../features/cases/copy'
import { discoveryCopy } from '../features/discovery/copy'
import copy, { accountDashboard } from '../features/auth/accountCopy'
import { api, getCsrfCookie } from '../shared/api/client'
import { Button, ButtonLink } from '../shared/ui/Button'
import { useLocale } from './locale'

function Brand() {
  return <Link to="/" className="inline-flex min-h-11 items-center gap-2.5 text-start font-bold tracking-tight" dir="ltr">
    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-forest text-white">
      <HeartHandshake aria-hidden="true" size={22} strokeWidth={1.8} />
    </span>
    <span>Morocco <span className="text-forest">Debt Relief</span></span>
  </Link>
}

export function AppLayout() {
  const { locale, setLocale, t } = useLocale()
  const { user, setUser } = useSession()
  const [signingOut, setSigningOut] = useState(false)
  const [logoutError, setLogoutError] = useState(false)

  async function logout() {
    setSigningOut(true)
    setLogoutError(false)
    try {
      await getCsrfCookie()
      await api.post('/logout')
      setUser(null)
    } catch (error) {
      if (isAxiosError(error) && error.response?.status === 401) {
        setUser(null)
      } else {
        setLogoutError(true)
      }
    } finally {
      setSigningOut(false)
    }
  }

  return <div className="flex min-h-screen flex-col">
    <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-3 focus:top-3 focus:z-50 focus:rounded focus:bg-white focus:p-3">{t.skip}</a>
    <header className="border-b border-line bg-white">
      <div className="container-shell flex flex-wrap items-center justify-between gap-x-5 gap-y-1 py-3 lg:grid lg:grid-cols-[1fr_auto_1fr]">
        <Brand />
        <nav aria-label={t.menu} className="order-3 flex w-full items-center gap-6 pt-1 text-sm font-semibold text-muted lg:order-0 lg:w-auto lg:pt-0">
          <NavLink end to="/" className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{t.home}</NavLink>
          <NavLink to="/cases" className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{caseCopy[locale].publicLink}</NavLink>
          <NavLink to="/urgent" className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{discoveryCopy[locale].urgentTitle}</NavLink>
          <Link to="/#approche" className="inline-flex min-h-11 items-center hover:text-forest">{t.approach}</Link>
        </nav>
        <div className="flex flex-wrap items-center gap-2 sm:gap-3 lg:justify-end">
          <button type="button" aria-label={t.language} onClick={() => setLocale(locale === 'fr' ? 'ar' : 'fr')}
            className="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm font-semibold text-forest hover:bg-mint">
            <Globe2 aria-hidden="true" size={17} /><span lang={locale === 'fr' ? 'ar' : 'fr'}>{locale === 'fr' ? 'العربية' : 'Français'}</span>
          </button>
          {user
            ? <><ButtonLink to={accountDashboard(user.roles)} variant="secondary">{copy[locale].dashboard}</ButtonLink>
              <Button disabled={signingOut} onClick={() => void logout()}>{signingOut ? t.loading : t.logout}</Button></>
            : <ButtonLink to="/login" variant="secondary">{t.login}</ButtonLink>}
        </div>
      </div>
      {logoutError && <p role="alert" className="container-shell pb-3 text-sm text-red-800">{t.failed}</p>}
    </header>
    <main id="main" tabIndex={-1} className="flex-1"><Outlet /></main>
    <footer className="border-t border-line bg-white py-7">
      <div className="container-shell flex flex-wrap items-center justify-between gap-3">
        <Brand /><p className="text-sm text-muted">{t.footer}</p>
      </div>
    </footer>
  </div>
}
