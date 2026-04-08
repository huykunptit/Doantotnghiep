<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Filter bar -->
      <div class="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center justify-between animate-fade-in-up">
        <div class="flex gap-2 w-full sm:w-auto flex-1 max-w-md">
          <div class="relative flex-1">
            <input v-model="search" type="text" placeholder="Tìm tiêu đề, mô tả..." class="input pl-10 h-10 text-sm w-full"
              @keyup.enter="fetchCourses(1)" />
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
          <button class="btn-secondary text-sm h-10 px-5 flex-shrink-0" @click="fetchCourses(1)">Tìm kiếm</button>
        </div>
        
        <button class="btn-export text-sm h-10 px-4 flex items-center gap-2 text-gray-700 w-full sm:w-auto" @click="exportData">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          Xuất CSV
        </button>
      </div>

      <!-- Status tabs -->
      <div class="flex gap-1 mb-6 bg-white/50 border border-gray-100 rounded-xl p-1.5 w-fit overflow-x-auto shadow-sm animate-fade-in-up" style="animation-delay: 0.1s">
        <button v-for="tab in tabs" :key="tab.value" @click="currentTab = tab.value; fetchCourses(1)"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap flex-shrink-0"
          :class="currentTab === tab.value ? 'bg-white text-gray-900 shadow-sm border border-gray-100' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50 border border-transparent'">
          <span class="w-2.5 h-2.5 rounded-full shadow-sm" :class="tab.dot"></span>
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="ml-1 text-[11px] px-1.5 py-0.5 rounded-full"
            :class="currentTab === tab.value ? 'bg-primary/10 text-primary font-bold' : 'bg-gray-100 text-gray-500'">{{ tab.count }}</span>
        </button>
      </div>

      <!-- Loading skeletons -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 4" :key="i" class="card p-4 flex gap-4 animate-pulse">
          <div class="w-24 h-16 bg-gray-200 rounded-lg flex-shrink-0"></div>
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 rounded w-2/3"></div>
            <div class="h-3 bg-gray-200 rounded w-1/3"></div>
            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
          </div>
          <div class="h-6 w-20 bg-gray-200 rounded-full self-start flex-shrink-0"></div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="courses.length === 0" class="card-glass p-16 text-center animate-fade-in-up" style="animation-delay: 0.2s">
        <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-gray-100">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-900 mb-1">Không có khóa học nào</h3>
        <p class="text-sm text-gray-500">Chưa có dữ liệu phù hợp với điều kiện tìm kiếm.</p>
      </div>

      <!-- Course list -->
      <div v-else class="space-y-4 animate-fade-in-up" style="animation-delay: 0.2s">
        <div v-for="course in courses" :key="course.id"
          class="card hover:shadow-lg transition-all duration-300 overflow-hidden group">
          <div class="flex flex-col sm:flex-row gap-0 h-full">
            <!-- Thumbnail strip on left -->
            <div class="sm:w-48 h-32 sm:h-auto bg-gray-100 flex-shrink-0 relative overflow-hidden">
              <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
              <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-200">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60"></div>
              <!-- Category badge overlay -->
              <div v-if="course.category?.name" class="absolute bottom-2 left-2 z-10">
                <span class="text-[10px] font-semibold bg-white/20 backdrop-blur-md border border-white/30 text-white px-2 py-1 rounded-lg">{{ course.category.name }}</span>
              </div>
            </div>

            <div class="flex-1 p-4 flex flex-col gap-3">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <h3 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2">{{ course.title }}</h3>
                  <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                      <div class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-[9px] font-bold text-blue-600">{{ course.instructor?.name?.charAt(0)?.toUpperCase() }}</span>
                      </div>
                      {{ course.instructor?.name }}
                    </div>
                    <span class="text-xs text-gray-400">·</span>
                    <span class="text-xs text-gray-500">{{ course.lessons_count || 0 }} bài học</span>
                    <span class="text-xs text-gray-400">·</span>
                    <span class="text-xs font-semibold" :class="course.price > 0 ? 'text-emerald-600' : 'text-blue-600'">
                      {{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
                    </span>
                    <span class="text-xs text-gray-400">·</span>
                    <span class="text-xs text-gray-400">{{ course.enrollments_count || 0 }} học viên</span>
                  </div>
                </div>
                <span class="badge flex-shrink-0 text-xs" :class="statusBadge(course.status)">{{ statusLabel(course.status) }}</span>
              </div>

              <!-- Reject reason notice -->
              <div v-if="course.status === 'rejected' && course.reject_reason"
                class="flex items-start gap-2 text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><strong>Lý do từ chối:</strong> {{ course.reject_reason }}</span>
              </div>

              <!-- Action buttons -->
              <div class="flex items-center gap-2 flex-wrap">
                <NuxtLink :to="`/admin/courses/${course.id}`"
                  class="btn-secondary text-xs h-7 px-3 flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  Xem chi tiết
                </NuxtLink>
                <template v-if="course.status === 'pending_review'">
                  <button @click="approveCourse(course)"
                    class="btn-primary text-xs h-7 px-3 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    Duyệt xuất bản
                  </button>
                  <button @click="showRejectModal(course)"
                    class="btn-danger text-xs h-7 px-3 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    Từ chối
                  </button>
                </template>
                <template v-else-if="course.status === 'rejected'">
                  <button @click="approveCourse(course)" class="btn-primary text-xs h-7 px-3">Duyệt lại</button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 || totalItems > perPage" class="pagination-wrapper mt-6">
        <div class="flex items-center gap-3">
          <p class="text-xs text-gray-500">Hiển thị <span class="font-medium text-gray-900">{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }}</span> / {{ totalItems }}</p>
          <select v-model="perPage" class="input h-8 py-1.5 text-xs w-auto focus:ring-0" @change="fetchCourses(1)">
            <option :value="10">10 / trang</option>
            <option :value="15">15 / trang</option>
            <option :value="30">30 / trang</option>
          </select>
        </div>
        <div class="flex gap-1">
          <button @click="fetchCourses(currentPage - 1)" :disabled="currentPage <= 1"
            class="pagination-btn" :class="currentPage <= 1 ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">‹</button>
          <button v-for="p in paginationRange" :key="p" @click="typeof p === 'number' && fetchCourses(p)"
            class="pagination-btn" :class="p === currentPage ? 'pagination-btn-active' : p === '…' ? 'cursor-default text-gray-400' : 'pagination-btn-inactive'">{{ p }}</button>
          <button @click="fetchCourses(currentPage + 1)" :disabled="currentPage >= totalPages"
            class="pagination-btn" :class="currentPage >= totalPages ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">›</button>
        </div>
      </div>

      <!-- Reject Modal -->
      <Teleport to="body">
        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100"
          leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="rejectTarget = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
              <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </div>
                <h3 class="text-base font-bold text-gray-900">Từ chối khóa học</h3>
              </div>
              <div class="p-6 space-y-3">
                <div class="bg-gray-50 rounded-xl p-3">
                  <p class="text-sm font-semibold text-gray-900 line-clamp-2">{{ rejectTarget.title }}</p>
                  <p class="text-xs text-gray-500 mt-0.5">{{ rejectTarget.instructor?.name }}</p>
                </div>
                <div>
                  <label class="label">Lý do từ chối <span class="text-red-500">*</span></label>
                  <textarea v-model="rejectReason" rows="3" class="input resize-none" placeholder="Nêu rõ lý do để giảng viên có thể cải thiện..."></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                  <button @click="rejectTarget = null" class="btn-ghost text-sm">Hủy</button>
                  <button @click="rejectCourse" :disabled="!rejectReason.trim() || rejectLoading" class="btn-danger text-sm px-5">
                    <svg v-if="rejectLoading" class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Xác nhận từ chối
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const route = useRoute()
const { exportToCSV } = useExport()

const courses = ref<any[]>([])
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(15)
const currentTab = ref((route.query.status as string) || '')
const search = ref((route.query.search as string) || '')
const rejectTarget = ref<any>(null)
const rejectReason = ref('')
const rejectLoading = ref(false)

const tabs = ref([
  { value: '', label: 'Tất cả', dot: 'bg-gray-400', count: undefined as number | undefined },
  { value: 'pending_review', label: 'Chờ duyệt', dot: 'bg-amber-500', count: undefined as number | undefined },
  { value: 'published', label: 'Đã xuất bản', dot: 'bg-primary', count: undefined as number | undefined },
  { value: 'draft', label: 'Nháp', dot: 'bg-gray-300', count: undefined as number | undefined },
  { value: 'rejected', label: 'Từ chối', dot: 'bg-red-500', count: undefined as number | undefined },
])

function statusBadge(s: string): string {
  const m: Record<string, string> = {
    published: 'bg-primary-light text-primary-dark',
    draft: 'bg-gray-100 text-gray-600',
    pending_review: 'bg-amber-100 text-amber-700',
    rejected: 'bg-red-100 text-red-700',
  }
  return m[s] || 'bg-gray-100 text-gray-600'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    published: 'Đã xuất bản', draft: 'Nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối',
  }
  return m[s] || s
}

