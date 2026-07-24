import { computed, onScopeDispose, ref, watch, type Ref } from 'vue'

export interface AdminApiError {
  message: string
  statusCode?: number
  fieldErrors: Record<string, string[]>
  original: unknown
}

export interface AdminListQuery {
  page: number
  per_page: number
  search?: string
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  [key: string]: unknown
}

export interface UseAdminListOptions<TFilters extends Record<string, any>> {
  initialPage?: number
  initialPerPage?: number
  initialSortField?: string | null
  initialSortOrder?: 1 | -1 | 0 | null
  initialSearch?: string
  initialFilters?: TFilters
  debounceMs?: number
  searchParam?: string
  pageParam?: string
  perPageParam?: string
  sortFieldParam?: string
  sortOrderParam?: string
}

function firstString(value: unknown): string | undefined {
  if (typeof value === 'string' && value.trim()) return value
  if (Array.isArray(value)) return firstString(value[0])
  return undefined
}

export function normalizeAdminApiError(
  error: unknown,
  fallbackMessage = 'Không thể tải dữ liệu. Vui lòng thử lại.',
): AdminApiError {
  const source = error && typeof error === 'object' ? error as Record<string, any> : {}
  const data = source.data ?? source.response?._data ?? source.response?.data ?? {}
  const rawErrors = data.errors ?? source.errors ?? {}
  const fieldErrors: Record<string, string[]> = {}

  if (rawErrors && typeof rawErrors === 'object' && !Array.isArray(rawErrors)) {
    Object.entries(rawErrors).forEach(([field, messages]) => {
      const values = Array.isArray(messages) ? messages : [messages]
      fieldErrors[field] = values
        .map((message) => firstString(message) ?? String(message ?? ''))
        .filter(Boolean)
    })
  }

  const status = source.statusCode
    ?? source.status
    ?? source.response?.status
    ?? data.statusCode
    ?? data.status

  return {
    message: firstString(data.message)
      ?? firstString(source.message)
      ?? Object.values(fieldErrors)[0]?.[0]
      ?? fallbackMessage,
    statusCode: Number.isFinite(Number(status)) ? Number(status) : undefined,
    fieldErrors,
    original: error,
  }
}

function filterValue(value: unknown) {
  if (value && typeof value === 'object' && !Array.isArray(value) && 'value' in value) {
    return (value as { value: unknown }).value
  }
  return value
}

export function useAdminList<TFilters extends Record<string, any> = Record<string, any>>(
  options: UseAdminListOptions<TFilters> = {},
) {
  const {
    initialPage = 1,
    initialPerPage = 20,
    initialSortField = null,
    initialSortOrder = null,
    initialSearch = '',
    initialFilters = {} as TFilters,
    debounceMs = 350,
    searchParam = 'search',
    pageParam = 'page',
    perPageParam = 'per_page',
    sortFieldParam = 'sort_by',
    sortOrderParam = 'sort_order',
  } = options

  const page = ref(initialPage)
  const perPage = ref(initialPerPage)
  const sortField = ref<string | null>(initialSortField)
  const sortOrder = ref<1 | -1 | 0 | null>(initialSortOrder)
  const search = ref(initialSearch)
  const debouncedSearch = ref(initialSearch)
  const filters = ref<TFilters>({ ...initialFilters }) as Ref<TFilters>
  const loading = ref(false)
  const error = ref<AdminApiError | null>(null)
  let searchTimer: ReturnType<typeof setTimeout> | undefined

  watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      debouncedSearch.value = value.trim()
      page.value = 1
    }, Math.max(0, debounceMs))
  })

  onScopeDispose(() => clearTimeout(searchTimer))

  const query = computed<AdminListQuery>(() => {
    const result: AdminListQuery = {
      page: page.value,
      per_page: perPage.value,
    }

    if (pageParam !== 'page') {
      delete result.page
      result[pageParam] = page.value
    }
    if (perPageParam !== 'per_page') {
      delete result.per_page
      result[perPageParam] = perPage.value
    }
    if (debouncedSearch.value) result[searchParam] = debouncedSearch.value
    if (sortField.value) {
      result[sortFieldParam] = sortField.value
      result[sortOrderParam] = sortOrder.value === -1 ? 'desc' : 'asc'
    }

    Object.entries(filters.value).forEach(([key, rawValue]) => {
      const value = filterValue(rawValue)
      if (value !== '' && value !== null && value !== undefined) result[key] = value
    })

    return result
  })

  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter((rawValue) => {
      const value = filterValue(rawValue)
      return value !== '' && value !== null && value !== undefined
    }).length + (debouncedSearch.value ? 1 : 0),
  )

  function setPage(eventOrPage: number | { page?: number; rows?: number }) {
    if (typeof eventOrPage === 'number') {
      page.value = Math.max(1, eventOrPage)
      return
    }
    page.value = Math.max(1, Number(eventOrPage.page ?? 0) + 1)
    if (eventOrPage.rows) perPage.value = Number(eventOrPage.rows)
  }

  function setPerPage(value: number) {
    perPage.value = value
    page.value = 1
  }

  function setSort(event: { sortField?: string | ((row: any) => string); sortOrder?: 1 | -1 | 0 | null }) {
    sortField.value = typeof event.sortField === 'string' ? event.sortField : null
    sortOrder.value = event.sortOrder ?? null
    page.value = 1
  }

  function setFilters(value: TFilters | { filters?: TFilters }) {
    filters.value = { ...(('filters' in value ? value.filters : value) ?? {}) } as TFilters
    page.value = 1
  }

  function setFilter<K extends keyof TFilters>(key: K, value: TFilters[K]) {
    filters.value = { ...filters.value, [key]: value }
    page.value = 1
  }

  function reset() {
    page.value = 1
    sortField.value = initialSortField
    sortOrder.value = initialSortOrder
    search.value = initialSearch
    debouncedSearch.value = initialSearch
    filters.value = { ...initialFilters }
    error.value = null
  }

  async function execute<T>(
    request: (query: AdminListQuery) => Promise<T>,
    fallbackMessage?: string,
  ): Promise<T | undefined> {
    loading.value = true
    error.value = null
    try {
      return await request({ ...query.value })
    } catch (caught) {
      error.value = normalizeAdminApiError(caught, fallbackMessage)
      return undefined
    } finally {
      loading.value = false
    }
  }

  return {
    page,
    perPage,
    sortField,
    sortOrder,
    search,
    debouncedSearch,
    filters,
    query,
    activeFilterCount,
    loading,
    error,
    setPage,
    setPerPage,
    setSort,
    setFilters,
    setFilter,
    reset,
    execute,
    normalizeError: normalizeAdminApiError,
  }
}
