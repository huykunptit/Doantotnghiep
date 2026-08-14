import { defineStore } from 'pinia'

export interface CartCourse {
  id: number
  title: string
  price: number
  thumbnail?: string | null
  slug?: string | null
}

const STORAGE_KEY = 'eript-cart'

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartCourse[]>([])
  const hydrated = ref(false)

  const count = computed(() => items.value.length)
  const subtotal = computed(() => items.value.reduce((sum, item) => sum + Math.max(0, Number(item.price) || 0), 0))
  const ids = computed(() => items.value.map(item => item.id))

  function hydrate() {
    if (hydrated.value || !import.meta.client) return
    hydrated.value = true
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      const parsed = raw ? JSON.parse(raw) : []
      if (!Array.isArray(parsed)) return
      items.value = parsed
        .filter((item: any) => item && Number(item.id) > 0)
        .map((item: any) => ({
          id: Number(item.id),
          title: String(item.title || ''),
          price: Math.max(0, Number(item.price) || 0),
          thumbnail: item.thumbnail || null,
          slug: item.slug || null,
        }))
    }
    catch {
      items.value = []
    }
  }

  function persist() {
    if (!import.meta.client) return
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value))
  }

  function has(id: number) {
    hydrate()
    return items.value.some(item => item.id === id)
  }

  function add(course: CartCourse) {
    hydrate()
    if (!course?.id || has(course.id) || (course.price || 0) <= 0) return false
    items.value = [...items.value, {
      id: Number(course.id),
      title: course.title,
      price: Math.max(0, Number(course.price) || 0),
      thumbnail: course.thumbnail || null,
      slug: course.slug || null,
    }]
    persist()
    return true
  }

  function remove(id: number) {
    hydrate()
    items.value = items.value.filter(item => item.id !== id)
    persist()
  }

  function removeMany(courseIds: number[]) {
    hydrate()
    const drop = new Set(courseIds.map(Number))
    items.value = items.value.filter(item => !drop.has(item.id))
    persist()
  }

  function clear() {
    hydrate()
    items.value = []
    persist()
  }

  return { items, count, subtotal, ids, hydrate, has, add, remove, removeMany, clear }
})
