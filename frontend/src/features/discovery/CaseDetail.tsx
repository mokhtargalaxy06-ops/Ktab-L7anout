import { useState } from 'react'
import { useParams } from 'react-router'
import { BadgeCheck, Download } from 'lucide-react'
import { useLocale } from '../../app/locale'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Loading } from '../../shared/ui/Loading'
import { useResource } from '../shops/shopApi'
import { api } from '../../shared/api/client'
import { useSession } from '../auth/session'
import shopCopy from '../shops/copy'
import { discoveryCopy } from './copy'
import { FundingMeter } from './DiscoveryCards'
import { donationCopy } from '../donations/copy'
import { mad, type PublicCase } from './types'

function ShopEvidence({ reference }: { reference: string }) {
  const { locale, t } = useLocale()
  const text = shopCopy[locale]
  const [failed, setFailed] = useState(false)
  const path = '/api/v1/donor/cases/' + encodeURIComponent(reference) + '/shop-evidence'
  const result = useResource<{ data: { id: string; media_type: string }[] }>(path)
  const documents = result?.data?.data ?? []

  async function download(id: string, mediaType: string) {
    setFailed(false)
    try {
      const response = await api.get<Blob>(path + '/' + encodeURIComponent(id), { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      const extension = mediaType === 'application/pdf' ? 'pdf' : mediaType === 'image/png' ? 'png' : 'jpg'
      link.download = 'evidence-' + id + '.' + extension
      link.click()
      window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      setFailed(true)
    }
  }

  return <Card className="grid gap-3 p-6 sm:p-8">
    <h2 className="display text-2xl">{text.donorEvidence}</h2>
    {(failed || result?.failed) && <p role="alert">{t.failed}</p>}
    {!documents.length && <div className="flex flex-wrap items-center justify-between gap-3">
      {result?.data && <p className="text-muted">{text.noSharedEvidence}</p>}
      {!result && <p role="status" className="text-muted">{t.loading}</p>}
      <Button disabled><Download aria-hidden="true" size={18} />{text.download}</Button>
    </div>}
    {documents.map((item, index) => <div key={item.id} className="flex flex-wrap items-center justify-between gap-2">
      <span>{text.redacted} {index + 1}</span>
      <Button onClick={() => void download(item.id, item.media_type)}><Download aria-hidden="true" size={18} />{text.download}</Button>
    </div>)}
  </Card>
}

export function CaseDetail() {
  const { reference } = useParams()
  const { locale, t } = useLocale()
  const { user } = useSession()
  const text = discoveryCopy[locale]
  const [retry, setRetry] = useState(0)
  const result = useResource<{ data: PublicCase }>(reference ? '/api/v1/cases/' + encodeURIComponent(reference) : null, retry)
  const record = result?.data?.data
  const key = locale === 'ar' ? 'name_ar' : 'name_fr'
  const dates = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-MA' : 'fr-MA', { dateStyle: 'medium', timeZone: 'UTC' })

  return <section className="container-shell grid gap-5 py-10 sm:py-14">
    <div><ButtonLink variant="secondary" to="/cases">{text.browse}</ButtonLink></div>
    {result?.failed ? <div role="alert"><p>{result.status === 404 ? text.missing : t.failed}</p>
      {result.status !== 404 && <Button onClick={() => setRetry(value => value + 1)}>{text.retry}</Button>}</div>
      : !record ? <Loading label={t.loading} />
      : <>
        <div className="grid gap-5 lg:grid-cols-[1.25fr_1fr]">
          <Card className="grid content-start gap-4 p-6 sm:p-8">
            <p className="inline-flex items-center gap-2 text-sm font-semibold text-forest"><BadgeCheck aria-hidden="true" size={18} />{text.verified}</p>
            <h1 className="display text-4xl">{record.city[key]}</h1>
            <p className="text-muted">{record.region[key]} · {text[record.status]}</p>
            <p className="max-w-prose leading-relaxed">{text.stories[record.story]}</p>
            <p className="break-all text-sm text-muted">{text.reference} <bdi>{record.id}</bdi></p>
          </Card>
          <Card className="grid content-start gap-5 p-6 sm:p-8">
            <h2 className="display text-2xl">{text.debt}</h2>
            <p className="text-3xl font-bold text-forest"><bdi>{mad(record.amount_minor, locale)}</bdi></p>
            <FundingMeter record={record} />
            {record.simulated ? <>
              <p className="text-sm text-amber-900">{donationCopy[locale].banner}</p>
              {record.remaining_minor !== '0' && <div><ButtonLink to={'/donations/new?case=' + record.id}>{donationCopy[locale].donate}</ButtonLink></div>}
            </> : <p className="text-sm text-muted">{t.note}</p>}
          </Card>
        </div>
        {user?.abilities.includes('donations.viewOwn') && <ShopEvidence key={record.id} reference={record.id} />}
        <Card className="grid gap-3 p-6 sm:p-8">
          <h2 className="display text-2xl">{text.updates}</h2>
          <ul className="divide-y divide-line">
            {record.updates?.map(update => <li key={update.type} className="flex flex-wrap justify-between gap-2 py-2">
              <span>{text[update.type]}</span><time dateTime={update.date} className="text-muted">{dates.format(new Date(update.date))}</time>
            </li>)}
          </ul>
        </Card>
      </>}
  </section>
}
