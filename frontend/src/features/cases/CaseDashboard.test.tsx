import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { AxiosError } from 'axios'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { CaseDashboard } from './CaseDashboard'
import { PublicCases } from './PublicCases'

vi.mock('../../shared/api/client', () => ({ api: { get: vi.fn(), request: vi.fn() }, getCsrfCookie: vi.fn() }))

it('sends the draft amount in minor units and shows the server validation details', async () => {
  const draft = { id: 'case-1', revision: 1, status: 'draft', shop_name: 'Hanout Test', shop_id: 'shop-1',
    city_id: 'city-1', region_id: 'region-1', debt_reference: 'TEST-1', debt_date: '2024-01-01', amount_minor: '1000', situation: 'Fixture',
    documents: [{ id: 'document-1', revision: 1, status: 'quarantined', media_type: 'application/pdf' }] }
  vi.mocked(api.get).mockImplementation(async path => ({ data: { data: path.endsWith('/case-1') ? draft
    : path.includes('/beneficiary/shops?') ? [{ id: 'shop-1', name: 'Hanout Test', status: 'verified' }] : [] } }))
  const failure = new AxiosError()
  failure.response = { status: 422, data: { errors: { debt_reference: ['La référence contient un espace.'] } } } as typeof failure.response
  vi.mocked(api.request).mockRejectedValue(failure)
  render(<MemoryRouter initialEntries={['/?case=case-1']}><LocaleProvider><CaseDashboard audience="beneficiary" /></LocaleProvider></MemoryRouter>)
  const amount = await screen.findByLabelText('Montant de la dette (MAD)')
  expect(amount).toHaveValue('10.00')
  expect(screen.getByRole('button', { name: 'Soumettre' })).toBeEnabled()
  fireEvent.change(amount, { target: { value: '12,50' } })
  const save = screen.getByRole('button', { name: 'Enregistrer le brouillon' })
  await waitFor(() => expect(save).toBeEnabled())
  fireEvent.submit(save.closest('form')!)
  expect(await screen.findByText('La référence contient un espace.')).toBeVisible()
  expect(api.request).toHaveBeenCalledWith(expect.objectContaining({ data: expect.objectContaining({ amount_minor: '1250' }) }))
})

it('confirms a debt in dirhams and sends the original amount in centimes', async () => {
  const record = { id: 'case-1', status: 'pending_verification', revision: 1,
    amount_minor: '3500', debt_date: '2024-01-01' }
  vi.mocked(api.get).mockImplementation(async path => ({ data: { data: path.endsWith('/case-1') ? record : [] } }))
  vi.mocked(api.request).mockResolvedValueOnce({ data: { data: { ...record, status: 'under_review' } } })
  render(<MemoryRouter initialEntries={['/?case=case-1']}><LocaleProvider><CaseDashboard audience="shop" /></LocaleProvider></MemoryRouter>)
  await userEvent.selectOptions(await screen.findByRole('combobox'), 'confirm')
  expect(screen.getByLabelText('Montant de la dette (MAD)')).toHaveValue('35.00')
  await userEvent.click(screen.getByRole('button', { name: 'Confirmer la dette' }))
  expect(api.request).toHaveBeenCalledWith(expect.objectContaining({
    url: '/api/v1/shop/cases/case-1/verification',
    data: expect.objectContaining({ decision: 'confirm', amount_minor: '3500' }),
  }))
})

it('shows where an administrator can lift a case flag', async () => {
  const record = { id: 'flagged-case', status: 'under_review', shop_name: 'Hanout Test', flagged: true,
    amount_minor: '1000', debt_date: '2024-01-01', revision: 1 }
  vi.mocked(api.get).mockImplementation(async path => ({
    data: { data: path.endsWith('/flagged-case') ? record : [record] },
  }))
  render(<MemoryRouter initialEntries={['/?case=flagged-case']}><LocaleProvider><CaseDashboard audience="admin" /></LocaleProvider></MemoryRouter>)
  expect(await screen.findByLabelText('Décision administrative')).toBeVisible()
  expect(screen.getByRole('option', { name: 'Lever le signalement' })).toBeVisible()
  expect(screen.queryByRole('option', { name: 'Approuver' })).not.toBeInTheDocument()
})

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
