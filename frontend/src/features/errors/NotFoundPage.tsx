import { useLocale } from '../../app/locale'
import { ButtonLink } from '../../shared/ui/Button'

export function NotFoundPage() {
  const { t } = useLocale()

  return <section className="container-shell grid justify-items-center gap-5 py-24 text-center">
    <h1 className="display text-4xl">{t.missing}</h1>
    <ButtonLink to="/">{t.back}</ButtonLink>
  </section>
}
