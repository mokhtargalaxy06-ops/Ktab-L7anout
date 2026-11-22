import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useLocale } from '../../app/locale'
import { api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import copy from './copy'
import { ShopForm } from './ShopForm'
import { selectClass, useResource, withCursor, write, type Collection, type DebtRequest, type Shop, type ShopDocument } from './shopApi'

export function PageControls({ meta, onChange }: { meta?: Collection<unknown>['meta']; onChange: (cursor: string) => void }) {
  const { locale } = useLocale()
  const text = copy[locale]
  const previous = meta?.prev_cursor
  const next = meta?.next_cursor
  return <div className="flex flex-wrap gap-2">
    {previous && <Button onClick={() => onChange(previous)}>{text.previous}</Button>}
    {next && <Button onClick={() => onChange(next)}>{text.next}</Button>}
  </div>
}

function Evidence({ shop, owner, onChange }: { shop: Shop; owner: boolean; onChange: (shop: Shop) => void }) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState(false)
  const [busy, setBusy] = useState(false)

  async function upload(form: HTMLFormElement) {
    if (!file) return
    setError(false)
    setBusy(true)
    const payload = new FormData()
    payload.append('document', file)
    try {
      onChange(await write<Shop>('post', '/api/v1/shop/shops/' + shop.id + '/documents', payload))
      form.reset()
      setFile(null)
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  async function download(documentRecord: ShopDocument) {
    setError(false)
    try {
      const response = await api.get<Blob>('/api/v1/shops/' + shop.id + '/documents/' + documentRecord.id, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      const extension = documentRecord.media_type === 'application/pdf' ? 'pdf' : documentRecord.media_type === 'image/png' ? 'png' : 'jpg'
      link.href = url
      link.download = documentRecord.id + '.' + extension
      link.click()
      window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      setError(true)
    }
  }

  return <Card className="p-5">
    <h3 className="mb-3 text-lg font-bold">{text.documents}</h3>
    {shop.documents?.length ? <ul className="mb-4 grid gap-2">
      {shop.documents.map(item => <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
        <bdi>{item.media_type} · v{item.revision}</bdi>
        <span>{text[item.status]}</span>
        {item.status === 'available' && <Button onClick={() => void download(item)}>{text.download}</Button>}
      </li>)}
    </ul> : <p className="mb-3 text-muted">{text.noRecords}</p>}
    {owner && ['pending', 'rejected'].includes(shop.status) && <form className="grid gap-3" onSubmit={event => {
      event.preventDefault()
      void upload(event.currentTarget)
    }}>
      <Input label={text.file} required type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={event => setFile(event.target.files?.[0] ?? null)} />
      <div><Button type="submit" disabled={busy || !file}>{text.upload}</Button></div>
    </form>}
    {error && <p role="alert" className="mt-2 text-red-800">{t.failed}</p>}
  </Card>
}

function DebtInbox({ shop, admin }: { shop: Shop; admin: boolean }) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [cursor, setCursor] = useState('')
  const [revision, setRevision] = useState(0)
  const [error, setError] = useState(false)
  const [busy, setBusy] = useState(false)
  const base = '/api/v1/shops/' + shop.id + '/debt-requests'
  const result = useResource<Collection<DebtRequest>>(withCursor(base, cursor), revision)

  async function send(path: string, input: object, form?: HTMLFormElement) {
    setBusy(true)
    setError(false)
    try {
      await write<DebtRequest>('post', path, input)
      form?.reset()
      setRevision(value => value + 1)
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return <Card className="p-5">
    <h3 className="mb-3 text-lg font-bold">{text.inbox}</h3>
    {admin && shop.status === 'verified' && <form className="mb-4 grid gap-3" onSubmit={event => {
      event.preventDefault()
      const form = event.currentTarget
      const values = new FormData(form)
      void send('/api/v1/admin/shops/' + shop.id + '/debt-requests', Object.fromEntries(values), form)
    }}>
      <Input label={text.reference} name="reference" required maxLength={100} pattern="[A-Za-z0-9_\-]{1,100}" />
      <Input label={text.amount} name="amount_minor" required inputMode="numeric" pattern="[1-9][0-9]{0,11}" />
      <div><Button type="submit" disabled={busy}>{text.newRequest}</Button></div>
    </form>}
    {result?.failed || error ? <p role="alert" className="text-red-800">{t.failed}</p> : !result?.data && <p role="status">{t.loading}</p>}
    {result?.data?.data.length === 0 && <p className="text-muted">{text.noRecords}</p>}
    {result?.data?.data.map(debt => <div key={debt.id} className="border-t border-line py-3">
      <p className="font-semibold"><bdi>{debt.reference}</bdi> · <bdi>{debt.amount_minor} {locale === 'ar' ? 'سنتيم' : 'centimes'} MAD</bdi></p>
      <p className="text-sm text-muted">{text[debt.status]}</p>
      {debt.reason && <p>{debt.reason}</p>}
      {!admin && shop.status === 'verified' && debt.status === 'pending' && <form className="mt-2 grid gap-2" onSubmit={event => {
        event.preventDefault()
        const form = event.currentTarget
        const values = new FormData(form)
        const submitter = (event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null
        const decision = submitter?.value ?? 'confirmed'
        if (decision === 'disputed' && !String(values.get('reason') ?? '').trim()) { setError(true); return }
        void send('/api/v1/shop/shops/' + shop.id + '/debt-requests/' + debt.id + '/decision', { decision, reason: values.get('reason') })
      }}>
        <Input label={text.reason} name="reason" maxLength={500} />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" value="confirmed" disabled={busy}>{text.confirm}</Button>
          <Button type="submit" value="disputed" disabled={busy}>{text.dispute}</Button>
        </div>
      </form>}
    </div>)}
    <PageControls meta={result?.data?.meta} onChange={setCursor} />
  </Card>
}

function Review({ shop, onChange }: { shop: Shop; onChange: (shop: Shop) => void }) {
  const { locale } = useLocale()
  const text = copy[locale]
  const choices = shop.status === 'pending' ? ['verified', 'rejected'] as const
    : shop.status === 'verified' ? ['suspended'] as const
    : shop.status === 'suspended' ? ['verified'] as const : []
  const [decision, setDecision] = useState<string>(choices[0] ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)

  async function submit(reason: FormDataEntryValue | null) {
    setBusy(true)
    setError(false)
    try {
      onChange(await write<Shop>('post', '/api/v1/admin/shops/' + shop.id + '/review', { decision, reason, revision: shop.revision }))
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  if (choices.length === 0) return null
  return <Card className="p-5">
    <h3 className="mb-3 text-lg font-bold">{text.shopReview}</h3>
    <form className="grid gap-3" onSubmit={event => {
      event.preventDefault()
      void submit(new FormData(event.currentTarget).get('reason'))
    }}>
      <label className="grid gap-1 text-sm font-semibold">{text.shopReview}
        <select className={selectClass} value={decision} onChange={event => setDecision(event.target.value)}>
          {choices.map(choice => <option key={choice} value={choice}>{choice === 'verified' ? text.verify : choice === 'rejected' ? text.reject : text.suspend}</option>)}
        </select>
      </label>
      <Input label={text.reason} name="reason" required={decision !== 'verified'} maxLength={500} />
      <div><Button type="submit" disabled={busy}>{text.save}</Button></div>
      {error && <p role="alert" className="text-red-800">{text.invalid}</p>}
    </form>
  </Card>
}

export function ShopDashboard({ admin = false }: { admin?: boolean }) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [selected, setSelected] = useState('')
  const [cursor, setCursor] = useState('')
  const [revision, setRevision] = useState(0)
  const [params] = useSearchParams()
  const city = admin ? params.get('city_id') : null
  const filters = new URLSearchParams({ ...(cursor && { cursor }), ...(city && { city_id: city }) })
  const result = useResource<Collection<Shop>>('/api/v1/' + (admin ? 'admin/shops' : 'shop/shops') + '?' + filters, revision)
  const detail = useResource<{ data: Shop }>(selected ? '/api/v1/shops/' + selected : null, revision)
  const [updated, setUpdated] = useState<Shop | null>(null)
  const shop = detail?.data?.data ?? (updated?.id === selected ? updated : undefined)

  function saved(record: Shop) {
    setUpdated(record)
    setSelected(record.id)
    setRevision(value => value + 1)
  }

  return <section className="mt-6 grid gap-4">
    <h2 className="display text-3xl">{admin ? text.shopReview : text.shops}</h2>
    <Card className="grid gap-3 p-5">
      <label className="grid gap-1 text-sm font-semibold">{text.selectShop}
        <select className={selectClass} value={selected} onChange={event => { setUpdated(null); setSelected(event.target.value) }}>
          <option value="">{admin ? text.choose : text.register}</option>
          {selected && updated?.id === selected && !result?.data?.data.some(item => item.id === selected)
            && <option value={selected}>{updated.name} · {text[updated.status]}</option>}
          {result?.data?.data.map(item => <option key={item.id} value={item.id}>{item.name} · {text[item.status]}</option>)}
        </select>
      </label>
      <PageControls meta={result?.data?.meta} onChange={value => { setCursor(value); setSelected(''); setUpdated(null) }} />
      <div><Button onClick={() => { setUpdated(null); setRevision(value => value + 1) }}>{text.refresh}</Button></div>
      {result?.failed || detail?.failed ? <p role="alert" className="text-red-800">{t.failed}</p>
        : (!result?.data || (selected && !shop)) && <p role="status">{t.loading}</p>}
      {admin && result?.data?.data.length === 0 && <p>{text.noRecords}</p>}
    </Card>
    {!admin && !selected && <ShopForm onSave={saved} />}
    {shop && <>
      <Card className="p-5">
        <h3 className="mb-2 text-lg font-bold">{text.shopProfile}</h3>
        <p className="font-semibold">{shop.name} · {text[shop.status]}</p>
        {shop.location && <p>{[shop.location.region, shop.location.city, shop.location.locality]
          .map(place => place?.[locale === 'ar' ? 'name_ar' : 'name_fr']).filter(Boolean).join(' · ')}</p>}
        <p><bdi>{shop.contact_phone}</bdi>{shop.contact_email && <> · <bdi>{shop.contact_email}</bdi></>}</p>
        {shop.review_reason && <p>{shop.review_reason}</p>}
      </Card>
      {!admin && ['pending', 'rejected', 'verified'].includes(shop.status) && <ShopForm key={shop.id + shop.revision} shop={shop} onSave={saved} />}
      <Evidence key={shop.id + shop.revision} shop={shop} owner={!admin} onChange={saved} />
      {admin && <Review key={shop.id + shop.status} shop={shop} onChange={saved} />}
      <DebtInbox key={shop.id} shop={shop} admin={admin} />
    </>}
  </section>
}
