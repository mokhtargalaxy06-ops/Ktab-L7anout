import { useLocale } from '../../app/locale'
import copy from './copy'
import { selectClass, useResource, type Collection, type Locations, type Place } from './shopApi'

type Props = { value: Locations; onChange: (value: Locations) => void; through?: 'region' | 'city' | 'locality'; admin?: boolean; revision?: number }

export function GeographySelect({ value, onChange, through = 'locality', admin = false, revision = 0 }: Props) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const prefix = admin ? '/api/v1/admin/geography/' : '/api/v1/'
  const regions = useResource<Collection<Place>>(prefix + 'regions', revision)
  const cities = useResource<Collection<Place>>(value.region_id && through !== 'region' ? prefix + 'cities?region_id=' + value.region_id : null, revision)
  const localities = useResource<Collection<Place>>(value.city_id && through === 'locality' ? prefix + 'localities?city_id=' + value.city_id : null, revision)

  return <div className="grid gap-3 sm:grid-cols-2">
    <label className="grid gap-1 text-sm font-semibold">{text.region}
      <select required className={selectClass} value={value.region_id}
        onChange={event => onChange({ region_id: event.target.value, city_id: '', locality_id: null })}>
        <option value="">{text.choose}</option>
        {regions?.data?.data.map(place => <option key={place.id} value={place.id}>{place[locale === 'ar' ? 'name_ar' : 'name_fr']}</option>)}
      </select>
    </label>
    {through !== 'region' && <label className="grid gap-1 text-sm font-semibold">{text.city}
      <select required className={selectClass} disabled={!value.region_id || !cities?.data} value={value.city_id}
        onChange={event => onChange({ ...value, city_id: event.target.value, locality_id: null })}>
        <option value="">{text.choose}</option>
        {cities?.data?.data.map(place => <option key={place.id} value={place.id}>{place[locale === 'ar' ? 'name_ar' : 'name_fr']}</option>)}
      </select>
    </label>}
    {through === 'locality' && <label className="grid gap-1 text-sm font-semibold">{text.locality}
      <select className={selectClass} disabled={!localities?.data?.data.length} value={value.locality_id ?? ''}
        onChange={event => onChange({ ...value, locality_id: event.target.value || null })}>
        <option value="">{text.choose}</option>
        {localities?.data?.data.map(place => <option key={place.id} value={place.id}>{place[locale === 'ar' ? 'name_ar' : 'name_fr']}</option>)}
      </select>
    </label>}
    {(regions?.failed || cities?.failed || localities?.failed) && <p role="alert" className="text-red-800">{t.failed}</p>}
  </div>
}
