import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { api, getCsrfCookie } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { Loading } from '../../shared/ui/Loading'
import { mad } from '../discovery/types'
import { useResource } from '../shops/shopApi'
import { settlementCopy } from './copy'

type Action = keyof typeof settlementCopy.fr.actions
type State = 'requested' | 'submitted' | 'unknown' | 'evidence_pending' | 'paid' | 'failed' | 'canceled'
type Payment = { id: string; amount_minor: string; status: State; reference: string | null; shop_response: 'confirm' | 'dispute' | null;
  reason?: string | null; evidence?: Record<string, string> | null }
type Case = { id: string; reference: string | null; shop_name: string; approved_minor: string; allocated_minor: string; settled_minor: string; payments: Payment[] }

const money = ['received_minor', 'allocated_minor', 'transferred_minor', 'confirmed_minor', 'settled_minor', 'available_minor'] as const
const counts = ['families_helped', 'participating_shops', 'debts_settled'] as const
type Stats = Record<typeof money[number], string> & Record<typeof counts[number], number> & {
  simulated: boolean
  regions: { name_fr: string; name_ar: string; families_helped: number; debts_settled: number; settled_minor: string }[]
}

export function TransparencyPage() {
  const { locale, t } = useLocale()
  const copy = settlementCopy[locale]
  const { data, failed: error } = useResource<{ data: Stats }>('/api/v1/transparency') ?? {}
  const stats = data?.data
  return <section className="container-shell space-y-6 py-10">
    <h1 className="display text-4xl">{copy.transparency}</h1>
    {error ? <p role="alert">{t.failed}</p> : !stats ? <Loading label={t.loading} /> : <>
      {stats.simulated && <p className="rounded-xl bg-mint p-4">{copy.simulation}</p>}
      <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {[...money, ...counts].map(key => <Card className="p-5" key={key}>
          <dt className="text-sm text-muted">{copy[key]}</dt>
          <dd className="mt-2 text-2xl font-bold"><bdi>{typeof stats[key] === 'string' ? mad(stats[key], locale) : stats[key]}</bdi></dd>
        </Card>)}
      </dl>
      <h2 className="display text-2xl">{copy.region}</h2>
      {!stats.regions.length ? <p>{copy.empty}</p> : <ul className="grid gap-3 sm:grid-cols-2">
        {stats.regions.map(region => <li key={region.name_fr} className="rounded-xl border border-line p-4">
          <h3 className="font-bold">{locale === 'ar' ? region.name_ar : region.name_fr}</h3>
          <p>{copy.families_helped} · {region.families_helped}</p>
          <p>{copy.debts_settled} · {region.debts_settled} · <bdi>{mad(region.settled_minor, locale)}</bdi></p>
        </li>)}
      </ul>}
    </>}
  </section>
}

function actions(payment: Payment, admin: boolean, frozen: boolean): Action[] {
  if (!admin) return payment.status === 'evidence_pending'
    ? (['confirm', 'dispute'] as const).filter(action => action !== payment.shop_response) : []
  if (payment.status === 'failed' && frozen) return ['recover']
  if (payment.status === 'requested') return ['submit', 'fail']
  if (payment.status === 'submitted') return ['transfer', 'fail']
  return payment.status === 'evidence_pending' && payment.shop_response === 'confirm' ? ['reconcile'] : []
}

const filters = ['requested', 'submitted', 'evidence_pending', 'paid', 'failed', 'canceled', 'dispute'] as const

