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
import { mad, toMajor, toMinor, type PublicCase } from '../discovery/types'
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
  whatsapp_pledge?: boolean; simulation_available?: boolean
  external_receipt?: { status: 'pending' | 'approved' | 'rejected'; amount_minor: string } | null
  allocated_minor: string; returned_minor: string; return_pending_minor: string; unallocated_minor: string;
  allocations: {
    id: string; case_reference: string; amount_minor: string; original_minor: string; shop_whatsapp_url?: string | null
    settlements: { amount_minor: string; status: Settlement }[]
  }[];
  returns: { kind: 'refund' | 'reversal'; status: 'requested' | 'confirmed' | 'canceled'; amount_minor: string }[];
}
type Policy = { version: string; simulated: boolean; terms: { fr: string; ar: string } }

function DonationGuard({ children }: { children: ReactNode }) {
  const { user, ready } = useSession()
  const location = useLocation()
  const { locale, t } = useLocale()
  if (!ready) return <div className="container-shell py-10"><Loading label={t.loading} /></div>
  if (!user) return <Navigate to="/login" state={{ returnTo: location.pathname + location.search }} replace />
  if (!user.abilities.includes('donations.viewOwn')) return <p role="alert" className="container-shell py-10">{donationCopy[locale].unavailable}</p>

  return <section className="container-shell grid gap-5 py-10">
    {children}
  </section>
}

