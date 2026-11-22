import { useEffect, useState } from 'react'
import { isAxiosError } from 'axios'
import { Link } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import type { User } from './session'
import { adminCopy } from '../admin/copy'
import accountCopy from './accountCopy'

const labels = {
  fr: {
    title: 'Comptes utilisateurs', change: 'Modifier le statut', status: 'Statut', saved: 'Statut enregistré.',
    active: 'Actif', suspended: 'Suspendu', closed: 'Fermé', next: 'Page suivante', previous: 'Page précédente',
    password: 'Votre mot de passe actuel', invalidPassword: 'Vérifiez votre mot de passe.',
    empty: 'Aucun compte à afficher.', conflict: 'Un compte fermé ne peut pas être rouvert.',
  },
  ar: {
    title: 'حسابات المستخدمين', change: 'تعديل الحالة', status: 'الحالة', saved: 'تم حفظ الحالة.',
    active: 'نشط', suspended: 'معلّق', closed: 'مغلق', next: 'الصفحة التالية', previous: 'الصفحة السابقة',
    password: 'كلمة مرورك الحالية', invalidPassword: 'تحقق من كلمة مرورك.',
    empty: 'لا توجد حسابات للعرض.', conflict: 'لا يمكن إعادة فتح حساب مغلق.',
  },
}

const statuses = ['active', 'suspended', 'closed'] as const
type Status = typeof statuses[number]
type Account = User & { status: Status }
type AccountPage = { data: Account[]; meta: { next_cursor: string | null; prev_cursor: string | null } }

export function AdminAccounts() {
  const { locale, t } = useLocale()
  const text = labels[locale]
  const admin = adminCopy[locale]
  const [filters, setFilters] = useState({ q: '', role: '', status: '' })
  const [cursor, setCursor] = useState<string | null>(null)
  const [page, setPage] = useState<AccountPage | null>(null)
  const [target, setTarget] = useState<Account | null>(null)
  const [status, setStatus] = useState<Status>('active')
  const [password, setPassword] = useState('')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [loadFailed, setLoadFailed] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    void api.get<AccountPage>('/api/v1/admin/users', { signal: controller.signal, params: { ...filters, cursor } })
      .then(response => { if (!controller.signal.aborted) setPage(response.data) })
      .catch(() => { if (!controller.signal.aborted) setLoadFailed(true) })
    return () => controller.abort()
  }, [cursor, filters])

  function open(account: Account) {
    setTarget(account)
    setStatus(account.status)
    setPassword('')
    setReason('')
    setError('')
    setNotice('')
  }

  function move(next: string) {
    setLoadFailed(false)
    setPage(null)
    setCursor(next)
  }

  async function save() {
    if (!target || !password || !reason.trim()) return
    setBusy(true)
    setError('')
    try {
      await getCsrfCookie()
      const response = await api.patch<{ data: Account }>('/api/v1/admin/users/' + target.id + '/status', { status, current_password: password, reason })
      setPage(current => current && { ...current, data: current.data.flatMap(account => account.id !== target.id ? [account]
        : !filters.status || filters.status === status ? [response.data.data] : []) })
      setTarget(null)
      setNotice(text.saved)
    } catch (failure) {
      const statusCode = isAxiosError(failure) ? failure.response?.status : undefined
      setError(statusCode === 422 ? text.invalidPassword : statusCode === 409 ? text.conflict : t.failed)
    } finally {
      setPassword('')
      setBusy(false)
    }
  }

  return <Card className="mt-5 p-5">
    <h2 className="mb-4 text-xl font-bold">{text.title}</h2>
    <form className="mb-4 grid items-end gap-2 sm:grid-cols-2" onSubmit={event => {
      event.preventDefault()
      const values = new FormData(event.currentTarget)
      setPage(null)
      setLoadFailed(false)
      setCursor(null)
      setFilters({ q: String(values.get('q')).trim(), role: String(values.get('role')), status: String(values.get('status')) })
    }}>
      <Input label={admin.search} name="q" maxLength={120} />
      <select className="min-h-11 rounded border border-line p-2" name="role" aria-label={admin.role}>
        <option value="">{admin.all} · {admin.role}</option>
        {(['donor', 'beneficiary', 'shopkeeper', 'administrator'] as const).map(role => <option key={role} value={role}>{accountCopy[locale][role]}</option>)}
      </select>
      <select className="min-h-11 rounded border border-line p-2" name="status" aria-label={admin.status}>
        <option value="">{admin.all} · {admin.status}</option>
        {statuses.map(value => <option key={value} value={value}>{text[value]}</option>)}
      </select>
      <Button type="submit">{admin.search}</Button>
    </form>
    {loadFailed ? <p role="alert">{t.failed}</p> : !page ? <p role="status">{t.loading}</p> : <>
      {page.data.length === 0 && <p>{text.empty}</p>}
      <ul className="divide-y divide-line">
        {page.data.map(account => <li key={account.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
          <div className="min-w-0">
            <p className="font-semibold">{account.name}</p>
            <p className="break-all text-sm text-muted"><bdi>{account.email}</bdi></p>
          </div>
          <span>{text[account.status]}</span>
          <Link className="font-semibold text-forest underline" to={'/dashboard/administrator/audit?target=' + account.id}>{admin.activity}</Link>
          {!account.roles.includes('administrator') && account.status !== 'closed' && <Button onClick={() => open(account)}>{text.change}</Button>}
        </li>)}
      </ul>
      <div className="flex flex-wrap gap-3">
        {page.meta.prev_cursor && <Button onClick={() => move(page.meta.prev_cursor!)}>{text.previous}</Button>}
        {page.meta.next_cursor && <Button onClick={() => move(page.meta.next_cursor!)}>{text.next}</Button>}
      </div>
    </>}
    {notice && <p role="status" className="mt-3">{notice}</p>}
    <Modal title={text.change} open={target !== null} closeLabel={t.close} onClose={() => { if (!busy) { setTarget(null); setPassword('') } }}>
      <form className="grid gap-4" onSubmit={event => { event.preventDefault(); void save() }}>
        <p>{target?.name}</p>
        <label className="grid gap-2 font-semibold">{text.status}
          <select value={status} onChange={event => setStatus(event.target.value as Status)} className="min-h-12 rounded border border-line px-3">
            {statuses.map(value => <option key={value} value={value}>{text[value]}</option>)}
          </select>
        </label>
        <Input label={text.password} required type="password" autoComplete="current-password" value={password} onChange={event => setPassword(event.target.value)} />
        <Input label={admin.reason} required value={reason} maxLength={500} onChange={event => setReason(event.target.value)} />
        {error && <p role="alert" className="text-red-800">{error}</p>}
        <Button disabled={busy} type="submit">{busy ? t.loading : text.change}</Button>
      </form>
    </Modal>
  </Card>
}
