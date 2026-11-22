import { useState } from 'react'
import { isAxiosError } from 'axios'
import { Link, useLocation } from 'react-router'
import { z } from 'zod'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import copy from './accountCopy'
import { useSession } from './session'

export function RecoveryPage({ reset = false }: { reset?: boolean }) {
  const { t, locale } = useLocale()
  const { user, setUser } = useSession()
  const labels = copy[locale]
  const { hash } = useLocation()
  const params = new URLSearchParams(hash.slice(1))
  const token = params.get('token')
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)

  async function submit() {
    setError('')
    if (!z.email().safeParse(email.trim()).success) { setError(t.invalidEmail); return }
    if (reset && (Array.from(password).length < 12 || new TextEncoder().encode(password).length > 72 || password.includes('\0'))) {
      setError(t.passwordHint)
      return
    }
    if (reset && password !== confirmation) { setError(t.mismatch); return }
    setBusy(true)
    try {
      await getCsrfCookie()
      await api.post(reset ? '/reset-password' : '/forgot-password', reset
        ? { email, password, password_confirmation: confirmation, token }
        : { email })
      setDone(true)
      if (reset) {
        setPassword('')
        setConfirmation('')
        if (user?.email === email.trim().toLowerCase()) setUser(null)
        window.history.replaceState(window.history.state, '', window.location.pathname)
      }
    } catch (failure) {
      const status = isAxiosError(failure) ? failure.response?.status : undefined
      setError(status === 429 ? t.limited : status === 419 ? t.expired : status === 422 && reset ? labels.invalidLink : t.failed)
    } finally {
      setBusy(false)
    }
  }

  return <section className="bg-surface px-4 py-12">
    <Card className="mx-auto max-w-md p-6">
      <h1 className="display mb-5 text-3xl">{reset ? labels.reset : labels.forgot}</h1>
      {done ? <p role="status">{reset ? labels.resetDone : labels.recoveryNotice}</p>
      : reset && !token ? <p role="alert">{labels.invalidLink} <Link className="underline" to="/forgot-password">{labels.forgot}</Link></p>
      : <form noValidate className="grid gap-4" onSubmit={event => { event.preventDefault(); void submit() }}>
        <Input label={t.email} autoComplete="email" type="email" dir="ltr" value={email} onChange={event => setEmail(event.target.value)} />
        {reset && <>
          <Input label={t.password} autoComplete="new-password" type="password" value={password} onChange={event => setPassword(event.target.value)} />
          <Input label={t.confirmation} autoComplete="new-password" type="password" value={confirmation} onChange={event => setConfirmation(event.target.value)} />
        </>}
        {error && <p role="alert" className="text-red-800">{error}</p>}
        <Button type="submit" disabled={busy}>{busy ? t.loading : reset ? labels.reset : labels.send}</Button>
      </form>}
      <Link className="mt-5 inline-block text-forest underline" to="/login">{t.login}</Link>
    </Card>
  </section>
}
