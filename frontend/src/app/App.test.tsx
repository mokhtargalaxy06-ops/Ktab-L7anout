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

  it('redirects guests away from the administrator dashboard', async () => {
    open('/dashboard/administrator')
    expect(await screen.findByRole('heading', { name: 'Se connecter' })).toBeVisible()
    expect(screen.queryByText('Comptes utilisateurs')).not.toBeInTheDocument()
  })

  it('denies another role access to the administrator dashboard before fetching accounts', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { data: { id: 'user', name: 'Test local', email: 'test@example.com', locale: 'fr', roles: ['beneficiary'], abilities: [], email_verified: true } },
    })
    open('/dashboard/administrator')
    expect(await screen.findByRole('heading', { name: 'Vous n’avez pas accès à cet espace.' })).toBeVisible()
    expect(vi.mocked(api.get).mock.calls.every(([path]) => path === '/api/v1/me')).toBe(true)
  })

  it('offers only public roles during registration', () => {
    open('/register')
    const select = screen.getByRole('combobox', { name: 'Type de compte' })
    expect(within(select).getAllByRole('option').map(option => option.getAttribute('value')))
      .toEqual(['donor', 'beneficiary', 'shopkeeper'])
  })

  it('requests a recovery email and displays a generic confirmation', async () => {
    vi.mocked(api.post).mockResolvedValue({ status: 204 })
    const user = userEvent.setup()
    open('/forgot-password')
    await user.type(screen.getByLabelText('Adresse e-mail'), 'test@example.com')
    await user.click(screen.getByRole('button', { name: 'Envoyer le lien' }))
    expect(await screen.findByRole('status')).toHaveTextContent('Si un compte actif correspond')
    expect(api.post).toHaveBeenCalledWith('/forgot-password', { email: 'test@example.com' })
  })

  it('clears the signed-in identity after resetting its password', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { data: { id: 'user', name: 'Test local', email: 'test@example.com', locale: 'fr', roles: ['donor'], abilities: [] } },
    })
    vi.mocked(api.post).mockResolvedValue({ status: 204 })
    const user = userEvent.setup()
    open('/reset-password#token=test-token&email=test%40example.com')
    expect(await screen.findByRole('button', { name: 'Se déconnecter' })).toBeVisible()
    await user.type(screen.getByLabelText('Mot de passe', { exact: true }), 'A strong new password')
    await user.type(screen.getByLabelText('Confirmer le mot de passe'), 'A strong new password')
    await user.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }))
    expect(await screen.findByRole('status')).toHaveTextContent('Votre mot de passe est modifié.')
    expect(screen.queryByRole('button', { name: 'Se déconnecter' })).not.toBeInTheDocument()
  })
})