export function DonationRoutes() {
  const { user } = useSession()
  const canDonate = user?.abilities.includes('donations.create') ?? false
  return <DonationGuard><Routes>
    <Route index element={<DonationHistory canDonate={canDonate} />} />
    <Route path="new" element={canDonate ? <DonateForm /> : <Navigate to="/dashboard/donor" replace />} />
    <Route path=":id" element={<DonationDetail canDonate={canDonate} />} />
    <Route path=":id/checkout" element={canDonate ? <DonationDetail checkout /> : <Navigate to="/dashboard/donor" replace />} />
    <Route path=":id/success" element={<DonationDetail canDonate={canDonate} />} />
    <Route path=":id/failure" element={<DonationDetail canDonate={canDonate} />} />
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
  const contact = useResource<{ shop_whatsapp_url?: string | null }>(scope === 'specific' && caseRef
    ? '/api/v1/donor/cases/' + encodeURIComponent(caseRef) + '/shop-evidence' : null)
  const terms = useResource<{ data: Policy }>('/api/v1/donation-policies/current')
  const [customAmount, setAmount] = useState<string>()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [openedMessage, setOpenedMessage] = useState('')
  const record = info?.data?.data
  const policy = terms?.data?.data
  const balance = scope === 'specific' && record ? BigInt(record.remaining_minor) : undefined
  const amount = customAmount ?? (balance !== undefined && balance < 5000n ? toMajor(String(balance)) : '50')
  const normalizedAmount = amount.trim().replace(/[٠-٩۰-۹]/g, digit => String(digit.charCodeAt(0) & 15)).replace('٫', '.')
  const minor = /^[0-9]{1,10}([.,][0-9]{1,2})?$/.test(normalizedAmount) ? toMinor(normalizedAmount) : '0'
  const exceedsBalance = balance !== undefined && BigInt(minor) > balance
  const valid = minor !== '0' && (scope === 'urgent_pool' || (balance !== undefined && !exceedsBalance))
  const shopUrl = contact?.data?.shop_whatsapp_url
  const whatsappUrl = scope === 'specific' && valid && shopUrl
    ? shopUrl + '?text=' + encodeURIComponent(text.whatsappIntent(caseRef, mad(minor, locale))) : ''
  const canRecordPledge = whatsappUrl !== '' && openedMessage === whatsappUrl
  const presets = ['5000', '10000', '25000'].filter(value => balance === undefined || BigInt(value) < balance)
  if (balance !== undefined && balance > 0n) presets.push(String(balance))

  async function submit() {
    if (busy || !valid || !policy || (scope === 'specific' ? !canRecordPledge : !policy.simulated)) return
    setBusy(true)
    setError('')
    const body = { scope, amount_minor: minor, currency: 'MAD', policy_version: policy.version,
      ...(scope === 'specific' && { case_reference: caseRef, whatsapp_pledge: true }) }
    const storageKey = 'donation-intent:' + JSON.stringify(body)
    const key = sessionStorage.getItem(storageKey) ?? crypto.randomUUID()
    sessionStorage.setItem(storageKey, key)
    try {
      await getCsrfCookie()
      const response = await api.post<{ data: Donation }>('/api/v1/donations', body, { headers: { 'Idempotency-Key': key } })
      sessionStorage.removeItem(storageKey)
      void navigate('/donations/' + response.data.data.id + (scope === 'urgent_pool' ? '/checkout' : ''))
    } catch (failure) {
      const status = isAxiosError(failure) ? failure.response?.status : undefined
      setError(status === 409 || status === 422 ? text.invalid : t.failed)
    } finally {
      setBusy(false)
    }
  }

  return <>
    <h1 className="display text-4xl">{text.donate}</h1>
    <form className="grid gap-4 lg:grid-cols-2" onSubmit={event => { event.preventDefault(); void submit() }}>
        <Card className="grid content-start gap-4 p-6">
          <fieldset className="grid gap-2"><legend className="pb-2 font-bold">{text.type}</legend>
            {(['specific', 'urgent_pool'] as const).map(mode => <label key={mode} className="flex items-center gap-2">
              <input type="radio" name="scope" checked={scope === mode} onChange={() => { setScope(mode); setAmount(undefined); setError('') }} />
              {mode === 'specific' ? text.specific : text.pool}
            </label>)}
          </fieldset>
          {scope === 'specific' && (!record ? <ButtonLink variant="secondary" to="/cases">{text.choose}</ButtonLink>
            : <p>{text.caseLabel} · {record.city[locale === 'ar' ? 'name_ar' : 'name_fr']} · <bdi>{mad(record.remaining_minor, locale)}</bdi></p>)}
          <div className="flex flex-wrap gap-2">
            {presets.map(preset => <button key={preset} type="button" aria-pressed={minor === preset} onClick={() => setAmount(toMajor(preset))}
              className="rounded-lg border border-line px-4 py-2 font-semibold aria-pressed:border-forest aria-pressed:bg-mint">
              <bdi>{mad(preset, locale)}</bdi>
            </button>)}
          </div>
          <Input label={text.amount} inputMode="decimal" required value={amount} onChange={event => setAmount(event.target.value)} />
          {exceedsBalance && <p role="alert">{text.invalid}</p>}
        </Card>
        <Card className="grid content-start gap-4 p-6">
          <h2 className="display text-2xl">{text.summary}</h2>
          <p>{scope === 'specific' ? text.specific : text.pool} · <bdi className="font-bold">{mad(minor, locale)}</bdi></p>
          {scope === 'specific' ? <>
            <p className="text-sm text-muted">{text.whatsappIntentHelp}</p>
            {contact?.failed ? <p role="alert">{t.failed}</p>
              : caseRef && !contact?.data ? <Loading label={t.loading} />
              : caseRef && !contact?.data?.shop_whatsapp_url ? <p>{text.whatsappUnavailable}</p>
              : whatsappUrl && <a
                className="justify-self-start rounded-lg bg-forest px-4 py-3 font-semibold text-white"
                target="_blank" rel="noopener noreferrer"
                href={whatsappUrl} onClick={() => setOpenedMessage(whatsappUrl)}>
                {text.whatsapp}
              </a>}
            {canRecordPledge && <Button type="submit" disabled={busy || !policy}>{text.recordPledge}</Button>}
            {canRecordPledge && terms?.failed && <p role="alert">{t.failed}</p>}
            {error && <p role="alert">{error}</p>}
          </> : terms?.failed ? <p role="alert">{t.failed}</p> : !policy ? <Loading label={t.loading} />
            : !policy.simulated ? <p>{text.unavailable}</p> : <>
          <p className="text-sm text-muted">{policy.terms[locale]}</p>
          <label className="flex items-center gap-2"><input type="checkbox" required />{text.accepted}</label>
          {error && <p role="alert">{error}</p>}
          <div><Button type="submit" disabled={!valid || busy}>{text.checkout}</Button></div>
          </>}
        </Card>
      </form>
  </>
}

