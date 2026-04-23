<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Trạm kiểm duyệt</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">Danh sách Khóa Học</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Theo dõi, đánh giá quy chuẩn và duyệt các giáo trình được xuất bản lên The Academic EduPress.
          </p>
        </div>
        <button @click="exportData" class="px-5 py-2.5 bg-surface-lowest border border-surface-dim/50 text-on-surface text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center gap-2">
           <span class="material-symbols-outlined text-[18px]">download</span> Xuất CSV
        </button>
      </div>

      <!-- Filters & Stats row -->
      <div class="flex flex-col lg:flex-row gap-6 justify-between items-start lg:items-center">
         
         <!-- Search Input -->
         <div class="relative w-full lg:w-80">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
            <input v-model="search" @keyup.enter="fetchCourses(1)" placeholder="Tìm tiêu đề hoặc mô tả..." type="text" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-dim/30 bg-surface-lowest shadow-sm placeholder-outline focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm">
         </div>

         <!-- Segmented Tabs for filtering -->
         <div class="flex gap-2 p-1 bg-surface-low rounded-xl border border-surface-dim/30 overflow-x-auto w-full lg:w-auto scrollbar-hide">
            <button v-for="tab in tabs" :key="tab.value" 
                    @click="currentTab = tab.value; fetchCourses(1)"
                    class="px-5 py-2 text-xs font-bold rounded-lg transition-all whitespace-nowrap flex items-center gap-2"
                    :class="currentTab === tab.value ? 'bg-surface-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'">
               {{ tab.label }}
               <span v-if="tab.count !== undefined" class="px-1.5 py-0.5 rounded text-[10px]" :class="currentTab === tab.value ? 'bg-primary/10' : 'bg-surface-high opacity-70 ml-1'">{{ tab.count }}</span>
            </button>
         </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid gap-4">
        <div v-for="item in 4" :key="item" class="h-40 rounded-2xl bg-surface-high animate-pulse" />
      </div>

      <!-- Empty State -->
      <div v-else-if="courses.length === 0" class="py-20 text-center bg-surface-low rounded-2xl border border-surface-dim mt-4 shadow-inner">
         <span class="material-symbols-outlined text-6xl text-outline opacity-50 mb-4">fact_check</span>
         <h4 class="font-bold text-on-surface text-lg mb-1">Cổng Duyệt Mở</h4>
         <p class="font-medium text-sm text-on-surface-variant">Không có khóa học nào đang chờ bạn xử lý hoặc phù hợp với bộ lọc.</p>
      </div>

      <!-- Data List -->
      <div v-else class="space-y-6">
        <div v-for="course in courses" :key="course.id" class="bg-surface-lowest p-6 rounded-2xl shadow-sm border border-surface-dim hover:shadow-ambient transition-all duration-300 flex flex-col md:flex-row gap-6 group">
          
          <div class="h-32 w-full md:w-56 overflow-hidden rounded-xl bg-surface-high border border-surface-dim/10 shrink-0 relative">
            <img v-if="course.thumbnail" :src="course.thumbnail" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500">
            <div v-else class="flex h-full items-center justify-center text-4xl text-outline">📘</div>
            <div class="absolute inset-0 bg-black/10 transition-opacity opacity-0 group-hover:opacity-100"></div>
          </div>
          
          <div class="flex-1 flex flex-col min-w-0">
             <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                   <div class="flex items-center gap-2 mb-2">
                       <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest" :class="statusBadgeClasses(course.status)">
                           {{ statusLabel(course.status) }}
                       </span>
                       <span v-if="course.category?.name || course.category" class="text-[10px] uppercase font-bold text-outline-variant bg-surface-high px-2 py-0.5 rounded">
                           {{ course.category?.name || course.category }}
                       </span>
                   </div>
                   <NuxtLink :to="`/admin/courses/${course.id}`" class="block font-headline text-xl font-bold tracking-tight text-on-surface hover:text-primary transition-colors truncate">
                       {{ course.title }}
                   </NuxtLink>
                   <p class="mt-1 text-xs font-medium text-on-surface-variant uppercase tracking-widest flex items-center gap-1.5">
                       <span class="material-symbols-outlined text-[14px]">school</span> {{ course.instructor?.name || 'Giảng viên ẩn danh' }}
                   </p>
                </div>
                
                <!-- Quick stats -->
                <div class="hidden xl:flex items-center gap-4 text-xs font-semibold text-outline p-3 bg-surface-low rounded-xl border border-surface-dim/30">
                   <span class="flex flex-col items-center"><span class="material-symbols-outlined text-[18px]">menu_book</span> {{ course.lessons_count || 0 }} Bài</span>
                   <div class="w-[1px] h-6 bg-surface-dim"></div>
                   <span class="flex flex-col items-center"><span class="material-symbols-outlined text-[18px]">group</span> {{ course.enrollments_count || 0 }} Sub</span>
                </div>
             </div>

             <p class="mt-4 line-clamp-2 text-sm leading-relaxed text-on-surface-variant">{{ course.description || 'Không có mô tả chi tiết.' }}</p>

             <div v-if="course.status === 'rejected' && course.reject_reason" class="mt-4 rounded-xl bg-error-container/30 border border-error/20 px-4 py-3 text-xs text-error font-medium flex items-start gap-2">
                <span class="material-symbols-outlined text-[16px] mt-0.5" style="font-variation-settings: 'FILL' 1;">error</span>
                <span><strong>Đã từ chối vì:</strong> {{ course.reject_reason }}</span>
             </div>

             <!-- Action Row -->
             <div class="mt-6 pt-4 border-t border-surface-dim/30 flex flex-wrap items-center justify-between gap-4">
                <NuxtLink :to="`/admin/courses/${course.id}`" class="px-5 py-2.5 bg-surface-low text-on-surface-variant text-xs font-bold rounded-lg hover:bg-surface-high hover:text-primary transition-colors flex items-center gap-2">
                   <span class="material-symbols-outlined text-[16px]">overview</span> Kiểm Duyệt Tổng Thể
                </NuxtLink>

                <div class="flex items-center gap-2">
                    <template v-if="course.status === 'pending_review' || course.status === 'rejected'">
                       <button @click="showRejectModal(course)" class="px-6 py-3 bg-error/10 hover:bg-error text-error hover:text-white text-xs font-bold rounded-xl border border-error/20 hover:border-error shadow-sm hover:shadow-red-200/50 transition-all active:scale-95 flex items-center gap-2" title="Yêu cầu sửa / Từ chối">
                          <span class="material-symbols-outlined text-[18px]">cancel</span> Từ chối
                       </button>
                       <button @click="approveCourse(course)" class="px-6 py-3 bg-secondary/10 hover:bg-secondary text-secondary hover:text-white border border-secondary/20 hover:border-secondary text-xs font-bold rounded-xl shadow-sm hover:shadow-secondary/20 transition-all active:scale-95 flex items-center gap-2" title="Phê duyệt xuất bản ngay">
                          <span class="material-symbols-outlined text-[18px]">check_circle</span> Phê duyệt Xuất Bản
                       </button>
                    </template>
                </div>
             </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex flex-wrap justify-center gap-2 pt-6">
        <button v-for="page in totalPages" :key="page" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold transition-all" :class="page === currentPage ? 'cta-gradient text-white shadow-md' : 'bg-surface-lowest hover:bg-surface-low text-on-surface border border-surface-dim/30 text-on-surface-variant'" @click="fetchCourses(page)">{{ page }}</button>
      </div>

      <!-- Reject Modal Overlay -->
      <Teleport to="body">
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="rejectTarget = null">
          <div class="w-full max-w-lg rounded-[2rem] bg-surface-lowest p-8 shadow-ambient modal-bounce border border-surface-dim">
            <div class="mb-6 flex items-center justify-between pb-4 border-b border-surface-dim/30">
              <h3 class="font-headline text-xl font-bold text-on-surface flex items-center gap-2">
                 <span class="material-symbols-outlined text-error">assignment_return</span> Từ chối Xuất Bản
              </h3>
              <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="rejectTarget = null">
                 <span class="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>
            
            <p class="mb-5 text-sm text-on-surface font-medium border-l-2 border-primary pl-3">Khoá học: <strong>{{ rejectTarget?.title }}</strong></p>
            
            <div class="space-y-2">
               <label class="block text-sm font-bold text-on-surface-variant">Lý do từ chối <span class="text-error">*</span></label>
               <textarea v-model="rejectReason" rows="5" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-error focus:ring-1 focus:ring-error shadow-sm outline-none transition-all placeholder-outline" placeholder="Nêu rõ lý do từ chối (VD: File video lỗi âm thanh, bản quyền hình ảnh, v.v...)"></textarea>
               <p class="text-[10px] text-outline font-medium text-right">Lý do này sẽ hiển thị trực tiếp trong Dashboard của Giảng viên.</p>
            </div>
            
            <div class="mt-6 pt-4 border-t border-surface-dim/30 flex justify-end gap-3">
              <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-bold bg-surface-low hover:bg-surface-high text-on-surface transition-colors" @click="rejectTarget = null">Hủy bỏ</button>
              <button :disabled="!rejectReason.trim() || rejectLoading" class="px-6 py-2.5 bg-error text-white rounded-lg text-sm font-bold shadow-md hover:shadow-lg transition-transform active:scale-95 disabled:opacity-50 flex items-center gap-2" @click="rejectCourse">
                 <span v-if="rejectLoading" class="material-symbols-outlined animate-spin text-[16px]">refresh</span>
                 {{ rejectLoading ? 'Đang xử lý...' : 'Xác nhận Khóa' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const route = useRoute()

const courses = ref<any[]>([])
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const currentTab = ref((route.query.status as string) || '')
const search = ref((route.query.search as string) || '')
const rejectTarget = ref<any>(null)
const rejectReason = ref('')
const rejectLoading = ref(false)
const { exportToCSV } = useExport()

const tabs = ref([
  { value: '', label: 'Toàn bộ', count: undefined as number | undefined },
  { value: 'pending_review', label: 'Chờ duyệt', count: undefined as number | undefined },
  { value: 'published', label: 'Đã trực tuyến', count: undefined as number | undefined },
  { value: 'draft', label: 'Bản Nháp (Draft)', count: undefined as number | undefined },
  { value: 'rejected', label: 'Bị từ chối', count: undefined as number | undefined },
])

const exportData = () => {
  exportToCSV(
    courses.value,
    [
      { key: 'id', label: 'ID' },
      { key: 'title', label: 'Tên khóa học' },
      { key: 'status', label: 'Trạng thái', format: (value) => statusLabel(String(value || 'draft')) },
      { key: 'category.name', label: 'Danh mục' },
      { key: 'instructor.name', label: 'Giảng viên' },
      { key: 'lessons_count', label: 'Số bài học' },
      { key: 'enrollments_count', label: 'Số học viên' },
      { key: 'created_at', label: 'Ngày tạo' },
    ],
    'admin_courses',
  )
}

const statusLabel = (status: string) => ({ published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[status] || status)

const statusBadgeClasses = (status: string) => {
   const map: Record<string, string> = {
      published: 'bg-secondary/10 text-secondary border border-secondary/20',
      draft: 'bg-surface-high text-outline border border-surface-dim/30',
      pending_review: 'bg-amber-500/10 text-amber-700 border border-amber-500/20',
      rejected: 'bg-error-container/40 text-error border border-error/20'
   }
   return map[status] || map.draft
}

async function fetchCourses(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const q = new URLSearchParams({ page: String(page), per_page: '10' })
    if (currentTab.value) q.set('status', currentTab.value)
    if (search.value.trim()) q.set('search', search.value.trim())
    const data = await $fetch<any>(`/api/admin/courses?${q}`, { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [], last_page: 1 }))
    courses.value = data.data || []
    totalPages.value = data.last_page || 1
  } finally {
    loading.value = false
  }
}

async function fetchTabCounts() {
  try {
    const stats = await $fetch<any>('/api/admin/stats', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null)
    const byStatus = stats?.courses_by_status || {}
    const total = Object.values(byStatus).reduce((sum: number, value: any) => sum + Number(value || 0), 0)
    tabs.value = tabs.value.map((tab) => ({ ...tab, count: tab.value ? Number(byStatus[tab.value] || 0) : total }))
  } catch {}
}

async function approveCourse(course: any) {
  if(!confirm(`Xác nhận Duyệt khóa học: "${course.title}" để Public ngay?`)) return
  try {
    await $fetch(`/api/admin/courses/${course.id}/approve`, { method: 'PUT', headers: { Authorization: `Bearer ${auth.token}` } })
    await Promise.all([fetchCourses(currentPage.value), fetchTabCounts()])
  } catch (e: any) {
    alert(e?.data?.message || 'Có lỗi trong quá trình phê duyệt.')
  }
}

function showRejectModal(course: any) {
  rejectTarget.value = course
  rejectReason.value = ''
}

async function rejectCourse() {
  if (!rejectTarget.value || !rejectReason.value.trim()) return
  rejectLoading.value = true
  try {
    await $fetch(`/api/admin/courses/${rejectTarget.value.id}/reject`, {
      method: 'PUT',
      body: { reject_reason: rejectReason.value },
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    rejectTarget.value = null
    await Promise.all([fetchCourses(currentPage.value), fetchTabCounts()])
  } catch (e: any) {
    alert(e?.data?.message || 'Từ chối thất bại')
  } finally {
    rejectLoading.value = false
  }
}

onMounted(() => Promise.all([fetchCourses(1), fetchTabCounts()]))
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
