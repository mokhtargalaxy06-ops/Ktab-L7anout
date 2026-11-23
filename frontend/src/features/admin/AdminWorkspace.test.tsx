import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { AdminWorkspace } from './AdminWorkspace'

vi.mock('../../shared/api/client', () => ({
  api: { get: vi.fn() }, getCsrfCookie: vi.fn(),
}))

it('loads database metrics and opens the user search from the sidebar', async () => {
  vi.mocked(api.get).mockImplementation(async path => path.endsWith('/overview') ? { data: { data: {
    users: 14, donors: 5, beneficiaries: 4, shops: 3, applications: 2, verification: 1, published: 2,
    funded: 1, settled: 0, donations_minor: '12345', settlements: 1, disputes: 0, simulated: true,
  } } } : { data: { data: [
    { id: 'opted-in', name: 'Local Test', email: 'local@example.test', phone: '+212612345678',
      whatsapp_contact: true, roles: ['donor'], status: 'active' },
    { id: 'opted-out', name: 'Private Test', email: 'private@example.test', phone: '+212612345679',
      whatsapp_contact: false, roles: ['beneficiary'], status: 'active' },
  ], meta: {} } })
  render(<MemoryRouter initialEntries={['/dashboard/administrator']}><LocaleProvider><Routes>
    <Route path="/dashboard/administrator/*" element={<AdminWorkspace />} />
  </Routes></LocaleProvider></MemoryRouter>)
  const users = await screen.findByText('Comptes inscrits')
  expect(users.nextElementSibling).toHaveTextContent('14')
  await userEvent.click(screen.getByRole('link', { name: 'Utilisateurs' }))
  expect(await screen.findByLabelText('Rechercher')).toBeVisible()
  const whatsapp = await screen.findByRole('link', { name: 'Contacter sur WhatsApp' })
  expect(whatsapp).toHaveAttribute('href', 'https://wa.me/212612345678')
  expect(screen.getAllByRole('link', { name: 'Contacter sur WhatsApp' })).toHaveLength(1)
  expect(api.get).toHaveBeenCalledWith('/api/v1/admin/users', expect.objectContaining({ params: expect.objectContaining({ role: '' }) }))
})