export function SettlementDashboard({ admin = false }: { admin?: boolean }) {
  const { locale, t } = useLocale()
  const copy = settlementCopy[locale]
  const [page, setPage] = useState(1)
  const [filter, setFilter] = useState('')
  const [version, refresh] = useState(0)
  const [journal, setJournal] = useState('')
  const [returns, setReturns] = useState('')
  const [receipts, setReceipts] = useState('')
  const params = new URLSearchParams({ journal, returns, receipts })
  const financeResult = useResource<{ data: Finance }>(admin ? '/api/v1/admin/finance?' + params : null, version)
  const [selection, select] = useState<{ id: string; payment?: string; action: Action; key: string }>()
  const [failed, setFailed] = useState(false)
  const [busy, setBusy] = useState(false)
  const base = '/api/v1/' + (admin ? 'admin' : 'shop') + '/settlements'
  const { data, failed: error } = useResource<{ data: Case[]; meta: { last_page: number } }>(base + '?page=' + page + '&status=' + filter, version) ?? {}
  const choose = (id: string, action: Action, payment?: string) => {
    setFailed(false)
    select({ id, payment, action, key: crypto.randomUUID() })
  }
  const save = async (fields: FormData) => {
    if (!selection || busy) return
    setBusy(true)
    setFailed(false)
    try {
      await getCsrfCookie()
      await api.post(base + '/' + selection.id + (selection.payment ? '/' + selection.payment : ''),
        { action: selection.action, ...(admin && { password: fields.get('password') }), ...(fields.has('reason') && { reason: fields.get('reason') }) },
        { headers: { 'Idempotency-Key': selection.key } })
      select(undefined)
      refresh(value => value + 1)
    } catch { setFailed(true) } finally { setBusy(false) }
  }
  return <section className="mt-10 space-y-4">
    <h2 className="display text-3xl">{copy.title}</h2>
    {!error && <p className="text-muted">{copy.simulation}</p>}
    {admin && <select aria-label={copy.all} className="rounded-lg border border-line p-2" value={filter}
      onChange={event => { setFilter(event.target.value); setPage(1) }}>
      <option value="">{copy.all}</option>
      {filters.map(state => <option key={state} value={state}>{copy.states[state]}</option>)}
    </select>}
    {selection && <Card className="border-forest p-4">
      <form className="space-y-3" onSubmit={event => { event.preventDefault(); void save(new FormData(event.currentTarget)) }}>
        <h3 className="font-bold">{copy.actions[selection.action]}</h3>
        {admin && <Input label={copy.password} name="password" type="password" autoComplete="current-password" required />}
        {['fail', 'dispute'].includes(selection.action) && <Input label={copy.reason} name="reason" maxLength={500} required />}
        <div className="flex gap-2"><Button type="submit" disabled={busy}>{copy.actions[selection.action]}</Button>
          <Button type="button" variant="secondary" disabled={busy} onClick={() => select(undefined)}>{copy.cancel}</Button></div>
        {failed && <p role="alert">{t.failed}</p>}
      </form>
    </Card>}
    {error ? <p role="alert">{t.failed}</p> : !data ? <Loading label={t.loading} /> : <>
      {!data.data.length && <p>{copy.empty}</p>}
      {data.data.map(item => <Card key={item.id} className="space-y-3 p-5">
        <h3 className="font-bold">{item.shop_name} · <bdi>{mad(item.approved_minor, locale)}</bdi></h3>
        {item.reference && <p className="break-all text-sm"><bdi>{item.reference}</bdi></p>}
        <p>{copy.states[item.settled_minor === item.approved_minor ? 'paid' : item.allocated_minor === item.approved_minor ? 'funded' : 'unfunded']}</p>
        {!item.payments.length && item.allocated_minor === item.approved_minor && <p>{copy.states.pending}</p>}
        {admin && item.allocated_minor === item.approved_minor && item.payments.every(payment => ['failed', 'canceled'].includes(payment.status))
          && <Button onClick={() => choose(item.id, 'initiate')}>{copy.actions.initiate}</Button>}
        {item.payments.map(payment => <div key={payment.id} className="space-y-2 rounded-lg border border-line p-3">
          <p>{copy.states[payment.status]} · <bdi>{mad(payment.amount_minor, locale)}</bdi>
            {payment.shop_response && ' · ' + copy.states[payment.shop_response]}</p>
          {payment.reference && <p className="break-all text-sm">{copy.reference} · <bdi>{payment.reference}</bdi></p>}
          {payment.reason && <p className="break-words text-sm">{copy.reason} · {payment.reason}</p>}
          {payment.evidence && <details><summary className="cursor-pointer text-sm font-semibold">{copy.evidence}</summary>
            <pre className="mt-2 overflow-auto rounded bg-mint p-2 text-xs" dir="ltr">{JSON.stringify(payment.evidence, null, 2)}</pre>
          </details>}
          <div className="flex flex-wrap gap-2">{actions(payment, admin, financeResult?.data?.data?.frozen ?? false).map(action =>
            <Button key={action} variant="secondary" onClick={() => choose(item.id, action, payment.id)}>{copy.actions[action]}</Button>)}</div>
        </div>)}
      </Card>)}
      <div className="flex gap-2">
        {page > 1 && <Button variant="secondary" onClick={() => setPage(page - 1)}>{copy.previous}</Button>}
        {page < data.meta.last_page && <Button variant="secondary" onClick={() => setPage(page + 1)}>{copy.next}</Button>}
      </div>
    </>}
    {admin && <FinanceHistory finance={financeResult?.data?.data} failed={financeResult?.failed}
      cursors={{ journal, returns, receipts }} setCursors={{ journal: setJournal, returns: setReturns, receipts: setReceipts }} />}
  </section>
}

