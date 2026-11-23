import { useId, type ComponentPropsWithRef } from 'react'

type Props = ComponentPropsWithRef<'input'> & { label: string; error?: string; hint?: string }

export function Input({ label, error, hint, id, ...props }: Props) {
  const generatedId = useId()
  const inputId = id ?? generatedId
  const description = error ? inputId + '-error' : hint ? inputId + '-hint' : undefined

  return <div className="grid min-w-0 gap-1.5">
    <label className="text-sm font-semibold" htmlFor={inputId}>{label}</label>
    <input {...props} id={inputId} aria-invalid={Boolean(error)} aria-describedby={description}
      className="min-h-12 w-full min-w-0 rounded-lg border border-line bg-white px-3 aria-invalid:border-red-700" />
    {error ? <p id={description} className="text-sm text-red-800">{error}</p>
      : hint && <p id={description} className="text-sm text-muted">{hint}</p>}
  </div>
}
