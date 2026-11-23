import { useState, type FormEvent } from 'react'
import { NavLink, Route, Routes, useSearchParams } from 'react-router'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { AdminAccounts } from '../auth/AdminAccounts'
import { CaseDashboard } from '../cases/CaseDashboard'
import { mad, toMinor } from '../discovery/types'
import { SettlementDashboard } from '../settlements/SettlementPages'
import { GeographyAdmin } from '../shops/GeographyAdmin'
import { ShopDashboard } from '../shops/ShopDashboard'
import { selectClass, useResource } from '../shops/shopApi'
import { adminCopy } from './copy'

const base = '/api/v1/admin'
const workspace = '/dashboard/administrator'
type Page<T> = { data: T[]; current_page: number; last_page: number }
type Fields = Record<string, string | number | null>
const sections = ['overview', 'cases', 'users', 'shops', 'finance', 'regions', 'audit'] as const
const kinds = ['donations', 'allocations', 'transactions', 'refunds'] as const
const idPattern = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'

function Pager({ page, change }: { page?: Page<unknown>; change: (next: number) => void }) {
  const { locale } = useLocale()
  const text = adminCopy[locale]
  return page && <div className="flex gap-2">
    {page.current_page > 1 && <Button variant="secondary" onClick={() => change(page.current_page - 1)}>{text.previous}</Button>}
    {page.current_page < page.last_page && <Button variant="secondary" onClick={() => change(page.current_page + 1)}>{text.next}</Button>}
  </div>
}

function Overview() {
  const { locale, t } = useLocale()
  const text = adminCopy[locale]
  const result = useResource<{ data: Record<keyof typeof text.metrics, string | number> & { simulated: boolean } }>(base + '/overview')
  const stats = result?.data?.data
  if (!stats) return <p role={result?.failed ? 'alert' : 'status'}>{result?.failed ? t.failed : t.loading}</p>
  return <section className="space-y-4">
    <h2 className="display text-3xl">{text.overview}</h2>
    {stats.simulated && <p>{locale === 'fr' ? 'Indicateurs financiers simulés' : 'مؤشرات مالية محاكاة'}</p>}
    <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
      {(Object.keys(text.metrics) as (keyof typeof text.metrics)[]).map(key => <Card className="p-4" key={key}>
        <dt className="text-sm text-muted">{text.metrics[key]}</dt><dd className="text-2xl font-bold [overflow-wrap:anywhere]">
          <bdi>{key === 'donations_minor' ? mad(String(stats[key]), locale) : new Intl.NumberFormat(locale).format(Number(stats[key]))}</bdi>
        </dd>
      </Card>)}
    </dl>
  </section>
}

