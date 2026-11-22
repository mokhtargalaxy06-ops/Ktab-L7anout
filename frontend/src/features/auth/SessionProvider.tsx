import { useEffect, useRef, useState, type PropsWithChildren } from 'react'
import { api } from '../../shared/api/client'
import { SessionContext, type User } from './session'

export function SessionProvider({ children }: PropsWithChildren) {
  const [user, setSessionUser] = useState<User | null>(null)
  const sessionChanged = useRef(false)

  function setUser(next: User | null) {
    sessionChanged.current = true
    setSessionUser(next)
  }

  useEffect(() => {
    const controller = new AbortController()
    void api.get<{ data: User }>('/api/v1/me', { signal: controller.signal })
      .then(response => {
        if (!controller.signal.aborted && !sessionChanged.current) setSessionUser(response.data.data)
      })
      .catch(() => {})
    return () => controller.abort()
  }, [])

  return <SessionContext.Provider value={{ user, setUser }}>{children}</SessionContext.Provider>
}
