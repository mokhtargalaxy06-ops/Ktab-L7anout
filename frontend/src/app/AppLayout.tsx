import { Globe2, HeartHandshake, Menu, X } from 'lucide-react'
import { isAxiosError } from 'axios'
import { useState } from 'react'
import { Link, NavLink, Outlet } from 'react-router'
import { useSession } from '../features/auth/session'
import { caseCopy } from '../features/cases/copy'
import { discoveryCopy } from '../features/discovery/copy'
import { settlementCopy } from '../features/settlements/copy'
import copy, { accountDashboard } from '../features/auth/accountCopy'
import { api, getCsrfCookie } from '../shared/api/client'
import { Button, ButtonLink } from '../shared/ui/Button'
import { useLocale } from './locale'

function Brand() {
  return <Link to="/" className="inline-flex min-h-11 items-center gap-2.5 text-start font-bold tracking-tight" dir="ltr">
    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-forest text-white">
      <HeartHandshake aria-hidden="true" size={22} strokeWidth={1.8} />
    </span>
    <span>Ktab-<span className="text-forest">L7anout</span></span>
  </Link>
}

export function AppLayout() {
  const { locale, setLocale, t } = useLocale()
  const { user, setUser } = useSession()
  const [signingOut, setSigningOut] = useState(false)
  const [logoutError, setLogoutError] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)

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
      <div className="container-shell grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 py-3 md:flex md:flex-wrap md:justify-between xl:grid xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
        <Brand />
        <button type="button" aria-label={t.menu} aria-expanded={menuOpen} aria-controls="primary-navigation"
          onClick={() => setMenuOpen(open => !open)}
          className="grid size-11 place-items-center rounded-lg border border-line text-forest md:hidden">
          {menuOpen ? <X aria-hidden="true" size={22} /> : <Menu aria-hidden="true" size={22} />}
        </button>
        <nav id="primary-navigation" aria-label={t.menu}
          className={`order-3 col-span-2 grid-cols-2 gap-x-3 border-t border-line pt-1 text-sm font-semibold text-muted md:flex md:w-full md:flex-wrap md:gap-x-6 xl:order-0 xl:w-auto xl:border-0 xl:pt-0 ${menuOpen ? 'grid' : 'hidden'}`}>
          <NavLink end to="/" onClick={() => setMenuOpen(false)} className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{t.home}</NavLink>
          <NavLink to="/cases" onClick={() => setMenuOpen(false)} className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{caseCopy[locale].publicLink}</NavLink>
          <NavLink to="/urgent" onClick={() => setMenuOpen(false)} className="inline-flex min-h-11 items-center hover:text-forest aria-[current=page]:text-forest">{discoveryCopy[locale].urgentTitle}</NavLink>
          <Link to="/#approche" onClick={() => setMenuOpen(false)} className="inline-flex min-h-11 items-center hover:text-forest">{t.approach}</Link>
        </nav>
        <div className="col-span-2 flex min-w-0 flex-wrap items-center gap-2 sm:gap-3 xl:col-span-1 xl:justify-end">
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
        <Brand /><Link className="font-semibold text-forest hover:underline" to="/transparency">{settlementCopy[locale].transparency}</Link>
        <p className="text-sm text-muted">{t.footer}</p>
      </div>
    </footer>
  </div>
}
