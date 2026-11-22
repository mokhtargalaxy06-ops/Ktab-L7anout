import { useCallback, useEffect, useRef, useState, type PropsWithChildren } from 'react'
import { api } from '../../shared/api/client'
import { SessionContext, type User } from './session'

export function SessionProvider({ children }: PropsWithChildren) {
  const [user, setSessionUser] = useState<User | null>(null)
  const [ready, setReady] = useState(false)
  const revision = useRef(0)

  function setUser(next: User | null) {
    revision.current++
    setSessionUser(next)
    setReady(true)
  }

  const refresh = useCallback(async (signal?: AbortSignal) => {
    const current = ++revision.current
    try {
      const response = await api.get<{ data: User }>('/api/v1/me', { signal })
      if (!signal?.aborted && current === revision.current) setSessionUser(response.data.data)
    } catch {
      if (!signal?.aborted && current === revision.current) setSessionUser(null)
    } finally {
      if (!signal?.aborted && current === revision.current) setReady(true)
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void Promise.resolve().then(() => {
      if (!controller.signal.aborted) return refresh(controller.signal)
    })
    return () => controller.abort()
  }, [refresh])

  return <SessionContext.Provider value={{ user, setUser, ready, refresh }}>{children}</SessionContext.Provider>
}
