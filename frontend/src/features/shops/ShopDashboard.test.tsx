import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { ShopDashboard } from './ShopDashboard'

vi.mock('../../shared/api/client', () => ({
  api: { get: vi.fn() }, getCsrfCookie: vi.fn(),
}))

it('opens owned shop workspaces directly without a shop selector', async () => {
  const shop = { id: 'shop-1', name: 'Hanout Test', revision: 1, status: 'pending',
    region_id: 'region', city_id: 'city', locality_id: null, contact_phone: '+212612345678', documents: [] }
  vi.mocked(api.get).mockImplementation(async url => ({ data: { data: url.startsWith('/api/v1/shop/shops')
    ? [shop, { ...shop, id: 'shop-2', name: 'Second Hanout' }] : [] } }))
  render(<MemoryRouter><LocaleProvider><ShopDashboard /></LocaleProvider></MemoryRouter>)
  expect(await screen.findByRole('heading', { name: 'Hanout Test' })).toBeVisible()
  expect(screen.getByRole('heading', { name: 'Second Hanout' })).toBeVisible()
  expect(screen.queryByLabelText('Sélectionner une épicerie')).not.toBeInTheDocument()
})
