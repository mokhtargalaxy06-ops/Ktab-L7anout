import { createContext, useContext } from 'react'
import type { Locale } from '../../app/messages'

export interface User {
  id: string
  name: string
  email: string
  locale: Locale
  roles: string[]
  abilities: string[]
  email_verified?: boolean
}

export const SessionContext = createContext<{
  user: User | null
  setUser: (user: User | null) => void
  ready: boolean
  refresh: (signal?: AbortSignal) => Promise<void>
}>({ user: null, setUser: () => {}, ready: false, refresh: async () => {} })

export function useSession() {
  return useContext(SessionContext)
}
