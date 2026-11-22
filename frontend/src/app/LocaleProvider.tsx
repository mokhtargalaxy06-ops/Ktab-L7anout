import { useEffect, useState, type PropsWithChildren } from 'react'
import { LocaleContext } from './locale'
import { messages, type Locale } from './messages'

export function LocaleProvider({ children }: PropsWithChildren) {
  const [locale, setLocale] = useState<Locale>(() => localStorage.getItem('mdr-locale') === 'ar' ? 'ar' : 'fr')

  useEffect(() => {
    document.documentElement.lang = locale
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
    localStorage.setItem('mdr-locale', locale)
  }, [locale])

  return <LocaleContext.Provider value={{ locale, setLocale, t: messages[locale] }}>{children}</LocaleContext.Provider>
}