function Records({ audit = false }: { audit?: boolean }) {
  const { locale, t } = useLocale()
  const text = adminCopy[locale]
  const [params] = useSearchParams()
  const [kind, setKind] = useState<typeof kinds[number]>('donations')
  const [search, setSearch] = useState(() => new URLSearchParams(audit && params.has('target') ? { target_id: params.get('target')! } : {}))
  const [page, changePage] = useState(1)
  const [version, refresh] = useState(0)
  const [selected, select] = useState<(Fields & { changes?: Fields }) | null>(null)
  const [returnKey, setReturnKey] = useState('')
  const [returnError, setReturnError] = useState(false)
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState(false)
  const result = useResource<Page<Fields & { changes?: Fields }>>(base + (audit ? '/audit' : '/financial-search/' + kind)
    + '?' + search + '&page=' + page, version)
  const filter = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const values = new URLSearchParams()
    for (const [name, value] of new FormData(event.currentTarget)) if (typeof value === 'string' && value.trim()) values.set(name, value.trim())
    setSearch(values)
    changePage(1)
  }
  const saveReturn = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!selected || busy) return
    const values = new FormData(event.currentTarget)
    setBusy(true)
    setReturnError(false)
    try {
      await getCsrfCookie()
      await api.post(base + '/donations/' + selected.id + '/simulate-return', {
        kind: values.get('kind'), amount_minor: toMinor(String(values.get('amount'))),
        current_password: values.get('password'), reason: values.get('reason'),
      }, { headers: { 'Idempotency-Key': returnKey } })
      select(null)
      setNotice(true)
      refresh(value => value + 1)
    } catch { setReturnError(true) } finally { setBusy(false) }
  }
  return <section className="space-y-4">
    <h2 className="display text-3xl">{audit ? text.audit : text.finance}</h2>
    {!audit && <select className={selectClass} aria-label={text.finance} value={kind} onChange={event => {
      setKind(event.target.value as typeof kind); changePage(1); setSearch(new URLSearchParams())
    }}>{kinds.map(key => <option key={key} value={key}>{text[key]}</option>)}</select>}
    <form key={kind} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]" onSubmit={filter}>
      <Input label={audit ? text.actor : text.reference} name={audit ? 'actor_id' : 'id'} pattern={idPattern} />
      <Input label={audit ? text.target : text.related} name={audit ? 'target_id' : 'related_id'} pattern={idPattern}
        defaultValue={audit ? search.get('target_id') ?? '' : ''} />
      <Button type="submit">{text.search}</Button>
    </form>
    {notice && <p role="status">{text.returnNotice}</p>}
    {result?.failed ? <p role="alert">{result.status === 404 ? text.financeUnavailable : t.failed}</p>
      : !result?.data ? <p role="status">{t.loading}</p> : !result.data.data.length ? <p>{text.empty}</p> :
      <div className="relative min-w-0 overflow-x-auto rounded-xl border border-line bg-white" tabIndex={0} role="region" aria-label={audit ? text.audit : text.finance}>
        <table className="w-full text-start text-sm">
          <thead><tr className="bg-mint"><th className="p-3 text-start">{text.reference}</th><th className="p-3 text-start">{audit ? text.action : text.amount}</th>
            <th className="p-3 text-start">{text.date}</th><th className="p-3"><span className="sr-only">{text.detail}</span></th></tr></thead>
          <tbody>{result.data.data.map(row => <tr key={row.id} className="border-t border-line">
            <td className="p-3"><bdi>{row.id}</bdi></td><td className="p-3">{audit ? row.action : mad(String(row.amount_minor), locale)}</td>
            <td className="p-3">{new Date(String(row.created_at)).toLocaleDateString(locale)}</td>
            <td className="p-3"><Button variant="secondary" onClick={() => {
              select(row); setReturnKey(crypto.randomUUID()); setReturnError(false); setNotice(false)
            }}>{text.detail}</Button></td>
          </tr>)}</tbody>
        </table>
      </div>}
    <Pager page={result?.data} change={changePage} />
    <Modal title={text.detail} open={selected !== null} closeLabel={t.close} onClose={() => { if (!busy) select(null) }}>
      {selected && <dl className="grid grid-cols-1 gap-x-2 gap-y-1 text-sm sm:grid-cols-[auto_minmax(0,1fr)]">{Object.entries(selected).flatMap<[string, unknown]>(([key, value]) =>
        key === 'changes' && value ? Object.entries(value) : [[key, value]]).filter(([, value]) => value != null)
        .map(([key, value]) => <div key={key} className="contents"><dt>{key.replaceAll('_', ' ')}</dt><dd className="break-all"><bdi>
          {typeof value === 'object' ? JSON.stringify(value) : String(value)}
        </bdi></dd></div>)}</dl>}
      {!audit && kind === 'donations' && selected?.payment_status === 'confirmed' && <form className="mt-4 grid gap-3" onSubmit={saveReturn}>
        <select name="kind" aria-label={text.action} className={selectClass}><option value="refund">{text.refund}</option><option value="reversal">{text.reversal}</option></select>
        <Input label={text.amount} name="amount" pattern="[0-9]{1,10}([.,][0-9]{1,2})?" required inputMode="decimal" />
        <Input label={text.reason} name="reason" maxLength={500} required />
        <Input label={text.password} name="password" type="password" required autoComplete="current-password" />
        {returnError && <p role="alert">{t.failed}</p>}
        <Button type="submit" disabled={busy}>{text.recordReturn}</Button>
      </form>}
    </Modal>
    {!audit && <SettlementDashboard key={version} admin />}
  </section>
}

type Region = { id: string; name_fr: string; name_ar: string; cases: number; verified_shops: number }
type City = Region & { shops: number; region_ar: string; region_fr: string }

