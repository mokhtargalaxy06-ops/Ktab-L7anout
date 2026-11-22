import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { CaseDashboard } from './CaseDashboard'
import { PublicCases } from './PublicCases'

vi.mock('../../shared/api/client', () => ({ api: { get: vi.fn() } }))

it('opens an application from the beneficiary tracking list', async () => {
  const record = {
    id: 'case-1', status: 'submitted', shop_name: 'Épicerie test', debt_reference: 'TEST-1',
    amount_minor: '25000', debt_date: '2024-01-01', situation: 'Explication privée',
  }
  vi.mocked(api.get).mockImplementation(async path => ({
    data: { data: path.endsWith('/case-1') ? record : [record] },
  }))
  render(<MemoryRouter><LocaleProvider><CaseDashboard audience="beneficiary" /></LocaleProvider></MemoryRouter>)
  await userEvent.click(await screen.findByRole('button', { name: 'Choisir un dossier' }))
  expect(await screen.findByText('Explication privée')).toBeVisible()
  expect(screen.queryByRole('button', { name: 'Approuver' })).not.toBeInTheDocument()
})

it('renders an approved amount and region in the public catalog', async () => {
  vi.mocked(api.get).mockImplementation(async path => ({
    data: { data: path.startsWith('/api/v1/cases') ? [{
      id: 'case-1', region: { name_fr: 'Rabat-Salé-Kénitra', name_ar: 'الرباط' },
      city: { name_fr: 'Rabat', name_ar: 'الرباط' }, amount_minor: '25000', collected_minor: '5000', progress: 20,
    }] : [], meta: { page: 1, last_page: 1, total: 1 } },
  }))
  render(<MemoryRouter><LocaleProvider><PublicCases /></LocaleProvider></MemoryRouter>)
  expect(await screen.findByRole('heading', { name: 'Rabat' })).toBeVisible()
  expect(screen.getByText('Rabat-Salé-Kénitra')).toBeVisible()
  expect(screen.getByRole('progressbar')).toHaveAttribute('value', '20')
})
