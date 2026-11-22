import type { Locale } from '../../app/messages'
import type { discoveryCopy } from './copy'

export type Story = keyof typeof discoveryCopy.fr.stories
export type PublicCase = {
  id: string
  region: { name_fr: string; name_ar: string }
  city: { name_fr: string; name_ar: string }
  story: Story
  amount_minor: string
  collected_minor: string
  remaining_minor: string
  status: 'open' | 'funded' | 'settled'
  progress: number
  urgent: boolean
  verified: boolean
  currency: 'MAD'
  updates?: { type: 'confirm' | 'approve' | 'publish' | 'funding'; date: string }[]
}
export type UrgencyPolicy = {
  weights: Record<keyof typeof discoveryCopy.fr.weights, number>
  age_cap_days: number
  balance_cap_minor: number
  urgent_threshold: number
}
export type Discovery = {
  statistics: { families_helped: number; fully_settled_cases: number; settled_minor: string; allocated_minor: string; received_minor: string; currency: 'MAD' }
  recent: PublicCase[]
  urgent: PublicCase[]
  urgency_policy: UrgencyPolicy
}

export function mad(minor: string | number, locale: Locale) {
  const value = BigInt(minor)
  const language = locale === 'ar' ? 'ar-MA' : 'fr-MA'
  const formatter = new Intl.NumberFormat(language, { style: 'currency', currency: 'MAD', minimumFractionDigits: 2 })
  const cents = new Intl.NumberFormat(language, { minimumIntegerDigits: 2 }).format(value % 100n)
  return formatter.formatToParts(value / 100n).map(part => part.type === 'fraction' ? cents : part.value).join('')
}

export function toMinor(value: string) {
  const [whole, fraction = ''] = value.replace(',', '.').split('.')
  return (BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))).toString()
}

export function toMajor(value: string | null) {
  if (!value || !/^\d+$/.test(value)) return ''
  const minor = BigInt(value)
  return String(minor / 100n) + '.' + String(minor % 100n).padStart(2, '0')
}
