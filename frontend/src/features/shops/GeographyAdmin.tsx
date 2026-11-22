import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import copy from './copy'
import { GeographySelect } from './GeographySelect'
import { selectClass, useResource, write, type Collection, type Locations, type Place } from './shopApi'

const levels = ['regions', 'cities', 'localities'] as const
type Level = typeof levels[number]

function PlaceForm({ path, parent, place, onSave }: { path: string; parent?: string; place?: Place; onSave: () => void }) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)

  async function submit(form: HTMLFormElement) {
    const values = new FormData(form)
    const input = Object.fromEntries(values)
    setBusy(true)
    setError(false)
    try {
      await write<Place>(place ? 'patch' : 'post', path + (place ? '/' + place.id : ''), {
        ...input, active: values.has('active'), ...(!place && parent ? { parent_id: parent } : {}),
      })
      if (!place) form.reset()
      onSave()
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return <form className="grid gap-3" onSubmit={event => { event.preventDefault(); void submit(event.currentTarget) }}>
    {!place && <Input label={text.code} name="code" required maxLength={40} dir="ltr" />}
    <Input label={text.frenchName} name="name_fr" defaultValue={place?.name_fr} required maxLength={150} lang="fr" dir="ltr" />
    <Input label={text.arabicName} name="name_ar" defaultValue={place?.name_ar} required maxLength={150} lang="ar" dir="rtl" />
    <Input label={text.source} name="source_url" type="url" defaultValue={place?.source_url} required maxLength={500} dir="ltr" />
    <Input label={text.date} name="source_date" type="date" defaultValue={place?.source_date} required />
    <label className="flex items-center gap-2"><input type="checkbox" name="active" defaultChecked={place?.active ?? true} />{text.active}</label>
    <div><Button type="submit" disabled={busy}>{busy ? t.loading : text.save}</Button></div>
    {error && <p role="alert" className="text-red-800">{text.invalid}</p>}
  </form>
}

export function GeographyAdmin() {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [level, setLevel] = useState<Level>('regions')
  const [location, setLocation] = useState<Locations>({ region_id: '', city_id: '', locality_id: null })
  const [selected, setSelected] = useState('')
  const [revision, setRevision] = useState(0)
  const parent = level === 'cities' ? location.region_id : level === 'localities' ? location.city_id : undefined
  const path = '/api/v1/admin/geography/' + level
  const query = level === 'cities' ? '?region_id=' : '?city_id='
  const result = useResource<Collection<Place>>(level === 'regions' ? path : parent ? path + query + parent : null, revision)
  const place = result?.data?.data.find(item => item.id === selected)

  return <Card className="mt-6 grid gap-4 p-5">
    <h2 className="display text-3xl">{text.geography}</h2>
    <label className="grid gap-1 text-sm font-semibold">{text.geography}
      <select className={selectClass} value={level} onChange={event => { setLevel(event.target.value as Level); setSelected('') }}>
        {levels.map((item, index) => <option key={item} value={item}>{[text.region, text.city, text.locality][index]}</option>)}
      </select>
    </label>
    {level !== 'regions' && <GeographySelect admin through={level === 'cities' ? 'region' : 'city'} value={location} revision={revision}
      onChange={next => { setLocation(next); setSelected('') }} />}
    {(level === 'regions' || parent) && <>
      {result?.failed && <p role="alert" className="text-red-800">{t.failed}</p>}
      <label className="grid gap-1 text-sm font-semibold">{text.selectRecord}
        <select className={selectClass} value={selected} onChange={event => setSelected(event.target.value)}>
          <option value="">{text.reset}</option>
          {result?.data?.data.map(item => <option key={item.id} value={item.id}>{item[locale === 'ar' ? 'name_ar' : 'name_fr']}</option>)}
        </select>
      </label>
      {(!selected || place) && <PlaceForm key={level + parent + selected + revision} path={path} parent={parent || undefined}
        place={place} onSave={() => setRevision(value => value + 1)} />}
    </>}
  </Card>
}
