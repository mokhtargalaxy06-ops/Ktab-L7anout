import { useEffect, useState } from 'react'
import { Navigate, Route, Routes } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import copy, { accountDashboard, type AccountRole } from './accountCopy'
import { AdminAccounts } from './AdminAccounts'
import { useSession, type User } from './session'

function ProfileForm({ user }: { user: User }) {
  const { locale, t } = useLocale()
  const labels = copy[locale]
  const { setUser } = useSession()
  const [name, setName] = useState(user.name)
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
      const response = await api.patch<{ data: User }>('/api/v1/me', { name: trimmedName, locale })
      setUser(response.data.data)
      setMessage(labels.saved)
    } catch {
      setError(t.failed)
    } finally {
      setBusy(false)
    }
  }

  return <Card className="p-5">
    <h2 className="mb-4 text-xl font-bold">{labels.profile}</h2>
    <form className="grid gap-4" onSubmit={event => { event.preventDefault(); void save() }}>
      <Input label={t.name} value={name} maxLength={120} onChange={event => setName(event.target.value)} />
      <Input label={t.email} readOnly value={user.email} dir="ltr" />
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

  return <section className="container-shell py-10">
    <p className="mb-2 font-semibold text-forest">{labels[role]}</p>
    <h1 className="display mb-6 text-4xl">{labels.dashboard}</h1>
    {!user.email_verified && <Card className="mb-5 flex flex-wrap items-center justify-between gap-3 bg-mint p-4">
      <p>{labels.verify}</p>
      <Button disabled={busy} onClick={() => void resend()}>{busy ? t.loading : labels.resend}</Button>
      {notice && <p role="status">{notice}</p>}
      {failed && <p role="alert" className="text-red-800">{t.failed}</p>}
    </Card>}
    <div className="grid gap-5 md:grid-cols-[1fr_1.4fr]">
      <ProfileForm user={user} />
      <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2">
        {labels.sections[role].map(section => <Card key={section} className="p-5">
          <h2 className="mb-2 text-lg font-bold">{section}</h2><p className="text-muted">{labels.pending}</p>
        </Card>)}
      </div>
    </div>
    {role === 'administrator' && user.abilities.includes('users.review') && <AdminAccounts />}
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
      <Route path={role} key={role} element={<DashboardPage role={role} user={user} />} />)}
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
