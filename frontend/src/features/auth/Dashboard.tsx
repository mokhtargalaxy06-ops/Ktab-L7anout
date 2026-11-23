import { lazy, Suspense, useEffect, useState } from 'react'
import { isAxiosError } from 'axios'
import { Navigate, Route, Routes } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import copy, { accountDashboard, type AccountRole } from './accountCopy'
import { ShopDashboard } from '../shops/ShopDashboard'
import { useSession, type User } from './session'
import { donationCopy } from '../donations/copy'

const CaseDashboard = lazy(() => import('../cases/CaseDashboard').then(module => ({ default: module.CaseDashboard })))
const SettlementDashboard = lazy(() => import('../settlements/SettlementPages').then(module => ({ default: module.SettlementDashboard })))
const AdminWorkspace = lazy(() => import('../admin/AdminWorkspace').then(module => ({ default: module.AdminWorkspace })))

function ProfileForm({ user }: { user: User }) {
  const { locale, t } = useLocale()
  const labels = copy[locale]
  const { setUser } = useSession()
  const [name, setName] = useState(user.name)
  const [phone, setPhone] = useState(user.phone ?? '')
  const [whatsappContact, setWhatsappContact] = useState(user.whatsapp_contact ?? false)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  async function save() {
    setError('')
    setMessage('')
    const trimmedName = name.trim()
    if (!trimmedName || trimmedName.length > 120) { setError(t.invalidFields); return }
    setBusy(true)
    try {
      await getCsrfCookie()
      const response = await api.patch<{ data: User }>('/api/v1/me', {
        name: trimmedName, locale, phone: phone.trim() || null, whatsapp_contact: Boolean(phone.trim()) && whatsappContact,
      })
      setUser(response.data.data)
      setPhone(response.data.data.phone ?? '')
      setWhatsappContact(response.data.data.whatsapp_contact ?? false)
      setMessage(labels.saved)
    } catch (failure) {
      setError(isAxiosError(failure) && failure.response?.data?.errors?.phone ? labels.phoneInvalid : t.failed)
    } finally {
      setBusy(false)
    }
  }

  return <Card className="p-5">
    <h2 className="mb-4 text-xl font-bold">{labels.profile}</h2>
    <form className="grid gap-4" onSubmit={event => { event.preventDefault(); void save() }}>
      <Input label={t.name} value={name} maxLength={120} onChange={event => setName(event.target.value)} />
      <Input label={t.email} readOnly value={user.email} dir="ltr" />
      <Input label={labels.phone} type="tel" autoComplete="tel" dir="ltr" maxLength={40}
        value={phone} hint={labels.phoneHint} onChange={event => setPhone(event.target.value)} />
      <label className="flex items-start gap-2 text-sm">
        <input type="checkbox" disabled={!phone.trim()} checked={Boolean(phone.trim()) && whatsappContact}
          onChange={event => setWhatsappContact(event.target.checked)} className="mt-1" />{labels.whatsappConsent}
      </label>
      <div><Button type="submit" disabled={busy}>{busy ? t.loading : labels.save}</Button></div>
      {message && <p role="status">{message}</p>}
      {error && <p role="alert" className="text-red-800">{error}</p>}
    </form>
  </Card>
}

function DashboardPage({ role, user }: { role: AccountRole; user: User }) {
  const { locale, t } = useLocale()
  const labels = copy[locale]
  const [notice, setNotice] = useState('')
  const [failed, setFailed] = useState(false)
  const [busy, setBusy] = useState(false)

  async function resend() {
    setFailed(false)
    setNotice('')
    setBusy(true)
    try {
      await getCsrfCookie()
      await api.post('/api/v1/email/verification-notification')
      setNotice(labels.sent)
    } catch {
      setFailed(true)
    } finally {
      setBusy(false)
    }
  }

  if (!user.roles.includes(role)) return <section className="container-shell py-12">
    <h1 className="display mb-5 text-3xl">{labels.forbidden}</h1><ButtonLink to={accountDashboard(user.roles)}>{labels.dashboard}</ButtonLink>
  </section>
  if (role === 'administrator' && user.abilities.includes('users.review')) {
    return <Suspense fallback={<Loading label={t.loading} />}><AdminWorkspace /></Suspense>
  }

  return <section className="container-shell py-10">
    <p className="mb-2 font-semibold text-forest">{labels[role]}</p>
    <h1 className="display mb-6 text-4xl">{labels.dashboard}</h1>
    {!user.email_verified && role === 'administrator' && <Card className="mb-5 flex flex-wrap items-center justify-between gap-3 bg-mint p-4">
      <p>{labels.verify}</p>
      <Button disabled={busy} onClick={() => void resend()}>{busy ? t.loading : labels.resend}</Button>
      {notice && <p role="status">{notice}</p>}
      {failed && <p role="alert" className="text-red-800">{t.failed}</p>}
    </Card>}
    <div className="grid gap-5 md:grid-cols-[1fr_1.4fr]">
      <ProfileForm user={user} />
      <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2">
        {role === 'donor' && <Card className="grid justify-items-start gap-3 p-5">
          <ButtonLink variant="secondary" to="/cases">{labels.sections.donor[0]}</ButtonLink>
          {user.abilities.includes('donations.viewOwn') && <ButtonLink variant="secondary" to="/donations">{donationCopy[locale].history}</ButtonLink>}
          {user.abilities.includes('donations.create') && <ButtonLink to="/donations/new">{donationCopy[locale].donate}</ButtonLink>}
        </Card>}
      </div>
    </div>
    {role === 'shopkeeper' && user.abilities.includes('shops.manageAssigned') && <ShopDashboard />}
    <Suspense fallback={<Loading label={t.loading} />}>
      {role === 'beneficiary' && user.abilities.includes('applications.submit') && <CaseDashboard audience="beneficiary" />}
      {role === 'shopkeeper' && user.abilities.includes('verifications.respondAssigned') && <CaseDashboard audience="shop" />}
      {role === 'shopkeeper' && user.abilities.includes('settlements.confirmAssigned') && <SettlementDashboard />}
    </Suspense>
  </section>
}

export function Dashboard() {
  const { user, ready } = useSession()
  const { t } = useLocale()
  if (!ready) return <div className="container-shell py-10"><Loading label={t.loading} /></div>
  if (!user) return <Navigate to="/login" replace />
  return <Routes>
    <Route index element={<Navigate to={accountDashboard(user.roles)} replace />} />
    {(['donor', 'beneficiary', 'shopkeeper', 'administrator'] as const).map(role =>
      <Route path={role === 'administrator' ? 'administrator/*' : role} key={role} element={<DashboardPage role={role} user={user} />} />)}
    <Route path="*" element={<Navigate to={accountDashboard(user.roles)} replace />} />
  </Routes>
}

export function EmailVerified() {
  const { locale } = useLocale()
  const labels = copy[locale]
  const { refresh } = useSession()
  useEffect(() => { void refresh() }, [refresh])
  return <section className="container-shell py-12"><h1 className="display mb-5 text-4xl">{labels.verified}</h1>
    <ButtonLink to="/dashboard">{labels.dashboard}</ButtonLink>
  </section>
}