function formatPrice(p: number): string {
  return p > 0 ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p) : 'Miễn phí'
}

const paginationRange = computed(() => {
  const total = totalPages.value, cur = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | string)[] = [1]
  if (cur > 3) pages.push('…')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('…')
  pages.push(total)
  return pages
})

async function fetchCourses(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (currentTab.value) q.set('status', currentTab.value)
    if (search.value.trim()) q.set('search', search.value.trim())
    await navigateTo({ path: '/admin/courses', query: Object.fromEntries(q.entries()) }, { replace: true })
    const data = await useApi<any>(`/admin/courses?${q}`, { token: auth.token })
    courses.value = data.data || []
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } catch {}
  loading.value = false
}

async function fetchTabCounts() {
  try {
    const stats = await useApi<any>('/admin/stats', { token: auth.token })
    const byStatus = stats?.courses_by_status || {}
    const total = Object.values(byStatus).reduce((s: number, v: any) => s + Number(v || 0), 0)
    tabs.value = tabs.value.map((tab) => ({
      ...tab,
      count: tab.value ? Number(byStatus[tab.value] || 0) : total,
    }))
  } catch {}
}

async function approveCourse(course: any) {
  try {
    await useApi(`/admin/courses/${course.id}/approve`, { method: 'PUT', token: auth.token })
    course.status = 'published'
    await fetchTabCounts()
  } catch (e: any) {
    alert(e?.data?.message || 'Thất bại')
  }
}

