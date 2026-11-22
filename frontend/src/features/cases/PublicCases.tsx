import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useLocale } from '../../app/locale'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import { discoveryCopy } from '../discovery/copy'
import { DiscoveryCaseCard, UrgencyExplanation } from '../discovery/DiscoveryCards'
import { toMajor, toMinor, type Discovery, type PublicCase } from '../discovery/types'
import { GeographySelect } from '../shops/GeographySelect'
import { selectClass, useResource } from '../shops/shopApi'
import { donationCopy } from '../donations/copy'

type Catalog = { data: PublicCase[]; meta: { page: number; last_page: number; total: number } }
const statuses = ['open', 'funded', 'settled'] as const

function Filters({ params, urgent, apply }: { params: URLSearchParams; urgent: boolean; apply: (value: URLSearchParams) => void }) {
  const { locale } = useLocale()
  const text = discoveryCopy[locale]
  const [location, setLocation] = useState({ region_id: params.get('region_id') ?? '', city_id: params.get('city_id') ?? '', locality_id: null as string | null })
  return <Card className="p-5">
    <form className="grid gap-4" onSubmit={event => {
      event.preventDefault()
      const input = new FormData(event.currentTarget)
      const next = new URLSearchParams()
      for (const field of ['q', 'status', 'urgent']) {
        const value = String(input.get(field) ?? '').trim()
        if (value) next.set(field, value)
      }
      for (const key of ['min_minor', 'max_minor']) {
        const value = String(input.get(key) ?? '').trim()
        if (value) next.set(key, toMinor(value))
      }
      if (location.region_id) next.set('region_id', location.region_id)
      if (location.city_id) next.set('city_id', location.city_id)
      apply(next)
    }}>
      <Input label={text.search} name="q" type="search" maxLength={100} defaultValue={params.get('q') ?? ''} />
      <GeographySelect through="city" required={false} value={location} onChange={setLocation} />
      <div className="grid gap-3 sm:grid-cols-3">
        <Input label={text.minimum} name="min_minor" inputMode="decimal" pattern="[0-9]{1,10}([.,][0-9]{1,2})?" defaultValue={toMajor(params.get('min_minor'))} />
        <Input label={text.maximum} name="max_minor" inputMode="decimal" pattern="[0-9]{1,10}([.,][0-9]{1,2})?" defaultValue={toMajor(params.get('max_minor'))} />
        <label className="grid gap-1.5 text-sm font-semibold">{text.status}
          <select name="status" defaultValue={params.get('status') ?? ''} className={selectClass}>
            <option value="">{text.all}</option>
            {statuses.map(status => <option key={status} value={status}>{text[status]}</option>)}
          </select>
        </label>
      </div>
      {!urgent && <label className="flex items-center gap-2"><input name="urgent" value="1" type="checkbox" defaultChecked={params.get('urgent') === '1'} />{text.urgentOnly}</label>}
      <div className="flex flex-wrap gap-2"><Button type="submit">{text.apply}</Button>
        <ButtonLink to={urgent ? '/urgent' : '/cases'} variant="secondary">{text.reset}</ButtonLink>
      </div>
    </form>
  </Card>
}

export function PublicCases({ urgent = false }: { urgent?: boolean }) {
  const { locale, t } = useLocale()
  const text = discoveryCopy[locale]
  const [params, setParams] = useSearchParams()
  const [retry, setRetry] = useState(0)
  const query = new URLSearchParams(params)
  if (urgent) query.set('urgent', '1')
  const result = useResource<Catalog>('/api/v1/cases' + (query.size ? '?' + query.toString() : ''), retry)
  const overview = useResource<{ data: Discovery }>(urgent ? '/api/v1/discovery' : null, retry)
  const filterKey = new URLSearchParams(params)
  filterKey.delete('page')
  const meta = result?.data?.meta

  function page(number: number) {
    const next = new URLSearchParams(params)
    if (number > 1) next.set('page', String(number))
    else next.delete('page')
    setParams(next)
  }

  return <section className="container-shell grid gap-5 py-10 sm:py-14">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="display text-4xl sm:text-5xl">{urgent ? text.urgentTitle : text.browse}</h1>
      <ButtonLink to={urgent ? '/cases' : '/urgent'} variant="secondary">{urgent ? text.browse : text.urgentTitle}</ButtonLink>
    </div>
    {overview?.data && <UrgencyExplanation policy={overview.data.data.urgency_policy} />}
    {overview?.data?.data.simulated && <div><ButtonLink to="/donations/new">{donationCopy[locale].donate}</ButtonLink></div>}
    {overview?.failed && <p role="alert" className="text-red-800">{t.failed} <Button onClick={() => setRetry(value => value + 1)}>{text.retry}</Button></p>}
    <Filters key={filterKey.toString()} params={params} urgent={urgent} apply={setParams} />
    {result?.failed ? <p role="alert" className="text-red-800">{t.failed} <Button onClick={() => setRetry(value => value + 1)}>{text.retry}</Button></p>
      : !result?.data ? <Loading label={t.loading} /> : result.data.data.length === 0 && <p>{text.empty}</p>}
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {result?.data?.data.map(record => <DiscoveryCaseCard key={record.id} record={record} />)}
    </div>
    {meta && meta.last_page > 1 && <div className="flex flex-wrap items-center gap-3">
      <Button disabled={meta.page <= 1} onClick={() => page(meta.page - 1)}>{text.previous}</Button>
      <p>{text.page} <bdi>{meta.page}/{meta.last_page}</bdi></p>
      <Button disabled={meta.page >= meta.last_page} onClick={() => page(meta.page + 1)}>{text.next}</Button>
    </div>}
  </section>
}
