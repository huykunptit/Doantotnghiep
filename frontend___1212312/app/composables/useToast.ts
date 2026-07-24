import { readonly, ref } from 'vue'

export interface Toast {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
  duration: number
}

const toasts = ref<Toast[]>([])

function genId() {
  return `t-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`
}

function remove(id: string) {
  const idx = toasts.value.findIndex(t => t.id === id)
  if (idx !== -1) toasts.value.splice(idx, 1)
}

function add(opts: Omit<Toast, 'id' | 'duration'> & { duration?: number }) {
  const id = genId()
  const duration = opts.duration ?? 4000
  toasts.value.push({ ...opts, id, duration })
  setTimeout(() => remove(id), duration)
  return id
}

export function useToast() {
  return {
    toasts: readonly(toasts),
    success: (title: string, message?: string, duration?: number) =>
      add({ type: 'success', title, message, duration }),
    error: (title: string, message?: string, duration?: number) =>
      add({ type: 'error', title, message, duration: duration ?? 6000 }),
    warning: (title: string, message?: string, duration?: number) =>
      add({ type: 'warning', title, message, duration }),
    info: (title: string, message?: string, duration?: number) =>
      add({ type: 'info', title, message, duration }),
    remove,
    add,
  }
}
