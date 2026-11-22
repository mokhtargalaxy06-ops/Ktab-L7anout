import { useEffect } from 'react'
import { useLocation } from 'react-router'
import { ClipboardCheck, HandCoins, ShieldCheck } from 'lucide-react'
import { useLocale } from '../../app/locale'
import { useSession } from '../auth/session'
import { ButtonLink } from '../../shared/ui/Button'
import { Card } from '../../shared/ui/Card'
import { HomeDiscovery } from '../discovery/HomeDiscovery'
import { discoveryCopy } from '../discovery/copy'

const stepIcons = [ClipboardCheck, ShieldCheck, HandCoins]

export function HomePage() {
  const { locale, t } = useLocale()
  const text = discoveryCopy[locale]
  const { user } = useSession()
  const { hash } = useLocation()

  useEffect(() => {
    if (hash === '#approche') document.getElementById('approche')?.scrollIntoView()
  }, [hash])

  return <>
    <section className="container-shell grid items-center gap-9 py-10 md:grid-cols-[minmax(0,1fr)_minmax(0,1.06fr)] md:gap-10 md:py-16 lg:gap-16">
      <div>
        <p className="mb-5 text-xs font-bold tracking-[0.16em] text-forest">{t.eyebrow}</p>
        <h1 className="display text-[clamp(2.8rem,5.1vw,5.1rem)] leading-[1.04] tracking-tight">{t.title}
          <span className="block text-forest">{t.accent}</span>
        </h1>
        <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted">{t.intro}</p>
        <div className="mt-7 flex flex-wrap gap-3">
          <ButtonLink to="/cases">{text.browse}</ButtonLink>
          <ButtonLink to="/urgent" variant="secondary">{text.urgentTitle}</ButtonLink>
        </div>
        <p className="mt-5 text-sm text-muted">{t.note}</p>
      </div>
      <img src="/hanout.jpg" alt={t.photo} width="1448" height="1086" fetchPriority="high"
        className="aspect-[4/3] h-full max-h-[520px] w-full rounded-3xl object-cover shadow-[0_18px_48px_-28px_#1b3e2d60]" />
    </section>
    <HomeDiscovery />
    <section id="approche" aria-labelledby="approche-title" className="scroll-mt-5 border-t border-line bg-surface py-12 md:py-16">
      <div className="container-shell">
        <div className="mb-8 text-center">
          <h2 id="approche-title" className="display text-4xl tracking-tight md:text-5xl">{t.sectionTitle}</h2>
          <p className="mt-3 text-muted">{t.sectionIntro}</p>
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          {t.steps.map(([title, description], index) => {
            const Icon = stepIcons[index]
            return <Card key={title} className="p-5 md:p-6">
              <span className="mb-4 grid size-11 place-items-center rounded-xl bg-mint text-forest"><Icon aria-hidden="true" size={22} /></span>
              <h3 className="text-lg font-bold">{title}</h3>
              <p className="mt-2 leading-relaxed text-muted">{description}</p>
            </Card>
          })}
        </div>
      </div>
    </section>
    <section className="container-shell py-10 md:py-14">
      <div className="grid gap-4 rounded-3xl bg-forest-dark p-6 text-white sm:p-10">
        <h2 className="display text-3xl sm:text-4xl">{text.transparency}</h2>
        <p className="max-w-2xl leading-relaxed text-white/85">{text.transparencyText}</p>
        <div className="flex flex-wrap gap-3">
          <ButtonLink variant="secondary" to="/cases">{text.browse}</ButtonLink>
          {!user && <ButtonLink variant="secondary" to="/register">{t.register}</ButtonLink>}
        </div>
      </div>
    </section>
  </>
}
