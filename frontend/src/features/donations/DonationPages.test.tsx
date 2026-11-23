import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api, getCsrfCookie } from '../../shared/api/client'
import { DonateForm, DonationDetail, DonationHistory } from './DonationPages'

vi.mock('../../shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), request: vi.fn() }, getCsrfCookie: vi.fn(),
}))

it('shows confirmed receipts separately from allocations in donor history', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: [{ id: 'history-item', scope: 'urgent_pool', amount_minor: '2500',
    payment_status: 'confirmed', allocation_status: 'waiting' }] } })
  render(<MemoryRouter><LocaleProvider><DonationHistory /></LocaleProvider></MemoryRouter>)
  expect(await screen.findByText('Confirmé · En attente d’affectation')).toBeVisible()
  expect(screen.getByRole('link', { name: 'Détail du don' })).toHaveAttribute('href', '/donations/history-item')
})

it('submits exact minor units with an idempotency key after consent', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: { version: 'mvp-1', simulated: true, terms: { fr: 'Conditions de simulation' } } } })
  vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 'intent' } } })
  render(<MemoryRouter initialEntries={['/donations/new']}><LocaleProvider><Routes>
    <Route path="/donations/new" element={<DonateForm />} />
    <Route path="/donations/intent/checkout" element={<p>Checkout intent</p>} />
  </Routes></LocaleProvider></MemoryRouter>)

  const user = userEvent.setup()
  const input = await screen.findByLabelText('Montant (MAD)')
  await user.clear(input)
  await user.type(input, '18,75')
  await user.click(screen.getByRole('checkbox'))
  await user.click(screen.getByRole('button', { name: 'Continuer vers la simulation' }))
  expect(await screen.findByText('Checkout intent')).toBeVisible()
  expect(getCsrfCookie).toHaveBeenCalled()
  expect(api.post).toHaveBeenCalledWith('/api/v1/donations', {
    scope: 'urgent_pool', amount_minor: '1875', currency: 'MAD', policy_version: 'mvp-1',
  }, { headers: { 'Idempotency-Key': expect.any(String) } })
})

it('prefills a small case balance and allows a smaller custom contribution', async () => {
  vi.mocked(api.get).mockImplementation(async path => ({ data: path.endsWith('/shop-evidence')
    ? { shop_whatsapp_url: 'https://wa.me/212612345678' }
    : { data: { remaining_minor: '3500', city: { name_fr: 'Fès' } } } }))
  render(<MemoryRouter initialEntries={['/donations/new?case=case-1']}><LocaleProvider><DonateForm /></LocaleProvider></MemoryRouter>)

  const input = await screen.findByDisplayValue('35.00')
  const linkName = 'Envoyer un message WhatsApp à l’épicerie'
  expect(await screen.findByRole('link', { name: linkName })).toBeVisible()
  await userEvent.clear(input)
  await userEvent.type(input, '36')
  expect(screen.queryByRole('link', { name: linkName })).not.toBeInTheDocument()
  expect(screen.getByRole('alert')).toBeVisible()
  await userEvent.clear(input)
  await userEvent.type(input, '12,50')
  const link = screen.getByRole('link', { name: linkName })
  expect(new URL(link.getAttribute('href')!).searchParams.get('text')).toMatch(/12,50.*case-1/)
})

it('opens a prefilled Arabic WhatsApp message for a contribution entered with Arabic digits', async () => {
  localStorage.setItem('mdr-locale', 'ar')
  vi.mocked(api.get).mockImplementation(async path => ({ data: path.endsWith('/shop-evidence')
    ? { shop_whatsapp_url: 'https://wa.me/212612345678' }
    : { data: { remaining_minor: '3500', city: { name_ar: 'فاس' } } } }))
  try {
    render(<MemoryRouter initialEntries={['/donations/new?case=case-1']}><LocaleProvider><DonateForm /></LocaleProvider></MemoryRouter>)
    const input = await screen.findByDisplayValue('35.00')
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    fireEvent.change(input, { target: { value: '١٢٫٥٠' } })
    const link = await screen.findByRole('link', { name: 'إرسال رسالة واتساب إلى البقالة' })
    const url = new URL(link.getAttribute('href')!)
    expect(url.origin + url.pathname).toBe('https://wa.me/212612345678')
    expect(url.searchParams.get('text')).toMatch(/أود المساهمة.*(?:12[,.]50|١٢٫٥٠).*case-1/u)
  } finally {
    localStorage.removeItem('mdr-locale')
  }
})

it('shows the failure screen only after the simulated payment result', async () => {
  const record = {
    id: 'intent', scope: 'specific', amount_minor: '5000', payment_status: 'pending', allocation_status: 'waiting',
    captured_minor: '0', allocated_minor: '0', unallocated_minor: '0', returned_minor: '0', return_pending_minor: '0',
    allocations: [], returns: [],
  }
  vi.mocked(api.get).mockResolvedValue({ data: { data: record } })
  vi.mocked(api.request).mockImplementation(async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { ...record, payment_status: 'failed' } } })
    return { data: { data: { ...record, payment_status: 'failed' } } }
  })
  render(<MemoryRouter initialEntries={['/donations/intent/checkout']}><LocaleProvider><Routes>
    <Route path="/donations/:id/checkout" element={<DonationDetail checkout />} />
    <Route path="/donations/:id/failure" element={<DonationDetail />} />
  </Routes></LocaleProvider></MemoryRouter>)
  await userEvent.click(await screen.findByRole('button', { name: 'Simuler un échec' }))
  expect(await screen.findByRole('heading', { name: 'Paiement simulé non abouti' })).toBeVisible()
  expect(api.request).toHaveBeenCalledWith({ method: 'post', url: '/api/v1/donations/intent/simulation', data: { outcome: 'failed' } })
})
