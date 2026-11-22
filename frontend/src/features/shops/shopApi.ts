import { useEffect, useState } from 'react'
import { api, getCsrfCookie } from '../../shared/api/client'

export type Place = { id: string; code: string; name_fr: string; name_ar: string; active: boolean; source_url: string; source_date: string }
export type Locations = { region_id: string; city_id: string; locality_id: string | null }
export type ShopProfile = Locations & { name: string; contact_phone: string; contact_email: string | null }
export type ShopDocument = { id: string; status: 'quarantined' | 'available' | 'rejected'; media_type: string; revision: number }
type PlaceName = Pick<Place, 'name_fr' | 'name_ar'>
export type Shop = ShopProfile & {
  id: string; status: 'pending' | 'verified' | 'suspended' | 'closed' | 'rejected'; review_reason: string | null; revision: number
  documents?: ShopDocument[]
  location?: { region: PlaceName; city: PlaceName; locality: PlaceName | null } | null
}
export type DebtRequest = { id: string; reference: string; amount_minor: string; status: 'pending' | 'confirmed' | 'disputed'; reason: string | null }
export type Collection<T> = { data: T[]; meta?: { next_cursor: string | null; prev_cursor: string | null } }
export const selectClass = 'min-h-11 w-full rounded-lg border border-line bg-white px-3 text-ink disabled:opacity-60'

export function useResource<T>(path: string | null, revision = 0) {
  const [result, setResult] = useState<{ path: string; revision: number; data?: T; failed?: boolean }>()
  useEffect(() => {
    if (!path) return
    const controller = new AbortController()
    void api.get<T>(path, { signal: controller.signal })
      .then(response => { if (!controller.signal.aborted) setResult({ path, revision, data: response.data }) })
      .catch(() => { if (!controller.signal.aborted) setResult({ path, revision, failed: true }) })
    return () => controller.abort()
  }, [path, revision])
  return result?.path === path && result.revision === revision ? result : undefined
}

export async function write<T>(method: 'post' | 'patch', path: string, payload: object) {
  await getCsrfCookie()
  return (await api.request<{ data: T }>({ method, url: path, data: payload })).data.data
}

export function withCursor(path: string, cursor: string) {
  return cursor ? path + '?cursor=' + encodeURIComponent(cursor) : path
}
