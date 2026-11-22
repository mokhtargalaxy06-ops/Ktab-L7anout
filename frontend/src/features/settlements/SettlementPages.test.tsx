import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { SettlementDashboard, TransparencyPage } from './SettlementPages'

vi.mock('../../shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn() }, getCsrfCookie: vi.fn(),
}))

it('shows transfer and shop confirmation as separate simulation totals', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: {
    simulated: true, received_minor: '7000', allocated_minor: '6000', transferred_minor: '5000',
    confirmed_minor: '0', settled_minor: '0', available_minor: '1000', families_helped: 0,
    participating_shops: 1, debts_settled: 0, regions: [],
  } } })
  render(<LocaleProvider><TransparencyPage /></LocaleProvider>)
  expect(await screen.findByText('Données de simulation. Aucun virement réel.')).toBeVisible()
  expect(screen.getByText('Virements enregistrés').nextElementSibling).toHaveTextContent('50')
  expect(screen.getByText('Réception confirmée par les épiceries').nextElementSibling).toHaveTextContent('0')
})

it('submits a shop dispute with a reason and idempotency key', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: [{
    id: 'case', reference: null, shop_name: 'Fixture shop', approved_minor: '5000', allocated_minor: '5000',
    settled_minor: '0', payments: [{ id: 'payout', status: 'evidence_pending', amount_minor: '5000',
      reference: 'SIM-PAYOUT-fixture', shop_response: null }],
  }], meta: { last_page: 1 } } })
  vi.mocked(api.post).mockResolvedValue({})
  const user = userEvent.setup()
  render(<LocaleProvider><SettlementDashboard /></LocaleProvider>)
  await user.click(await screen.findByRole('button', { name: 'Contester' }))
  await user.type(screen.getByLabelText('Motif'), 'Référence incorrecte')
  const buttons = screen.getAllByRole('button', { name: 'Contester' })
  await user.click(buttons[0])
  await waitFor(() => expect(api.post).toHaveBeenCalledWith('/api/v1/shop/settlements/case/payout',
    { action: 'dispute', reason: 'Référence incorrecte' }, { headers: { 'Idempotency-Key': expect.any(String) } }))
})
