import { useState } from 'react'
import { useLocale } from '../../app/locale'
import { Button } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { Input } from '../../shared/ui/Input'
import { selectClass, write } from '../shops/shopApi'
import { discoveryCopy } from './copy'
import type { Story } from './types'

type Props = {
  id: string; revision: number; story?: Story; vulnerability?: number | null; risk?: number | null; reasonLabel: string
}

export function DiscoveryAssessment({ id, revision, story, vulnerability, risk, reasonLabel }: Props) {
  const { locale, t } = useLocale()
  const text = discoveryCopy[locale]
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<'saved' | 'failed'>()
  const stories = Object.keys(text.stories) as Story[]
  return <Card className="grid gap-3 p-5">
    <h3 className="font-bold">{text.assessment}</h3>
    <form className="grid gap-3" onSubmit={async event => {
      event.preventDefault()
      const data = Object.fromEntries(new FormData(event.currentTarget))
      setBusy(true)
      setMessage(undefined)
      try {
        await write('post', '/api/v1/admin/cases/' + id + '/discovery', { ...data, revision })
        setMessage('saved')
      } catch {
        setMessage('failed')
      } finally {
        setBusy(false)
      }
    }}>
      <label className="grid gap-1 text-sm font-semibold">{text.story}
        <select name="story" defaultValue={story ?? 'essentials'} className={selectClass}>
          {stories.map(key => <option key={key} value={key}>{text.stories[key]}</option>)}
        </select>
      </label>
      <div className="grid gap-3 sm:grid-cols-2">
        <Input name="vulnerability" type="number" min={0} max={4} label={text.weights.vulnerability} hint={text.grade} required defaultValue={vulnerability ?? ''} />
        <Input name="access_risk" type="number" min={0} max={4} label={text.weights.access_risk} hint={text.grade} required defaultValue={risk ?? ''} />
      </div>
      <Input name="reason" label={reasonLabel} maxLength={500} required />
      <div><Button type="submit" disabled={busy}>{text.save}</Button></div>
      {message && <p role={message === 'failed' ? 'alert' : 'status'}>{message === 'failed' ? t.failed : text.saved}</p>}
    </form>
  </Card>
}