function RegionalActivity() {
  const { locale, t } = useLocale()
  const text = adminCopy[locale]
  const [region, setRegion] = useState('')
  const [uncovered, setUncovered] = useState(false)
  const [page, changePage] = useState(1)
  const query = new URLSearchParams({ page: String(page), ...(region && { region_id: region }), ...(uncovered && { coverage: 'uncovered' }) })
  const result = useResource<{ data: { regions: Region[]; cities: Page<City> } }>(base + '/regions/activity?' + query)
  const data = result?.data?.data
  if (!data) return <p role={result?.failed ? 'alert' : 'status'}>{result?.failed ? t.failed : t.loading}</p>
  const maximum = Math.max(1, ...data.regions.map(item => item.cases))
  return <section className="space-y-4">
    <h2 className="display text-3xl">{text.regions}</h2>
    <Card className="grid gap-2 p-4" aria-label={text.activity}>
      {data.regions.map(item => <div key={item.id} className="grid items-center gap-2 sm:grid-cols-[1fr_2fr_auto]">
        <span>{item[locale === 'ar' ? 'name_ar' : 'name_fr']}</span>
        <meter className="w-full" min={0} max={maximum} value={item.cases} aria-label={text.cases} />
        <ButtonLink variant="secondary" to={workspace + '/cases?region_id=' + item.id}>{item.cases} {text.cases}</ButtonLink>
      </div>)}
    </Card>
    <select className={selectClass} aria-label={text.region} value={region} onChange={event => { setRegion(event.target.value); changePage(1) }}>
      <option value="">{text.all}</option>{data.regions.map(item => <option key={item.id} value={item.id}>{item[locale === 'ar' ? 'name_ar' : 'name_fr']}</option>)}
    </select>
    <label className="flex gap-2"><input type="checkbox" checked={uncovered} onChange={event => { setUncovered(event.target.checked); changePage(1) }} />{text.coverage}</label>
    {!data.cities.data.length && <p>{text.empty}</p>}
    <div className="grid gap-2 md:grid-cols-2">{data.cities.data.map(city => <Card key={city.id} className="p-4">
      <h3 className="font-bold">{city[locale === 'ar' ? 'name_ar' : 'name_fr']}</h3>
      <p>{city.cases} {text.cases} · {city.verified_shops}/{city.shops} {text.verified}</p>
      <div className="flex gap-2">
        <ButtonLink variant="secondary" to={workspace + '/cases?city_id=' + city.id}>{text.cases}</ButtonLink>
        <ButtonLink variant="secondary" to={workspace + '/shops?city_id=' + city.id}>{text.shops}</ButtonLink>
      </div>
    </Card>)}</div>
    <Pager page={data.cities} change={changePage} />
    <GeographyAdmin />
  </section>
}

export function AdminWorkspace() {
  const { locale } = useLocale()
  const text = adminCopy[locale]
  return <section className="container-shell py-8">
    <h1 className="display mb-5 text-3xl sm:text-4xl">{text.title}</h1>
    <div className="grid min-w-0 grid-cols-1 gap-5 lg:grid-cols-[13rem_minmax(0,1fr)]">
      <nav aria-label={text.title} className="flex flex-wrap content-start gap-1 rounded-xl border border-line bg-white p-2 lg:flex-col lg:self-start">
        {sections.map(key => <NavLink key={key} end={key === 'overview'} to={workspace + (key === 'overview' ? '' : '/' + key)}
          className="inline-flex min-h-11 items-center rounded-lg px-3 py-2 font-semibold hover:bg-mint aria-[current=page]:bg-mint">{text[key]}</NavLink>)}
      </nav>
      <div className="min-w-0"><Routes>
        <Route index element={<Overview />} />
        <Route path="cases" element={<CaseDashboard audience="admin" />} />
        <Route path="users" element={<AdminAccounts />} />
        <Route path="shops" element={<ShopDashboard admin />} />
        <Route path="finance" element={<Records key="finance" />} />
        <Route path="regions" element={<RegionalActivity />} />
        <Route path="audit" element={<Records key="audit" audit />} />
      </Routes></div>
    </div>
  </section>
}
