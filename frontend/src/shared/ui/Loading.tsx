import { LoaderCircle } from 'lucide-react'

export function Loading({ label }: { label: string }) {
  return <span role="status" className="inline-flex items-center gap-2">
    <LoaderCircle aria-hidden="true" size={17} className="motion-safe:animate-spin" />{label}
  </span>
}