function showRejectModal(course: any) {
  rejectTarget.value = course
  rejectReason.value = ''
}

async function rejectCourse() {
  if (!rejectTarget.value) return
  rejectLoading.value = true
  try {
    await useApi(`/admin/courses/${rejectTarget.value.id}/reject`, {
      method: 'PUT',
      body: { reject_reason: rejectReason.value },
      token: auth.token,
    })
    rejectTarget.value.status = 'rejected'
    rejectTarget.value.reject_reason = rejectReason.value
    rejectTarget.value = null
    await fetchTabCounts()
  } catch (e: any) {
    alert(e?.data?.message || 'Thất bại')
  } finally {
    rejectLoading.value = false
  }
}

async function exportData() {
  try {
    const q = new URLSearchParams({ page: '1', per_page: '1000' })
    if (currentTab.value) q.set('status', currentTab.value)
    if (search.value.trim()) q.set('search', search.value.trim())
    const data = await useApi<any>(`/admin/courses?${q}`, { token: auth.token })
    const rows = data.data || []
    
    exportToCSV(rows, [
      { key: 'id', label: 'ID' },
      { key: 'title', label: 'Tên khóa học' },
      { key: 'category.name', label: 'Danh mục' },
      { key: 'instructor.name', label: 'Giảng viên' },
      { key: 'price', label: 'Giá (VND)', format: (v) => String(v || 0) },
      { key: 'status', label: 'Trạng thái', format: (v) => statusLabel(v) },
      { key: 'enrollments_count', label: 'Học viên' },
    ], 'danh_sach_khoa_hoc')
  } catch (e) {
    alert('Không thể xuất dữ liệu')
  }
}

onMounted(() => Promise.all([fetchCourses(), fetchTabCounts()]))
</script>

