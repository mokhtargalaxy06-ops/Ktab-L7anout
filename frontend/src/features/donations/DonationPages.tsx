import { useState, type ReactNode } from 'react'
import { isAxiosError } from 'axios'
import { Navigate, Route, Routes, useLocation, useNavigate, useParams, useSearchParams } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import { useSession } from '../auth/session'
import { mad, toMinor, type PublicCase } from '../discovery/types'
import { PageControls } from '../shops/ShopDashboard'
import { useResource, withCursor, write, type Collection } from '../shops/shopApi'
import { donationCopy } from './copy'

type Scope = 'specific' | 'urgent_pool'
type Payment = 'pending' | 'confirmed' | 'failed' | 'cancelled'
type Allocation = 'waiting' | 'partial' | 'allocated' | 'returned'
type Settlement = 'requested' | 'submitted' | 'unknown' | 'evidence_pending' | 'paid' | 'failed' | 'canceled'
type Donation = {
  id: string; scope: Scope; amount_minor: string; payment_status: Payment; allocation_status: Allocation;
  case_reference?: string; confirmed_at: string | null; created_at: string; captured_minor: string;
  allocated_minor: string; returned_minor: string; return_pending_minor: string; unallocated_minor: string;
  allocations: { id: string; case_reference: string; amount_minor: string; original_minor: string; settlements: { amount_minor: string; status: Settlement }[] }[];
  returns: { kind: 'refund' | 'reversal'; status: 'requested' | 'confirmed' | 'canceled'; amount_minor: string }[];
}
type Policy = { version: string; simulated: boolean; terms: { fr: string; ar: string } }

function DonationGuard({ children }: { children: ReactNode }) {
  const { user, ready } = useSession()
  const location = useLocation()
  const { locale, t } = useLocale()
  if (!ready) return <div className="container-shell py-10"><Loading label={t.loading} /></div>
  if (!user) return <Navigate to="/login" state={{ returnTo: location.pathname + location.search }} replace />
  if (!user.abilities.includes('donations.create')) return <p role="alert" className="container-shell py-10">{donationCopy[locale].unavailable}</p>

  return <section className="container-shell grid gap-5 py-10">
    <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 font-semibold text-amber-900">{donationCopy[locale].banner}</p>
    {children}
  </section>
}

export function DonationRoutes() {
  return <DonationGuard><Routes>
    <Route index element={<DonationHistory />} />
    <Route path="new" element={<DonateForm />} />
    <Route path=":id" element={<DonationDetail />} />
    <Route path=":id/checkout" element={<DonationDetail checkout />} />
    <Route path=":id/success" element={<DonationDetail />} />
    <Route path=":id/failure" element={<DonationDetail />} />
    <Route path="*" element={<Navigate to="/donations" replace />} />
  </Routes></DonationGuard>
}

