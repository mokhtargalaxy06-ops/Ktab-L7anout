import { createContext, useContext } from 'react'
import type { Locale } from '../../app/messages'

export interface User {
  id: string
  name: string
  email: string
  locale: Locale
  roles: string[]
  abilities: string[]
}

export const SessionContext = createContext<{
  user: User | null
  setUser: (user: User | null) => void
}>({ user: null, setUser: () => {} })

export function useSession() {
  return useContext(SessionContext)
}
