import { fireEvent, render, screen, within } from '@testing-library/react'
import { AxiosError } from 'axios'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api, getCsrfCookie } from '../shared/api/client'
import { App } from './App'

vi.mock('../shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn() },
  getCsrfCookie: vi.fn(),
}))

function open(path: string) {
  return render(<MemoryRouter initialEntries={[path]}><App /></MemoryRouter>)
}

beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(api.get).mockRejectedValue(new Error('Guest session'))
})

describe('foundation routes', () => {
  it('starts in French and switches to Arabic with RTL', async () => {
    open('/')
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('La solidarité commence')
    await userEvent.click(screen.getByRole('button', { name: 'Changer de langue' }))
    expect(document.documentElement).toHaveAttribute('lang', 'ar')
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('التضامن يبدأ')
  })

  it('shows the not-found route and provides a way back', async () => {
    open('/introuvable')
    expect(screen.getByRole('heading', { name: 'Cette page est introuvable.' })).toBeVisible()
    await userEvent.click(screen.getByRole('link', { name: 'Revenir à l’accueil' }))
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('La solidarité commence')
  })

  it('validates password confirmation before registering', async () => {
    open('/register')
    fireEvent.change(screen.getByLabelText('Nom complet'), { target: { value: 'Test local' } })
    fireEvent.change(screen.getByLabelText('Adresse e-mail'), { target: { value: 'test@example.com' } })
    fireEvent.change(screen.getByLabelText('Mot de passe', { exact: true }), { target: { value: 'A long test password' } })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), { target: { value: 'wrong confirmation' } })
    await userEvent.click(screen.getByRole('button', { name: 'Créer un compte' }))
    expect(await screen.findByText('Les mots de passe doivent être identiques.')).toBeVisible()
    expect(api.post).not.toHaveBeenCalled()
  })

  it('fetches a CSRF cookie, signs in, and exposes logout', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 'user', name: 'Test local', email: 'test@example.com', locale: 'fr', roles: ['donor'], abilities: [] } } })
    open('/login')
    fireEvent.change(screen.getByLabelText('Adresse e-mail'), { target: { value: 'test@example.com' } })
    fireEvent.change(screen.getByLabelText('Mot de passe'), { target: { value: 'A long test password' } })
    await userEvent.click(within(screen.getByRole('main')).getByRole('button', { name: 'Se connecter' }))
    expect(await screen.findByRole('button', { name: 'Se déconnecter' })).toBeVisible()
    expect(getCsrfCookie).toHaveBeenCalledBefore(vi.mocked(api.post))
    expect(api.post).toHaveBeenCalledWith('/login', { email: 'test@example.com', password: 'A long test password' })
  })

  it('clears the local session when logout reports an expired login', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { data: { id: 'user', name: 'Test local', email: 'test@example.com', locale: 'fr', roles: ['donor'], abilities: [] } },
    })
    const error = new AxiosError()
    Object.assign(error, { response: { status: 401 } })
    vi.mocked(api.post).mockRejectedValue(error)
    open('/')
    await userEvent.click(await screen.findByRole('button', { name: 'Se déconnecter' }))
    expect(await screen.findByRole('link', { name: 'Se connecter' })).toBeVisible()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
