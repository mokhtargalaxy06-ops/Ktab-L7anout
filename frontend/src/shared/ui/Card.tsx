import type { ComponentPropsWithoutRef } from 'react'

export function Card({ className = '', ...props }: ComponentPropsWithoutRef<'div'>) {
  return <div className={'min-w-0 rounded-2xl border border-line bg-white ' + className} {...props} />
}
