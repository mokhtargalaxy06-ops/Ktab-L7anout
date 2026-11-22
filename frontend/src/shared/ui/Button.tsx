import type { ButtonHTMLAttributes } from 'react'
import { Link, type LinkProps } from 'react-router'

function buttonClass(variant: 'primary' | 'secondary' = 'primary') {
  const base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60'
  return base + (variant === 'primary' ? ' bg-forest text-white hover:bg-forest-dark' : ' border border-line bg-white text-forest hover:bg-mint')
}

export function Button({ className = '', type = 'button', variant, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' }) {
  return <button type={type} className={buttonClass(variant) + ' ' + className} {...props} />
}

export function ButtonLink({ variant, className = '', ...props }: LinkProps & { variant?: 'primary' | 'secondary' }) {
  return <Link className={buttonClass(variant) + ' ' + className} {...props} />
}
