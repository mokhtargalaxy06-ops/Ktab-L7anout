import { useState } from 'react'
import { isAxiosError } from 'axios'
import { useSearchParams } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, type ApiError } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { GeographySelect } from '../shops/GeographySelect'
import { PageControls } from '../shops/ShopDashboard'
import { selectClass, useResource, write, type Collection, type Locations } from '../shops/shopApi'
import { caseCopy, type Action, type CaseStatus } from './copy'
import { adminCopy } from '../admin/copy'
import { DiscoveryAssessment } from '../discovery/DiscoveryAssessment'
import { discoveryCopy } from '../discovery/copy'
import type { Story } from '../discovery/types'

type Audience = 'beneficiary' | 'shop' | 'admin'
type Evidence = { id: string; media_type: string; status: 'available' | 'quarantined' | 'rejected'; revision: number }
type Entry = { action: Action | 'public_assessed'; reason?: string; amount_minor?: string | null; created_at: string; revision: number }
type Debt = Locations & {
  id: string; status: CaseStatus; revision: number; shop_id: string; shop_name: string; debt_reference: string; debt_date: string
  amount_minor: string; situation?: string; household_size?: number; flagged?: boolean; duplicate_count?: number
  duplicates?: Pick<Debt, 'id' | 'debt_reference' | 'status'>[]
  public_story?: Story; vulnerability_grade?: number | null; access_risk_grade?: number | null
  documents?: Evidence[]; history?: Entry[]
}

