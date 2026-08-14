import { defineStore } from 'pinia'

export interface CartCourse {
  id: number
  title: string
  price: number
  thumbnail?: string | null
  slug?: string | null
}

const LEGACY_KEY = 'eript-cart'
const GUEST_KEY = 'eript-cart:guest'

function ownerKey(userId: number | null | undefined) {
  return userId ? `eript-cart:u${userId}` : GUEST_KEY
}

function normalize(item: any): CartCourse | null {
  if (!item || Number(item.id) <= 0) return null
  return {
    id: Number(item.id),
    title: String(item.title || ''),
    price: Math.max(0, Number(item.price) || 0),
    thumbnail: item.thumbnail || null,
    slug: item.slug || null,
  }
}

function parseList(raw: string | null): CartCourse[] {
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed.map(normalize).filter(Boolean) as CartCourse[]
  }
  catch {
    return []
  }
}

function mergeCarts(primary: CartCourse[], extra: CartCourse[]) {
  const seen = new Set(primary.map(item => item.id))
  const next = [...primary]
  for (const item of extra) {
    if (seen.has(item.id)) continue
    seen.add(item.id)
    next.push(item)
  }
  return next
}

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartCourse[]>([])
  const hydrated = ref(false)
  const ownerId = ref<number | null>(null)
  let authBound = false

  const count = computed(() => items.value.length)
  const subtotal = computed(() => items.value.reduce((sum, item) => sum + Math.max(0, Number(item.price) || 0), 0))
  const ids = computed(() => items.value.map(item => item.id))

  function currentUserId() {
    try {
      const auth = useAuthStore()
      if (!auth.isAuthenticated || !auth.user?.id) return null
      return Number(auth.user.id) || null
    }
    catch {
      return null
    }
  }

  function readBucket(key: string) {
    if (!import.meta.client) return []
    return parseList(localStorage.getItem(key))
  }

  function writeBucket(key: string, list: CartCourse[]) {
    if (!import.meta.client) return
    localStorage.setItem(key, JSON.stringify(list))
  }

  function migrateLegacy(targetKey: string) {
    if (!import.meta.client) return
    const legacy = localStorage.getItem(LEGACY_KEY)
    if (legacy == null) return
    if (!localStorage.getItem(targetKey)) localStorage.setItem(targetKey, legacy)
    localStorage.removeItem(LEGACY_KEY)
  }

  function persist() {
    if (!import.meta.client) return
    writeBucket(ownerKey(ownerId.value), items.value)
  }

  function loadOwner(userId: number | null, mergeFromGuest = false) {
    const key = ownerKey(userId)
    migrateLegacy(key)
    let next = readBucket(key)
    if (mergeFromGuest && userId) {
      next = mergeCarts(next, items.value)
      writeBucket(GUEST_KEY, [])
    }
    ownerId.value = userId
    hydrated.value = true
    items.value = next
    persist()
  }

  function switchTo(userId: number | null) {
    if (!import.meta.client) return
    if (hydrated.value && ownerId.value === userId) return
    if (hydrated.value) persist()
    const loggingIn = Boolean(userId) && ownerId.value == null && hydrated.value && items.value.length > 0
    loadOwner(userId, loggingIn)
  }

  function hydrate() {
    if (!import.meta.client) return
    bindAuth()
    switchTo(currentUserId())
  }

  function bindAuth() {
    if (authBound || !import.meta.client) return
    authBound = true
    const auth = useAuthStore()
    watch(
      () => (auth.isAuthenticated && auth.user?.id ? Number(auth.user.id) : null),
      (id) => {
        switchTo(id)
      },
    )
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

  return { items, count, subtotal, ids, hydrate, bindAuth, has, add, remove, removeMany, clear }
})
