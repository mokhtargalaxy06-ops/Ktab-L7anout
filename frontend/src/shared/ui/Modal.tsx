import { useEffect, useId, useRef, type PropsWithChildren } from 'react'
import { X } from 'lucide-react'

type Props = PropsWithChildren<{ open: boolean; title: string; closeLabel: string; onClose: () => void }>

export function Modal({ open, title, closeLabel, onClose, children }: Props) {
  const ref = useRef<HTMLDialogElement>(null)
  const titleId = useId()

  useEffect(() => {
    const dialog = ref.current
    if (open && !dialog?.open) dialog?.showModal()
    if (!open && dialog?.open) dialog.close()
  }, [open])

  return <dialog ref={ref} aria-labelledby={titleId} onCancel={event => { event.preventDefault(); onClose() }}
    className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-6 text-ink shadow-xl backdrop:bg-black/45">
    <div className="mb-4 flex items-center justify-between gap-4">
      <h2 id={titleId} className="text-xl font-semibold">{title}</h2>
      <button type="button" aria-label={closeLabel} onClick={onClose} className="grid size-11 place-items-center rounded-lg hover:bg-mint">
        <X aria-hidden="true" size={20} />
      </button>
    </div>
    {children}
  </dialog>
}
