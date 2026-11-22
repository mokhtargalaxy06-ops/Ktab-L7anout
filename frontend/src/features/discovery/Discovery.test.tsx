import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { useLocale } from '../../app/locale'
import { api } from '../../shared/api/client'
import { PublicCases } from '../cases/PublicCases'
import { CaseDetail } from './CaseDetail'
import type { PublicCase } from './types'

vi.mock('../../shared/api/client', () => ({ api: { get: vi.fn() } }))

const record: PublicCase = {
  id: 'case-1', region: { name_fr: 'Rabat-Salé-Kénitra', name_ar: 'الرباط سلا القنيطرة' },
  city: { name_fr: 'Rabat', name_ar: 'الرباط' }, story: 'essentials', amount_minor: '25000',
  collected_minor: '5000', remaining_minor: '20000', status: 'open', progress: 20, verified: true, urgent: true,
  currency: 'MAD', updates: [{ type: 'publish', date: '2024-02-01' }],
}

it('applies filters without losing them on the next page', async () => {
  vi.mocked(api.get).mockImplementation(async path => {
    const { pathname, searchParams } = new URL(path, 'http://localhost')
    const data = pathname.endsWith('/regions') ? [{ id: 'rabat-region', name_fr: 'Rabat-Salé-Kénitra' }]
      : pathname.endsWith('/cities') ? [{ id: 'rabat-city', name_fr: 'Rabat' }] : [record]
    return { data: { data, meta: { page: Number(searchParams.get('page') ?? 1), last_page: 2, total: 13 } } }
  })
  render(<MemoryRouter><LocaleProvider><PublicCases /></LocaleProvider></MemoryRouter>)
  const user = userEvent.setup()
  await user.selectOptions(screen.getByLabelText('Région'), await screen.findByRole('option', { name: 'Rabat-Salé-Kénitra' }))
  await user.selectOptions(screen.getByLabelText('Ville ou commune'), await screen.findByRole('option', { name: 'Rabat' }))
  await user.type(screen.getByLabelText('Référence, ville ou région'), 'Rabat')
  await user.type(screen.getByLabelText('Dette minimale (MAD)'), '123,45')
  await user.selectOptions(screen.getByLabelText('État du financement'), 'open')
  await user.click(screen.getByLabelText('Dossiers urgents uniquement'))
  await user.click(screen.getByRole('button', { name: 'Appliquer les filtres' }))
  await user.click(await screen.findByRole('button', { name: 'Suivant' }))
  await waitFor(() => {
    const path = vi.mocked(api.get).mock.calls.at(-1)?.[0] ?? ''
    const params = new URL(path, 'http://localhost').searchParams
    expect(Object.fromEntries(params)).toEqual({
      q: 'Rabat', city_id: 'rabat-city', region_id: 'rabat-region', min_minor: '12345', status: 'open', urgent: '1', page: '2',
    })
  })
})

function LanguageButton() {
  const { setLocale } = useLocale()
  return <button type="button" onClick={() => setLocale('ar')}>Arabic</button>
}

it('localizes an anonymous detail and flips the direction to RTL', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: record } })
  render(<MemoryRouter initialEntries={['/cases/case-1']}><LocaleProvider><LanguageButton />
    <Routes><Route path="/cases/:reference" element={<CaseDetail />} /></Routes>
  </LocaleProvider></MemoryRouter>)
  expect(await screen.findByRole('heading', { name: 'Rabat' })).toBeVisible()
  await userEvent.click(screen.getByRole('button', { name: 'Arabic' }))
  expect(screen.getByRole('heading', { name: 'الرباط' })).toBeVisible()
  expect(screen.getByText('نُشر الملف')).toBeVisible()
  expect(document.documentElement).toHaveAttribute('dir', 'rtl')
})
