import { createContext, useContext } from 'react'
import { messages, type Locale } from './messages'

export const LocaleContext = createContext({
  locale: 'fr' as Locale,
  setLocale: (_locale: Locale): void => { void _locale },
  t: messages.fr,
})

export function useLocale() {
  return useContext(LocaleContext)
}
