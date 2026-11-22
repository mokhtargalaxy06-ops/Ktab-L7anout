import { useState } from 'react'
import { HandHeart, ReceiptText, ShieldCheck, WalletCards } from 'lucide-react'
import { useLocale } from '../../app/locale'
import { Button, ButtonLink } from '../../shared/ui/Button'
import { Loading } from '../../shared/ui/Loading'
import { useResource } from '../shops/shopApi'
import { discoveryCopy } from './copy'
import { DiscoveryCaseCard, UrgencyExplanation } from './DiscoveryCards'
import { mad, type Discovery, type PublicCase } from './types'

function CaseSection({ title, path, cases, link }: { title: string; path: string; cases: PublicCase[]; link: string }) {
  const { locale } = useLocale()
  return <section className="grid gap-4">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h2 className="display text-3xl sm:text-4xl">{title}</h2>
      <ButtonLink to={path} variant="secondary">{link}</ButtonLink>
    </div>
    {cases.length ? <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {cases.map(record => <DiscoveryCaseCard key={record.id} record={record} />)}
    </div> : <p className="rounded-xl border border-line bg-surface p-5 text-muted">{discoveryCopy[locale].empty}</p>}
  </section>
}

export function HomeDiscovery() {
  const { locale, t } = useLocale()
  const text = discoveryCopy[locale]
  const [retry, setRetry] = useState(0)
  const result = useResource<{ data: Discovery }>('/api/v1/discovery', retry)
  const data = result?.data?.data

  return <div className="border-y border-line bg-surface">
    <div className="container-shell grid gap-10 py-10 md:py-14">
      <section className="grid gap-4">
        <h2 className="display text-3xl sm:text-4xl">{text.impact}</h2>
        {result?.failed ? <div role="alert">{t.failed} <Button onClick={() => setRetry(value => value + 1)}>{text.retry}</Button></div>
          : !data?.statistics ? <Loading label={t.loading} /> : <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {([
              [text.helped, new Intl.NumberFormat(locale === 'ar' ? 'ar-MA' : 'fr-MA').format(data.statistics.families_helped), HandHeart],
              [text.settledTotal, mad(data.statistics.settled_minor, locale), ReceiptText],
              [text.received, mad(data.statistics.received_minor, locale), WalletCards],
              [text.allocated, mad(data.statistics.allocated_minor, locale), ShieldCheck],
            ] as const).map(([label, value, Icon]) => <div key={label} className="grid content-start gap-2 rounded-xl border border-line bg-white p-5">
              <Icon aria-hidden="true" size={22} className="text-forest" />
              <dt className="text-sm font-semibold text-muted">{label}</dt>
              <dd className="text-2xl font-bold text-forest"><bdi>{value}</bdi></dd>
              {label === text.settledTotal && <dd className="text-sm text-muted">{text.settledCases} <bdi>{data.statistics.fully_settled_cases}</bdi></dd>}
            </div>)}
          </dl>}
      </section>
      {data?.statistics && <>
        <CaseSection title={text.recent} path="/cases" cases={data.recent} link={text.browse} />
        <CaseSection title={text.urgentTitle} path="/urgent" cases={data.urgent} link={text.urgentTitle} />
        <UrgencyExplanation policy={data.urgency_policy} />
      </>}
    </div>
  </div>
}