export function DonateForm() {
  const { locale, t } = useLocale()
  const text = donationCopy[locale]
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [scope, setScope] = useState<Scope>(params.get('case') ? 'specific' : 'urgent_pool')
  const caseRef = params.get('case') ?? ''
  const info = useResource<{ data: PublicCase }>(scope === 'specific' && caseRef ? '/api/v1/cases/' + encodeURIComponent(caseRef) : null)
  const terms = useResource<{ data: Policy }>('/api/v1/donation-policies/current')
  const [amount, setAmount] = useState('50')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const record = info?.data?.data
  const policy = terms?.data?.data
  const minor = /^[0-9]{1,10}([.,][0-9]{1,2})?$/.test(amount) ? toMinor(amount) : '0'
  const valid = minor !== '0' && (scope === 'urgent_pool' || (record && BigInt(minor) <= BigInt(record.remaining_minor)))

  async function submit() {
    if (!valid || !policy?.simulated) return
    setBusy(true)
    setError('')
    const body = { scope, ...(scope === 'specific' && { case_reference: caseRef }), amount_minor: minor, currency: 'MAD', policy_version: policy.version }
    const storageKey = 'donation-intent:' + JSON.stringify(body)
    const key = sessionStorage.getItem(storageKey) ?? crypto.randomUUID()
    sessionStorage.setItem(storageKey, key)
    try {
      await getCsrfCookie()
      const response = await api.post<{ data: Donation }>('/api/v1/donations', body, { headers: { 'Idempotency-Key': key } })
      sessionStorage.removeItem(storageKey)
      void navigate('/donations/' + response.data.data.id + '/checkout')
    } catch (failure) {
      const status = isAxiosError(failure) ? failure.response?.status : undefined
      setError(status === 409 || status === 422 ? text.invalid : t.failed)
    } finally {
      setBusy(false)
    }
  }

  return <>
    <h1 className="display text-4xl">{text.donate}</h1>
    {terms?.failed ? <p role="alert">{t.failed}</p> : !policy ? <Loading label={t.loading} />
      : !policy.simulated ? <p>{text.unavailable}</p>
      : <form className="grid gap-4 lg:grid-cols-2" onSubmit={event => { event.preventDefault(); void submit() }}>
        <Card className="grid content-start gap-4 p-6">
          <fieldset className="grid gap-2"><legend className="pb-2 font-bold">{text.type}</legend>
            {(['specific', 'urgent_pool'] as const).map(mode => <label key={mode} className="flex items-center gap-2">
              <input type="radio" name="scope" checked={scope === mode} onChange={() => setScope(mode)} />
              {mode === 'specific' ? text.specific : text.pool}
            </label>)}
          </fieldset>
          {scope === 'specific' && (!record ? <ButtonLink variant="secondary" to="/cases">{text.choose}</ButtonLink>
            : <p>{text.caseLabel} · {record.city[locale === 'ar' ? 'name_ar' : 'name_fr']} · <bdi>{mad(record.remaining_minor, locale)}</bdi></p>)}
          <div className="flex flex-wrap gap-2">
            {['50', '100', '250'].map(preset => <button key={preset} type="button" aria-pressed={amount === preset} onClick={() => setAmount(preset)}
              className="rounded-lg border border-line px-4 py-2 font-semibold aria-pressed:border-forest aria-pressed:bg-mint">
              <bdi>{mad(toMinor(preset), locale)}</bdi>
            </button>)}
          </div>
          <Input label={text.amount} inputMode="decimal" required value={amount} onChange={event => setAmount(event.target.value)} />
        </Card>
        <Card className="grid content-start gap-4 p-6">
          <h2 className="display text-2xl">{text.summary}</h2>
          <p>{scope === 'specific' ? text.specific : text.pool} · <bdi className="font-bold">{mad(minor, locale)}</bdi></p>
          <p className="text-sm text-muted">{policy.terms[locale]}</p>
          <label className="flex items-center gap-2"><input type="checkbox" required />{text.accepted}</label>
          {error && <p role="alert">{error}</p>}
          <div><Button type="submit" disabled={!valid || busy}>{text.checkout}</Button></div>
        </Card>
      </form>}
  </>
}

export function DonationHistory() {
  const { locale, t } = useLocale()
  const text = donationCopy[locale]
  const [cursor, setCursor] = useState('')
  const [refresh, setRefresh] = useState(0)
  const result = useResource<Collection<Donation>>(withCursor('/api/v1/donations', cursor), refresh)
  return <>
    <h1 className="display text-4xl">{text.history}</h1>
    <div className="flex flex-wrap gap-2"><ButtonLink to="/donations/new">{text.donate}</ButtonLink>
      <Button onClick={() => setRefresh(value => value + 1)}>{text.refresh}</Button></div>
    {result?.failed ? <p role="alert">{t.failed}</p> : !result?.data ? <Loading label={t.loading} /> : result.data.data.length === 0 && <p>{text.none}</p>}
    <div className="grid gap-3 md:grid-cols-2">{result?.data?.data.map(item => <Card key={item.id} className="grid gap-3 p-5">
      <p className="font-bold"><bdi>{mad(item.amount_minor, locale)}</bdi> · {item.scope === 'specific' ? text.specific : text.pool}</p>
      <p>{text[item.payment_status]} · {text[item.allocation_status]}</p>
      <div><ButtonLink variant="secondary" to={'/donations/' + item.id}>{text.detail}</ButtonLink></div>
    </Card>)}</div>
    <PageControls meta={result?.data?.meta} onChange={setCursor} />
  </>
}

