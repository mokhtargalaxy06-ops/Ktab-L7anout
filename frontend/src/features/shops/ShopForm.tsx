import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import copy from './copy'
import { GeographySelect } from './GeographySelect'
import { write, type Shop, type ShopProfile } from './shopApi'

export function ShopForm({ shop, onSave }: { shop?: Shop; onSave: (shop: Shop) => void }) {
  const { locale } = useLocale()
  const text = copy[locale]
  const [data, setData] = useState<ShopProfile>(() => shop ? {
    name: shop.name, region_id: shop.region_id, city_id: shop.city_id, locality_id: shop.locality_id,
    contact_phone: shop.contact_phone, contact_email: shop.contact_email,
  } : { name: '', region_id: '', city_id: '', locality_id: null, contact_phone: '', contact_email: null })
  const [error, setError] = useState(false)
  const [busy, setBusy] = useState(false)

  async function save() {
    setError(false)
    setBusy(true)
    try {
      onSave(await write<Shop>(shop ? 'patch' : 'post', '/api/v1/shop/shops' + (shop ? '/' + shop.id : ''), data))
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return <Card className="p-5">
    <h2 className="mb-4 text-lg font-bold">{shop ? text.edit : text.register}</h2>
    <form className="grid gap-4" onSubmit={event => { event.preventDefault(); void save() }}>
      <Input label={text.shopName} value={data.name} required maxLength={150} onChange={event => setData({ ...data, name: event.target.value })} />
      <GeographySelect value={data} onChange={location => setData({ ...data, ...location })} />
      <Input label={text.phone} type="tel" dir="ltr" value={data.contact_phone} required maxLength={25}
        onChange={event => setData({ ...data, contact_phone: event.target.value })} />
      <Input label={text.email} type="email" dir="ltr" value={data.contact_email ?? ''}
        onChange={event => setData({ ...data, contact_email: event.target.value || null })} />
      {shop?.status === 'verified' && <p className="text-sm text-muted">{text.evidenceNeeded}</p>}
      {error && <p role="alert" className="text-red-800">{text.invalid}</p>}
      <div><Button type="submit" disabled={busy}>{text.save}</Button></div>
    </form>
  </Card>
}
