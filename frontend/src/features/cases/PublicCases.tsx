import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { Card } from '../../shared/ui/Card'
import { PageControls } from '../shops/ShopDashboard'
import { useResource, withCursor, type Collection } from '../shops/shopApi'
import { caseCopy } from './copy'

type PublicCase = { id: string; region: { name_fr: string; name_ar: string }; amount_minor: string; currency: 'MAD' }

export function PublicCases() {
  const { locale, t } = useLocale()
  const [cursor, setCursor] = useState('')
  const result = useResource<Collection<PublicCase>>(withCursor('/api/v1/cases', cursor))
  const text = caseCopy[locale]
  const format = new Intl.NumberFormat(locale === 'ar' ? 'ar-MA' : 'fr-MA')

  return <section className="container-shell grid gap-5 py-12">
    <h1 className="display text-4xl">{text.publicTitle}</h1>
    {result?.failed ? <p role="alert">{t.failed}</p> : !result?.data ? <p role="status">{t.loading}</p>
      : result.data.data.length === 0 && <p>{text.publicEmpty}</p>}
    <div className="grid gap-3 md:grid-cols-2">
      {result?.data?.data.map(record => <Card key={record.id} className="grid gap-2 p-5">
        <h2 className="text-lg font-bold">{record.region[locale === 'ar' ? 'name_ar' : 'name_fr']}</h2>
        <p>{text.publicAmount} <bdi>{format.format(BigInt(record.amount_minor))} {record.currency}</bdi></p>
      </Card>)}
    </div>
    <PageControls meta={result?.data?.meta} onChange={setCursor} />
  </section>
}