export function DonationDetail({ checkout = false }: { checkout?: boolean }) {
  const { id } = useParams()
  const navigate = useNavigate()
  const { locale, t } = useLocale()
  const text = donationCopy[locale]
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const [refresh, setRefresh] = useState(0)
  const result = useResource<{ data: Donation }>(id ? '/api/v1/donations/' + id : null, refresh)
  const item = result?.data?.data
  const route = '/donations/' + id

  async function simulate(outcome: string) {
    setBusy(true)
    setError(false)
    try {
      const updated = await write<Donation>('post', '/api/v1/donations/' + id + '/simulation', { outcome })
      setRefresh(value => value + 1)
      if (updated.payment_status !== 'pending') void navigate(route + (updated.payment_status === 'confirmed' ? '/success' : '/failure'))
    } catch {
      setError(true)
      setRefresh(value => value + 1)
    } finally {
      setBusy(false)
    }
  }

  if (!item) return result?.failed ? <p role="alert">{t.failed}</p> : <Loading label={t.loading} />
  const title = checkout ? text.simulate : item.payment_status === 'confirmed' ? text.successTitle
    : item.payment_status === 'failed' || item.payment_status === 'cancelled' ? text.failureTitle : text.detail

  return <>
    <h1 className="display text-4xl">{title}</h1>
    <Card className="grid gap-3 p-6">
      <p className="font-bold"><bdi>{mad(item.amount_minor, locale)}</bdi> · {item.scope === 'specific' ? text.specific : text.pool}</p>
      <p>{text[item.payment_status]} · {text[item.allocation_status]}</p>
      {item.confirmed_at && <time dateTime={item.confirmed_at}>{new Date(item.confirmed_at).toLocaleString(locale === 'ar' ? 'ar-MA' : 'fr-MA')}</time>}
      <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {([
          [text.captured, item.captured_minor], [text.allocated, item.allocated_minor],
          [text.unallocated, item.unallocated_minor], [text.queued, item.return_pending_minor], [text.returned, item.returned_minor],
        ]).map(([label, value]) => <div key={label}><dt className="text-muted">{label}</dt><dd className="font-bold"><bdi>{mad(value, locale)}</bdi></dd></div>)}
      </dl>
      {checkout && item.payment_status === 'pending' && <div className="flex flex-wrap gap-2">
        {([['success', text.success], ['pending', text.wait], ['failed', text.failure], ['cancelled', text.cancel]]).map(([code, label]) =>
          <Button key={code} disabled={busy} onClick={() => void simulate(code)}>{label}</Button>)}
      </div>}
      {error && <p role="alert">{t.failed}</p>}
    </Card>
    <Card className="grid gap-3 p-6">
      <h2 className="display text-2xl">{text.allocations}</h2>
      {item.allocations.length === 0 && <p>{text.none}</p>}
      {item.allocations.map(allocation => <div key={allocation.id} className="border-t border-line pt-3">
        <p><bdi className="break-all">{allocation.case_reference}</bdi> · <bdi>{mad(allocation.amount_minor, locale)}</bdi></p>
        <p className="text-sm font-semibold">{text.settlement}</p>
        {allocation.settlements.length ? allocation.settlements.map((line, index) => <p key={index}>
          {text[line.status]} · <bdi>{mad(line.amount_minor, locale)}</bdi></p>) : <p className="text-muted">{text.noSettlement}</p>}
      </div>)}
    </Card>
    {item.returns.length > 0 && <Card className="grid gap-3 p-6">
      <h2 className="display text-2xl">{text.returns}</h2>
      {item.returns.map((record, index) => <p key={index}>{text[record.kind]} · {text[record.status]} · <bdi>{mad(record.amount_minor, locale)}</bdi></p>)}
    </Card>}
    <div className="flex flex-wrap gap-2">
      <ButtonLink variant="secondary" to="/donations">{text.history}</ButtonLink>
      {!checkout && item.payment_status === 'pending' && <ButtonLink to={route + '/checkout'}>{text.checkout}</ButtonLink>}
      <Button onClick={() => setRefresh(value => value + 1)}>{text.refresh}</Button>
    </div>
  </>
}