export function DonationHistory({ canDonate = true }: { canDonate?: boolean }) {
  const { locale, t } = useLocale()
  const text = donationCopy[locale]
  const [cursor, setCursor] = useState('')
  const [refresh, setRefresh] = useState(0)
  const result = useResource<Collection<Donation>>(withCursor('/api/v1/donations', cursor), refresh)
  return <>
    <h1 className="display text-4xl">{text.history}</h1>
    <div className="flex flex-wrap gap-2">{canDonate && <ButtonLink to="/donations/new">{text.donate}</ButtonLink>}
      <Button onClick={() => setRefresh(value => value + 1)}>{text.refresh}</Button></div>
    {result?.failed ? <p role="alert">{t.failed}</p> : !result?.data ? <Loading label={t.loading} /> : result.data.data.length === 0 && <p>{text.none}</p>}
    <div className="grid gap-3 md:grid-cols-2">{result?.data?.data.map(item => <Card key={item.id} className="grid gap-3 p-5">
      <p className="font-bold"><bdi>{mad(item.amount_minor, locale)}</bdi> · {item.scope === 'specific' ? text.specific : text.pool}</p>
      <p>{item.whatsapp_pledge && item.payment_status === 'pending' ? text.pledged : text[item.payment_status]} · {text[item.allocation_status]}</p>
      <div><ButtonLink variant="secondary" to={'/donations/' + item.id}>{text.detail}</ButtonLink></div>
    </Card>)}</div>
    <PageControls meta={result?.data?.meta} onChange={setCursor} />
  </>
}

export function DonationDetail({ checkout = false, canDonate = true }: { checkout?: boolean; canDonate?: boolean }) {
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
    {checkout && item.simulation_available !== false && <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 font-semibold text-amber-900">{text.banner}</p>}
    <Card className="grid gap-3 p-6">
      <p className="font-bold"><bdi>{mad(item.amount_minor, locale)}</bdi> · {item.scope === 'specific' ? text.specific : text.pool}</p>
      <p>{item.whatsapp_pledge && item.payment_status === 'pending' ? text.pledged : text[item.payment_status]} · {text[item.allocation_status]}</p>
      {item.confirmed_at && <time dateTime={item.confirmed_at}>{new Date(item.confirmed_at).toLocaleString(locale === 'ar' ? 'ar-MA' : 'fr-MA')}</time>}
      {item.whatsapp_pledge && <p className="break-all">{text.pledgeReference} <bdi>{item.id}</bdi></p>}
      {item.external_receipt && <p>{text.externalReceipt[item.external_receipt.status]} · <bdi>{mad(item.external_receipt.amount_minor, locale)}</bdi></p>}
      <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {([
          [text.captured, item.captured_minor], [text.allocated, item.allocated_minor],
          [text.unallocated, item.unallocated_minor], [text.queued, item.return_pending_minor], [text.returned, item.returned_minor],
        ]).map(([label, value]) => <div key={label}><dt className="text-muted">{label}</dt><dd className="font-bold"><bdi>{mad(value, locale)}</bdi></dd></div>)}
      </dl>
      {checkout && item.payment_status === 'pending' && item.simulation_available !== false
        && (!item.external_receipt || item.external_receipt.status === 'rejected') && <div className="flex flex-wrap gap-2">
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
        {item.payment_status === 'confirmed' && allocation.shop_whatsapp_url && <a
          className="inline-block py-2 font-semibold text-forest underline" target="_blank" rel="noopener noreferrer"
          href={allocation.shop_whatsapp_url + '?text=' + encodeURIComponent(text.whatsappMessage(
            allocation.case_reference, mad(allocation.amount_minor, locale),
          ))}>{text.whatsapp}</a>}
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
      {!checkout && canDonate && item.payment_status === 'pending' && item.simulation_available !== false
        && (!item.external_receipt || item.external_receipt.status === 'rejected') && <ButtonLink to={route + '/checkout'}>{text.checkout}</ButtonLink>}
      <Button onClick={() => setRefresh(value => value + 1)}>{text.refresh}</Button>
    </div>
  </>
}
