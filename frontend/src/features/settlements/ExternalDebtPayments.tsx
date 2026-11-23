import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { mad, toMajor, toMinor } from '../discovery/types'
import { useResource } from '../shops/shopApi'

const copy = {
  fr: {
    title: 'Paiements reçus hors plateforme', empty: 'Aucune contribution à examiner.',
    amount: 'Montant reçu (MAD)', reference: 'Référence du paiement', evidence: 'Justificatif (PDF, JPEG ou PNG)',
    submit: 'Confirmer le paiement reçu', pending: 'En attente de validation', approved: 'Réception confirmée', rejected: 'Déclaration rejetée',
    approve: 'Valider le paiement', reject: 'Rejeter la déclaration', decision: 'Décision', reason: 'Motif', password: 'Mot de passe actuel',
    download: 'Télécharger le justificatif', previous: 'Précédent', next: 'Suivant', save: 'Enregistrer', pledge: 'Référence de contribution',
  },
  ar: {
    title: 'المدفوعات المستلمة خارج المنصة', empty: 'لا توجد مساهمات للمراجعة.',
    amount: 'المبلغ المستلم (درهم)', reference: 'مرجع الدفع', evidence: 'إثبات الدفع (PDF أو JPEG أو PNG)',
    submit: 'تأكيد استلام الدفع', pending: 'بانتظار المراجعة', approved: 'تم تأكيد الاستلام', rejected: 'تم رفض التصريح',
    approve: 'اعتماد الدفع', reject: 'رفض التصريح', decision: 'القرار', reason: 'السبب', password: 'كلمة المرور الحالية',
    download: 'تنزيل الإثبات', previous: 'السابق', next: 'التالي', save: 'حفظ', pledge: 'مرجع المساهمة',
  },
}

type Receipt = {
  id: string; shop_name: string; public_reference: string; pledged_minor: string
  amount_minor: string | null; reference: string | null; status: 'pending' | 'approved' | 'rejected' | null; reason: string | null
}

export function ExternalDebtPayments({ admin }: { admin: boolean }) {
  const { locale, t } = useLocale()
  const text = copy[locale]
  const [page, setPage] = useState(1)
  const [revision, refresh] = useState(0)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const base = '/api/v1/' + (admin ? 'admin' : 'shop') + '/external-payments'
  const result = useResource<{ data: Receipt[]; last_page: number }>(base + '?page=' + page, revision)

  async function save(item: Receipt, form: HTMLFormElement) {
    if (busy) return
    setError('')
    const fields = new FormData(form)
    if (!admin) {
      const amount = String(fields.get('amount_minor')).trim()
        .replace(/[٠-٩۰-۹]/g, digit => String(digit.charCodeAt(0) & 15)).replace('٫', '.')
      if (!/^[0-9]{1,10}([.,][0-9]{1,2})?$/.test(amount)
        || BigInt(toMinor(amount)) < 1n || BigInt(toMinor(amount)) > BigInt(item.pledged_minor)) {
        setError(item.id)
        return
      }
      fields.set('amount_minor', toMinor(amount))
    }
    setBusy(true)
    try {
      await getCsrfCookie()
      if (admin) await api.patch(base + '/' + item.id, Object.fromEntries(fields))
      else await api.post(base + '/' + item.id, fields)
      form.reset()
      refresh(value => value + 1)
    } catch {
      setError(item.id)
    } finally {
      setBusy(false)
    }
  }

  async function download(id: string) {
    try {
      const { data, headers } = await api.get<Blob>(base + '/' + id + '/evidence', { responseType: 'blob' })
      const url = URL.createObjectURL(data)
      const link = document.createElement('a')
      link.href = url
      link.download = 'receipt-' + id + (headers['content-disposition']?.match(/\.(pdf|png|jpg|jpeg)\b/i)?.[0] ?? '')
      link.click()
      window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      setError(id)
    }
  }

  return <section className="grid gap-4">
    <h3 className="display text-2xl">{text.title}</h3>
    {result?.failed ? <p role="alert">{t.failed}</p> : !result?.data ? <p role="status">{t.loading}</p>
      : !result.data.data.length && <p>{text.empty}</p>}
    {result?.data?.data.map(item => <Card key={item.id} className="grid gap-3 p-4">
      <p className="font-semibold">{item.shop_name} · <bdi>{mad(item.amount_minor ?? item.pledged_minor, locale)}</bdi></p>
      <p className="break-all"><bdi>{item.public_reference}</bdi> · {text.pledge} <bdi>{item.id}</bdi></p>
      {item.status && <p>{text[item.status]} · <bdi>{item.reference}</bdi>{item.reason && <> · {item.reason}</>}</p>}
      {admin && <div><Button variant="secondary" onClick={() => void download(item.id)}>{text.download}</Button></div>}
      {((!admin && !item.status) || (admin && item.status === 'pending')) &&
        <form className="grid gap-3" onSubmit={event => { event.preventDefault(); void save(item, event.currentTarget) }}>
          {admin ? <>
            <label className="grid gap-1">{text.decision}
              <select name="decision" className="rounded-lg border border-line p-2">
                <option value="approved">{text.approve}</option>
                <option value="rejected">{text.reject}</option>
              </select>
            </label>
            <Input name="reason" label={text.reason} required maxLength={500} />
            <Input name="password" label={text.password} type="password" autoComplete="current-password" required />
          </> : <>
            <Input name="amount_minor" label={text.amount} inputMode="decimal" defaultValue={toMajor(item.pledged_minor)} required />
            <Input name="reference" label={text.reference} required maxLength={120} />
            <Input name="evidence" label={text.evidence} type="file" accept=".pdf,.jpg,.jpeg,.png" required />
          </>}
          <div><Button type="submit" disabled={busy}>{admin ? text.save : text.submit}</Button></div>
        </form>}
      {error === item.id && <p role="alert">{t.failed}</p>}
    </Card>)}
    <div className="flex gap-2">
      {page > 1 && <Button variant="secondary" onClick={() => setPage(page - 1)}>{text.previous}</Button>}
      {result?.data && page < result.data.last_page && <Button variant="secondary" onClick={() => setPage(page + 1)}>{text.next}</Button>}
    </div>
  </section>
}
