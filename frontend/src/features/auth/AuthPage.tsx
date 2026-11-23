import { useState } from 'react'
import { isAxiosError } from 'axios'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router'
import { z } from 'zod'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie, type ApiError } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import { useSession, type User } from './session'
import copy, { accountDashboard, publicRoles } from './accountCopy'

export function AuthPage({ register = false }: { register?: boolean }) {
  const { t, locale } = useLocale()
  const labels = copy[locale]
  const navigate = useNavigate()
  const location = useLocation()
  const { setUser } = useSession()
  const [submissionError, setSubmissionError] = useState('')
  const schema = z.object({
    name: register ? z.string().trim().min(1, t.required).max(120, t.invalidFields) : z.string().optional(),
    email: z.email({ error: t.invalidEmail }).max(254, t.invalidEmail),
    password: z.string().min(register ? 12 : 1, register ? t.passwordHint : t.required)
      .refine(value => new TextEncoder().encode(value).length <= 72 && !value.includes('\0'), t.invalidPassword),
    password_confirmation: register ? z.string().min(1, t.required) : z.string().optional(),
    role: z.enum(publicRoles).optional(),
    phone: register ? z.string().trim().min(1, t.required).max(40, labels.phoneInvalid) : z.string().optional(),
    whatsapp_contact: z.boolean().optional(),
  }).refine(fields => !register || fields.password === fields.password_confirmation, {
    path: ['password_confirmation'], message: t.mismatch,
  })
  const { register: field, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm({ resolver: zodResolver(schema) })

  async function submit(values: z.infer<typeof schema>) {
    setSubmissionError('')
    try {
      await getCsrfCookie()
      const { data } = await api.post<{ data: User }>(register ? '/register' : '/login', register ? { ...values, locale } : values)
      setUser(data.data)
      const returnTo: unknown = location.state?.returnTo
      const fundingPath = typeof returnTo === 'string' && /^\/donations(?:[/?]|$)/.test(returnTo)
      void navigate(fundingPath && data.data.abilities.includes('donations.create') ? returnTo : accountDashboard(data.data.roles), { replace: true })
    } catch (error) {
      const response = isAxiosError<ApiError>(error) ? error.response : undefined
      if (response?.status === 422 && response.data?.errors) {
        const serverFields = response.data.errors
        for (const key of ['email', 'password', 'name', 'password_confirmation', 'phone'] as const) {
          if (serverFields[key]) setError(key, { message: key === 'phone' ? labels.phoneInvalid
            : key === 'email' ? (register ? t.duplicateEmail : t.invalidLogin) : t.invalidFields })
        }
        setSubmissionError(t.invalidFields)
      } else {
        setSubmissionError(response?.status === 429 ? t.limited : response?.status === 419 ? t.expired : t.failed)
      }
    }
  }

  return <section className="bg-surface px-4 py-12 md:py-20">
    <Card className="mx-auto max-w-md p-6 shadow-sm sm:p-8">
      <h1 className="display text-4xl text-ink">{register ? t.register : t.login}</h1>
      <p className="mb-6 mt-2 text-muted">{register ? t.registerIntro : t.loginIntro}</p>
      <form noValidate onSubmit={event => void handleSubmit(submit)(event)} className="grid gap-4">
        {register && <Input {...field('name')} label={t.name} autoComplete="name" error={errors.name?.message} />}
        {register && <label className="grid gap-1.5 text-sm font-semibold">
          {labels.role}
          <select {...field('role')} defaultValue="donor" className="min-h-12 rounded-lg border border-line bg-white px-3">
            {publicRoles.map(role => <option key={role} value={role}>{labels[role]}</option>)}
          </select>
        </label>}
        <Input {...field('email')} label={t.email} type="email" autoComplete="email" dir="ltr" error={errors.email?.message} />
        {register && <>
          <Input {...field('phone')} label={labels.phone} type="tel" autoComplete="tel" dir="ltr"
            maxLength={40} hint={labels.phoneHint} error={errors.phone?.message} required />
          <label className="flex items-start gap-2 text-sm">
            <input {...field('whatsapp_contact')} type="checkbox" className="mt-1" />{labels.whatsappConsent}
          </label>
        </>}
        <Input {...field('password')} label={t.password} type="password" autoComplete={register ? 'new-password' : 'current-password'}
          hint={register ? t.passwordHint : undefined} error={errors.password?.message} />
        {register && <Input {...field('password_confirmation')} label={t.confirmation} type="password"
          autoComplete="new-password" error={errors.password_confirmation?.message} />}
        {submissionError && <p role="alert" className="text-sm text-red-800">{submissionError}</p>}
        <Button type="submit" disabled={isSubmitting}>{isSubmitting ? <Loading label={t.loading} /> : register ? t.register : t.login}</Button>
      </form>
      {!register && <Link to="/forgot-password" className="mt-4 inline-block text-sm font-semibold text-forest underline">{labels.forgot}</Link>}
      <p className="mt-5 text-sm text-muted">
        {register ? t.hasAccount : t.noAccount}{' '}
        <Link to={register ? '/login' : '/register'} className="font-semibold text-forest underline underline-offset-4">{register ? t.login : t.register}</Link>
      </p>
    </Card>
  </section>
}