function DraftForm({ record, saved }: { record?: Debt; saved: (next: Debt) => void }) {
  const { locale, t } = useLocale()
  const text = caseCopy[locale]
  const [location, setLocation] = useState<Locations>({ region_id: record?.region_id ?? '', city_id: record?.city_id ?? '', locality_id: null })
  const [shop, setShop] = useState(record?.shop_id ?? '')
  const [error, setError] = useState(false)
  const [busy, setBusy] = useState(false)
  const shops = useResource<Collection<{ id: string; name: string }>>(location.city_id ? '/api/v1/beneficiary/shops?city_id=' + location.city_id : null)

  async function save(form: HTMLFormElement) {
    const fields = Object.fromEntries(new FormData(form))
    setBusy(true)
    setError(false)
    try {
      saved(await write<Debt>(record ? 'patch' : 'post', '/api/v1/beneficiary/cases' + (record ? '/' + record.id : ''), {
        ...fields, city_id: location.city_id, shop_id: shop,
      }))
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return <Card className="p-5">
    <h3 className="mb-3 text-lg font-bold">{text.save}</h3>
    <form className="grid gap-3" onSubmit={event => { event.preventDefault(); void save(event.currentTarget) }}>
      <GeographySelect through="city" value={location} onChange={next => { setLocation(next); if (next.city_id !== location.city_id) setShop('') }} />
      <label className="grid gap-1 text-sm font-semibold">{text.shopName}
        <select className={selectClass} value={shop} required disabled={!shops?.data} onChange={event => setShop(event.target.value)}>
          <option value="">—</option>
          {shops?.data?.data.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select>
      </label>
      {shops?.failed && <p role="alert">{t.failed}</p>}
      {shops?.data?.data.length === 0 && <p>{text.none}</p>}
      <Input label={text.reference} name="debt_reference" required maxLength={100} defaultValue={record?.debt_reference} />
      <Input label={text.date} type="date" name="debt_date" required defaultValue={record?.debt_date} />
      <Input label={text.amount} name="amount_minor" required inputMode="numeric" pattern="[1-9][0-9]{0,11}" defaultValue={record?.amount_minor} />
      <label className="grid gap-1 text-sm font-semibold">{text.situation}
        <textarea name="situation" required maxLength={3000} defaultValue={record?.situation}
          className="min-h-24 rounded-lg border border-line p-3" />
      </label>
      <Input label={text.household} name="household_size" type="number" min={1} max={50} defaultValue={record?.household_size} />
      <div><Button type="submit" disabled={busy || !shop || !shops?.data}>{text.save}</Button></div>
      {error && <p role="alert" className="text-red-800">{t.invalidFields}</p>}
    </form>
  </Card>
}

function actionsFor(record: Debt, audience: Audience): Action[] {
  if (audience === 'shop') return record.status === 'pending_verification' ? ['confirm', 'dispute', 'reject'] : []
  if (audience === 'admin' && record.status === 'published') return ['unpublish', 'suspend']
  if (audience !== 'admin' || !['submitted', 'pending_verification', 'under_review', 'disputed', 'approved'].includes(record.status)) return []
  const actions: Action[] = ['request_info', 'reject', record.flagged ? 'clear_flag' : 'flag']
  if (record.status === 'submitted') actions.unshift('route')
  if (record.status === 'disputed') actions.unshift('reopen_dispute')
  if (record.status === 'under_review' && !record.flagged) actions.unshift('approve')
  if (record.status === 'approved' && !record.flagged) actions.unshift('publish')
  return actions
}

function Tracking({ record, audience, base, saved, open }: { record: Debt; audience: Audience; base: string; saved: (next: Debt) => void; open: (id: string) => void }) {
  const { locale, t } = useLocale()
  const text = caseCopy[locale]
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [decision, setDecision] = useState<Action | ''>('')
  const actions = actionsFor(record, audience)
  const ownerDraft = audience === 'beneficiary' && record.status === 'draft'
  const canUpload = ownerDraft || (audience === 'shop' && record.status === 'pending_verification')

  async function send(path: string, body: object, form?: HTMLFormElement) {
    setError('')
    setBusy(true)
    try {
      saved(await write<Debt>('post', base + '/' + record.id + '/' + path, body))
      form?.reset()
      setDecision('')
    } catch (failure) {
      const response = isAxiosError<ApiError>(failure) ? failure.response?.data : undefined
      setError(response?.message ?? t.failed)
    } finally {
      setBusy(false)
    }
  }

  async function download(document: Evidence) {
    try {
      const response = await api.get<Blob>(base + '/' + record.id + '/documents/' + document.id, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = window.document.createElement('a')
      link.href = url
      link.download = document.id + (document.media_type === 'application/pdf' ? '.pdf' : document.media_type === 'image/png' ? '.png' : '.jpg')
      link.click()
      window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      setError(t.failed)
    }
  }

  return <div className="grid gap-4">
    <Card className="grid gap-2 p-5">
      <h3 className="text-lg font-bold">{record.shop_name} · {text.statuses[record.status]}</h3>
      <p>{text.reference} <bdi>{record.debt_reference}</bdi></p>
      <p>{text.amount} <bdi>{record.amount_minor}</bdi></p>
      <p>{text.date} {new Intl.DateTimeFormat(locale === 'ar' ? 'ar-MA' : 'fr-MA', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(record.debt_date))}</p>
      {record.situation && <p className="whitespace-pre-wrap break-words">{record.situation}</p>}
      {record.household_size && <p>{text.household} {record.household_size}</p>}
      {audience === 'admin' && <p>{text.duplicates} {record.duplicate_count} {record.flagged ? ' · ' + text.flagged : ''}</p>}
      {record.duplicates?.map(match => <div key={match.id} className="flex flex-wrap items-center gap-2">
        <bdi>{match.debt_reference}</bdi><span>{text.statuses[match.status]}</span>
        <Button onClick={() => open(match.id)}>{text.choose}</Button>
      </div>)}
    </Card>
    {ownerDraft && <DraftForm record={record} saved={saved} />}
    {audience === 'admin' && ['under_review', 'approved', 'published'].includes(record.status) && <DiscoveryAssessment
      id={record.id} revision={record.revision} story={record.public_story} vulnerability={record.vulnerability_grade}
      risk={record.access_risk_grade} reasonLabel={text.reason} />}
    <Card className="grid gap-2 p-5">
      <h3 className="font-bold">{text.evidence}</h3>
      {record.documents?.map(file => <div key={file.id} className="flex flex-wrap items-center gap-2">
        <span><bdi>{file.media_type} · v{file.revision}</bdi> · {text.statuses[file.status]}</span>
        {file.status === 'available' && <Button onClick={() => void download(file)}>{text.evidence}</Button>}
      </div>)}
      {canUpload && <form className="grid gap-2" onSubmit={event => {
        event.preventDefault()
        const form = event.currentTarget
        const payload = new FormData(form)
        payload.set('revision', String(record.revision))
        void send('documents', payload, form)
      }}>
        <Input type="file" name="document" label={text.file} accept=".pdf,.png,.jpg,.jpeg" required />
        <div><Button disabled={busy} type="submit">{text.upload}</Button></div>
      </form>}
    </Card>
    {ownerDraft && <Card className="p-5">
      <form className="grid gap-3" onSubmit={event => { event.preventDefault(); void send('submit', { consent: true, consent_version: '1', revision: record.revision }) }}>
        <p>{text.guidance}</p>
        <label className="flex items-start gap-2"><input className="mt-1" type="checkbox" required />{text.consent}</label>
        <div><Button type="submit" disabled={busy || !record.documents?.some(file => file.revision === record.revision && file.status === 'available')}>{text.submit}</Button></div>
      </form>
    </Card>}
    {audience === 'beneficiary' && ['draft', 'submitted', 'pending_verification', 'under_review', 'disputed'].includes(record.status)
      && <div><Button disabled={busy} onClick={() => void send('cancel', {})}>{text.cancel}</Button></div>}
    {actions.length > 0 && <Card className="p-5">
      <form className="grid gap-3" onSubmit={event => {
        event.preventDefault()
        const fields = new FormData(event.currentTarget)
        void send(audience === 'shop' ? 'verification' : 'review', { ...Object.fromEntries(fields), decision, revision: record.revision })
      }}>
        <label className="grid gap-1">{text.history}
          <select className={selectClass} required value={decision} onChange={event => setDecision(event.target.value as Action)}>
            <option value="">—</option>
            {actions.map(action => <option key={action} value={action}>{text.actions[action]}</option>)}
          </select>
        </label>
        {audience === 'shop' && (decision === 'confirm' || decision === 'dispute') && <Input name="amount_minor" label={text.amount}
          required={decision === 'confirm'} inputMode="numeric" pattern="[1-9][0-9]{0,11}" defaultValue={decision === 'confirm' ? record.amount_minor : ''} key={decision} />}
        {decision && <Input name="reason" label={text.reason} maxLength={500}
          required={['dispute', 'reject', 'request_info', 'flag', 'clear_flag', 'unpublish', 'suspend', 'reopen_dispute'].includes(decision)} />}
        <div><Button disabled={busy || !decision} type="submit">{decision ? text.actions[decision] : text.submit}</Button></div>
      </form>
    </Card>}
    {error && <p role="alert" className="text-red-800">{error}</p>}
    <Card className="p-5">
      <h3 className="mb-2 font-bold">{text.history}</h3>
      {record.history?.map((entry, index) => <p key={index}>{entry.action === 'public_assessed' ? discoveryCopy[locale].assessment : text.actions[entry.action]} · {new Date(entry.created_at).toLocaleDateString(locale === 'ar' ? 'ar-MA' : 'fr-MA')}
        {entry.amount_minor && <> · {text.amount} <bdi>{entry.amount_minor}</bdi></>}{entry.reason && <> · {entry.reason}</>}</p>)}
    </Card>
  </div>
}

export function CaseDashboard({ audience }: { audience: Audience }) {
  const { locale, t } = useLocale()
  const text = caseCopy[locale]
  const [params, setParams] = useSearchParams()
  const selected = params.get('case') ?? ''
  const [cursor, setCursor] = useState('')
  const [revision, setRevision] = useState(0)
  const base = '/api/v1/' + audience + '/cases'
  const listQuery = new URLSearchParams(cursor ? { cursor } : {})
  if (audience === 'admin') {
    for (const key of ['region_id', 'city_id', 'status']) {
      const value = params.get(key)
      if (value) listQuery.set(key, value)
    }
  }
  const list = useResource<Collection<Debt>>(base + '?' + listQuery, revision)
  const detail = useResource<{ data: Debt }>(selected && selected !== 'new' ? base + '/' + selected : null, revision)
  const open = (value: string) => setParams(current => {
    const updated = new URLSearchParams(current)
    if (value) updated.set('case', value)
    else updated.delete('case')
    return updated
  })
  const saved = (record: Debt) => {
    open(audience === 'admin' && record.status === 'draft' ? '' : record.id)
    setRevision(value => value + 1)
  }

  return <section className="mt-6 grid gap-4">
    <h2 className="display text-3xl">{text[audience === 'beneficiary' ? 'owner' : audience]}</h2>
    <div className="flex flex-wrap gap-2">
      {selected ? <Button onClick={() => open('')}>{text.back}</Button>
        : audience === 'beneficiary' && <Button onClick={() => open('new')}>{text.new}</Button>}
      <Button onClick={() => setRevision(value => value + 1)}>{text.refresh}</Button>
    </div>
    {!selected && <Card className="grid gap-3 p-5">
      {audience === 'admin' && <select aria-label={adminCopy[locale].status} className={selectClass}
        value={params.get('status') ?? ''} onChange={event => {
          const updated = new URLSearchParams(params)
          if (event.target.value) updated.set('status', event.target.value)
          else updated.delete('status')
          setCursor('')
          setParams(updated)
        }}>
        <option value="">{adminCopy[locale].all}</option>
        {Object.entries(text.statuses).filter(([state]) => state !== 'draft').map(([state, label]) =>
          <option key={state} value={state}>{label}</option>)}
      </select>}
      {list?.failed ? <p role="alert">{t.failed}</p> : !list?.data ? <p role="status">{t.loading}</p>
        : list.data.data.length === 0 && <p>{text.none}</p>}
      {list?.data?.data.map(record => <div key={record.id} className="flex flex-wrap items-center justify-between gap-2">
        <span><bdi>{record.debt_reference}</bdi> · {record.shop_name} · {text.statuses[record.status]}</span>
        <Button onClick={() => open(record.id)}>{text.choose}</Button>
      </div>)}
      <PageControls meta={list?.data?.meta} onChange={setCursor} />
    </Card>}
    {audience === 'beneficiary' && selected === 'new' && <DraftForm saved={saved} />}
    {selected && selected !== 'new' && (detail?.failed ? <p role="alert">{t.failed}</p>
      : detail?.data ? <Tracking key={selected} record={detail.data.data} audience={audience} base={base} saved={saved} open={open} /> : <p role="status">{t.loading}</p>)}
  </section>
}
