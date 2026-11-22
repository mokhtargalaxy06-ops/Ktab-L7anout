import { BadgeCheck } from 'lucide-react'
import { useLocale } from '../../app/locale'
import { ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { discoveryCopy } from './copy'
import { mad, type PublicCase, type UrgencyPolicy } from './types'

export function FundingMeter({ record }: { record: PublicCase }) {
  const { locale } = useLocale()
  const text = discoveryCopy[locale]

  return <div className="grid gap-2">
    <div className="flex items-center justify-between gap-2 text-sm">
      <span>{text.collected} <bdi className="font-semibold">{mad(record.collected_minor, locale)}</bdi></span>
      <span className="font-bold"><bdi>{record.progress}%</bdi></span>
    </div>
    <progress value={record.progress} max={100} aria-label={text.collected} className="h-2 w-full appearance-none overflow-hidden rounded-full accent-forest [&::-webkit-progress-bar]:bg-mint [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-forest" />
  </div>
}

export function DiscoveryCaseCard({ record }: { record: PublicCase }) {
  const { locale } = useLocale()
  const text = discoveryCopy[locale]
  const key = locale === 'ar' ? 'name_ar' : 'name_fr'

  return <Card className="grid gap-4 p-5 shadow-[0_10px_30px_-22px_#12352466]">
    <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
      <span className="inline-flex items-center gap-1.5 text-forest"><BadgeCheck aria-hidden="true" size={16} />{text.verified}</span>
      {record.urgent && <span className="rounded-md bg-amber-50 px-2 py-1 text-amber-900">{text.urgent}</span>}
    </div>
    <div>
      <h3 className="text-xl font-bold">{record.city[key]}</h3>
      <p className="text-sm text-muted">{record.region[key]}</p>
    </div>
    <p className="text-sm text-muted">{text.debt} <bdi className="font-semibold text-ink">{mad(record.amount_minor, locale)}</bdi></p>
    <FundingMeter record={record} />
    <div><ButtonLink variant="secondary" to={'/cases/' + record.id}>{text.view}</ButtonLink></div>
  </Card>
}

export function UrgencyExplanation({ policy }: { policy: UrgencyPolicy }) {
  const { locale } = useLocale()
  const text = discoveryCopy[locale]
  const keys = Object.keys(policy.weights) as (keyof typeof text.weights)[]

  return <Card className="grid gap-4 p-5 sm:p-7">
    <h2 className="display text-2xl">{text.methodology}</h2>
    <p className="text-muted">{text.explanation}</p>
    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {keys.map(key => <div key={key} className="rounded-lg bg-surface p-3">
        <dt className="text-sm text-muted">{text.weights[key]}</dt><dd className="text-xl font-bold text-forest"><bdi>{policy.weights[key]}%</bdi></dd>
      </div>)}
    </dl>
    <p className="text-sm text-muted">{text.threshold} <bdi>{policy.urgent_threshold}/100</bdi> · {text.ageCap} <bdi>{policy.age_cap_days}</bdi> · {text.balanceCap} <bdi>{mad(policy.balance_cap_minor, locale)}</bdi></p>
    <p className="text-sm text-muted">{text.tie}</p>
  </Card>
}