type FinanceEntry = { id: string; amount_minor: string | number; type?: string; kind?: string; status?: string; created_at: string }
type Finance = { frozen: boolean; deficits: { payment_transaction_id: string; available_minor: string | number }[]; transactions: FinanceEntry[]; refunds: FinanceEntry[]; next_journal: string | null; next_returns: string | null; receipts: FinanceEntry[]; next_receipts: string | null }
type HistoryTab = 'journal' | 'returns' | 'receipts'
function FinanceHistory({ finance, failed, cursors, setCursors }: {
  finance?: Finance; failed?: boolean; cursors: Record<HistoryTab, string>; setCursors: Record<HistoryTab, (cursor: string) => void>
}) {
  const { locale, t } = useLocale()
  const copy = settlementCopy[locale]
  const [exportError, setExportError] = useState(false)
  async function download(report: 'journal' | 'reconciliation') {
    setExportError(false)
    try {
      const response = await api.get<Blob>('/api/v1/admin/finance/' + report + '.csv', { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = 'simulated-' + report + '.csv'
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 0)
    } catch { setExportError(true) }
  }
  return <Card className="space-y-4 p-5">
    <div className="flex flex-wrap gap-2"><Button variant="secondary" onClick={() => void download('journal')}>{copy.export}</Button>
      <Button variant="secondary" onClick={() => void download('reconciliation')}>{copy.reconciliation}</Button></div>
    {(failed || exportError) && <p role="alert">{t.failed}</p>}
    {!failed && !finance && <Loading label={t.loading} />}
    {finance?.frozen && <p role="status">{copy.freeze}</p>}
    {finance?.deficits.length ? <ul>{finance.deficits.map(row => <li key={row.payment_transaction_id} className="break-all"><bdi>{row.payment_transaction_id} · {mad(String(row.available_minor).replace('-', ''), locale)}</bdi></li>)}</ul> : null}
    {finance && [{ title: copy.journal, items: finance.transactions, next: finance.next_journal, current: cursors.journal, update: setCursors.journal },
      { title: copy.refunds, items: finance.refunds, next: finance.next_returns, current: cursors.returns, update: setCursors.returns },
      { title: copy.receipts, items: finance.receipts, next: finance.next_receipts, current: cursors.receipts, update: setCursors.receipts }].map(section =>
      <section key={section.title} className="space-y-2">
        <h3 className="font-bold">{section.title}</h3>
        {!section.items.length && <p>{copy.empty}</p>}
        <ul className="space-y-2">{section.items.map(item => <li className="break-all text-sm" key={item.id}>
          <bdi>{item.type ?? item.kind} {item.status} · {mad(String(item.amount_minor), locale)} · {item.created_at} · {item.id}</bdi>
        </li>)}</ul>
        <div className="flex gap-2">{section.current && <Button variant="secondary" onClick={() => section.update('')}>{copy.previous}</Button>}
          {section.next && <Button variant="secondary" onClick={() => section.update(section.next ?? '')}>{copy.next}</Button>}</div>
      </section>)}
  </Card>
}
