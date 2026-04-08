<template>
  <div>
    <NuxtLayout name="admin">
      <div v-if="loading" class="animate-pulse space-y-4">
        <div class="h-8 bg-gray-200 rounded w-1/3"></div>
        <div class="h-64 bg-gray-200 rounded-xl"></div>
      </div>

      <div v-else-if="course">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
          <NuxtLink to="/admin/courses" class="btn-ghost text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Quay lại
          </NuxtLink>
          <NuxtLink :to="`/admin/courses/${courseId}/curriculum`" class="btn-ghost text-sm">
            Quản lý curriculum
          </NuxtLink>
          <h1 class="text-xl font-bold text-gray-900">Duyệt khóa học</h1>
        </div>

        <!-- Course info -->
        <div class="grid lg:grid-cols-3 gap-8">
          <div class="lg:col-span-2 space-y-6">
            <!-- Basic info card -->
            <div class="card-glass p-6">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-32 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                  <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                  </div>
                </div>
                <div class="flex-1">
                  <h2 class="text-lg font-bold text-gray-900 mb-1">{{ course.title }}</h2>
                  <p class="text-sm text-gray-500 mb-2">Giảng viên: <strong>{{ course.instructor?.name }}</strong></p>
                  <div class="flex items-center gap-3 flex-wrap">
                    <span class="badge" :class="statusBadge(course.status)">{{ statusLabel(course.status) }}</span>
                    <span class="text-sm text-gray-600">{{ course.lessons_count || 0 }} bài học</span>
                    <span class="text-sm font-semibold text-primary">{{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}</span>
                  </div>
                </div>
              </div>

              <div v-if="course.description" class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Mô tả khóa học</h3>
                <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ course.description }}</p>
              </div>
            </div>

            <!-- Lessons list -->
            <div class="card-glass overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50 backdrop-blur-sm">
                <h3 class="font-semibold text-gray-900">Nội dung khóa học ({{ lessons.length }} bài)</h3>
              </div>
              <div v-if="lessons.length === 0" class="p-5 text-center text-sm text-gray-500">Chưa có bài giảng nào</div>
              <div class="divide-y divide-gray-100">
                <div v-for="(lesson, idx) in lessons" :key="lesson.id" class="px-5 py-3 flex items-center gap-3">
                  <span class="text-xs text-gray-400 w-6">{{ idx + 1 }}</span>
                  <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <span class="text-sm text-gray-900 flex-1">{{ lesson.title }}</span>
                  <span v-if="lesson.duration" class="text-xs text-gray-400">{{ Math.floor(lesson.duration / 60) }}p{{ lesson.duration % 60 }}s</span>
                  <span v-if="lesson.is_preview" class="badge bg-blue-100 text-blue-700 text-xs">Xem thử</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions sidebar -->
          <div class="space-y-4">
            <!-- Current status -->
            <div class="card-glass p-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Trạng thái</h3>
              <span class="badge" :class="statusBadge(course.status)">{{ statusLabel(course.status) }}</span>
            </div>

            <!-- Approve/Reject actions (for pending) -->
            <div v-if="course.status === 'pending_review'" class="card-glass p-5 space-y-3 relative overflow-hidden group">
              <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400 to-amber-500"></div>
              <h3 class="text-sm font-semibold text-gray-700 mb-1">Hành động kiểm duyệt</h3>

              <button @click="approveCourse" :disabled="actionLoading" class="btn-primary w-full">
                <svg v-if="actionLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                ✓ Duyệt xuất bản
              </button>

              <div>
                <label class="label">Lý do từ chối</label>
                <textarea v-model="rejectReason" rows="3" class="input mb-2" placeholder="Nhập lý do từ chối..."></textarea>
                <button @click="rejectCourse" :disabled="actionLoading || !rejectReason" class="btn-danger w-full">
                  ✗ Từ chối khóa học
                </button>
              </div>
            </div>

            <div v-else-if="course.status === 'rejected'" class="card-glass p-5 relative overflow-hidden">
              <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-red-400 to-red-500"></div>
              <h3 class="text-sm font-semibold text-red-600 mb-2">Lý do từ chối</h3>
              <p class="text-sm text-gray-700">{{ course.reject_reason }}</p>
              <button @click="approveCourse" class="btn-primary w-full mt-3 text-sm">Duyệt lại</button>
            </div>

            <div v-else-if="course.status === 'published'" class="card-glass p-5 relative overflow-hidden">
              <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-400 to-emerald-500"></div>
              <p class="text-sm text-primary font-medium">✓ Khóa học đã được xuất bản</p>
              <p v-if="course.published_at" class="text-xs text-gray-500 mt-1">{{ formatDate(course.published_at) }}</p>
            </div>

            <!-- Instructor info -->
            <div class="card-glass p-5 items-center">
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Thông tin giảng viên</h3>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-light flex items-center justify-center">
                  <img v-if="course.instructor?.avatar" :src="course.instructor.avatar" class="w-full h-full rounded-full object-cover" />
                  <span v-else class="text-sm font-semibold text-primary">{{ course.instructor?.name?.charAt(0) }}</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ course.instructor?.name }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-20 text-gray-500">Không tìm thấy khóa học</div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const route = useRoute()
const auth = useAuthStore()

const courseId = route.params.id as string
const course = ref<any>(null)
const lessons = ref<any[]>([])
const loading = ref(true)
const actionLoading = ref(false)
const rejectReason = ref('')

function statusBadge(s: string): string {
  const m: Record<string, string> = { published: 'bg-primary-light text-primary-dark', draft: 'bg-gray-100 text-gray-600', pending_review: 'bg-amber-100 text-amber-700', rejected: 'bg-red-100 text-red-700' }
  return m[s] || 'bg-gray-100 text-gray-600'
}
function statusLabel(s: string): string {
  const m: Record<string, string> = { published: 'Đã xuất bản', draft: 'Nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }
  return m[s] || s
}
function formatPrice(p: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p)
}
function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('vi-VN', { year: 'numeric', month: 'long', day: 'numeric' })
}

async function approveCourse() {
  actionLoading.value = true
  try {
    await useApi(`/admin/courses/${courseId}/approve`, { method: 'PUT', token: auth.token })
    course.value.status = 'published'
    course.value.published_at = new Date().toISOString()
    course.value.reject_reason = null
  } catch (e: any) {
    alert(e?.data?.message || 'Thất bại')
  } finally {
    actionLoading.value = false
  }
}

async function rejectCourse() {
  if (!rejectReason.value.trim()) return
  actionLoading.value = true
  try {
    await useApi(`/admin/courses/${courseId}/reject`, {
      method: 'PUT',
      body: { reject_reason: rejectReason.value },
      token: auth.token,
    })
    course.value.status = 'rejected'
    course.value.reject_reason = rejectReason.value
  } catch (e: any) {
    alert(e?.data?.message || 'Thất bại')
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  try {
    const courseData = await useApi<any>(`/admin/courses/${courseId}`, { token: auth.token })
    course.value = courseData
    lessons.value = Array.isArray(courseData?.lessons) ? courseData.lessons : []
  } catch {
    course.value = null
  } finally {
    loading.value = false
  }
})
</script>
