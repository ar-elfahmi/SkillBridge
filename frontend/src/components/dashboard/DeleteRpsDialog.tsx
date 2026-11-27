import { ModalDialog } from '@/components/common/ModalDialog'
import { StatusNotice } from '@/components/common/StatusNotice'
import { Button } from '@/components/ui/button'
import { copy } from '@/lib/copy'

interface DeleteRpsDialogProps {
  name: string
  isPending: boolean
  hasError: boolean
  onConfirm: () => void
  onCancel: () => void
}

/** Konfirmasi hapus dokumen. Menyebut dampaknya: berkas PDF dan analisis terkait ikut terhapus. */
export function DeleteRpsDialog({ name, isPending, hasError, onConfirm, onCancel }: DeleteRpsDialogProps) {
  return (
    <ModalDialog
      title={copy.dashboard.deleteDoc.title}
      onClose={() => !isPending && onCancel()}
      showClose={false}
    >
      <p className="text-sm leading-relaxed text-text-primary">{copy.dashboard.deleteDoc.body(name)}</p>
      {hasError && <StatusNotice variant="error">{copy.dashboard.deleteDoc.failed}</StatusNotice>}
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button
          type="button"
          variant="outline"
          data-autofocus
          disabled={isPending}
          onClick={onCancel}
          className="h-11 w-full px-5 sm:w-auto"
        >
          {copy.dashboard.deleteDoc.cancel}
        </Button>
        <Button
          type="button"
          disabled={isPending}
          onClick={onConfirm}
          className="h-11 w-full bg-danger px-5 text-white hover:bg-danger/90 sm:w-auto"
        >
          {isPending ? copy.dashboard.deleteDoc.pending : copy.dashboard.deleteDoc.confirm}
        </Button>
      </div>
    </ModalDialog>
  )
}
