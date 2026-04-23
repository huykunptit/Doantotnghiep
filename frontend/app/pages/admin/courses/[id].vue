<template>
  <NuxtLayout name="admin">
    <section v-if="loading" class="space-y-4">
      <div class="h-10 w-48 rounded-2xl bg-surface-high animate-pulse" />
      <div class="h-80 rounded-3xl bg-surface-high animate-pulse" />
    </section>

    <section v-else-if="course" class="space-y-8">
      <AppPageHeader eyebrow="Admin" title="Chi tiết khóa học" description="Kiểm tra thông tin khóa học, giảng viên và thực hiện duyệt hoặc từ chối.">
        <template #actions>
          <NuxtLink to="/admin/courses"><UiButton variant="secondary">Quay lại</UiButton></NuxtLink>
          <NuxtLink :to="`/admin/courses/${courseId}/curriculum`"><UiButton variant="secondary">Xem curriculum</UiButton></NuxtLink>
          <NuxtLink v-if="course.preview_urls?.course" :to="course.preview_urls.course"><UiButton variant="secondary">Xem trang khóa học</UiButton></NuxtLink>
          <NuxtLink v-if="course.preview_urls?.learn" :to="course.preview_urls.learn"><UiButton>Xem thử bài học</UiButton></NuxtLink>
        </template>
      </AppPageHeader>

      <div class="grid gap-8 xl:grid-cols-[1.1fr_0.9fr]">
        <UiCard>
          <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row">
              <div class="h-48 w-full overflow-hidden rounded-3xl bg-surface-low lg:w-72">
                <img v-if="course.thumbnail" :src="course.thumbnail" class="h-full w-full object-cover">
                <div v-else class="flex h-full items-center justify-center text-5xl text-outline/50">📘</div>
              </div>
              <div class="flex-1 space-y-3">
                <div class="flex flex-wrap gap-2">
                  <StatusBadge :value="statusLabel(course.status)" />
                  <StatusBadge v-if="course.category?.name || course.category" :value="course.category?.name || course.category" />
                </div>
                <h1 class="text-2xl font-bold text-on-surface">{{ course.title }}</h1>
                <p class="text-sm leading-6 text-on-surface-variant">{{ course.description }}</p>
                <div class="grid gap-3 sm:grid-cols-2 text-sm text-on-surface-variant">
                  <p>Giảng viên: <strong class="text-on-surface">{{ course.instructor?.name }}</strong></p>
                  <p>Bài học: <strong class="text-on-surface">{{ lessons.length }}</strong></p>
                  <p>Học viên: <strong class="text-on-surface">{{ course.enrollments_count || 0 }}</strong></p>
                  <p>Giá: <strong class="text-on-surface">{{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}</strong></p>
                </div>
              </div>
            </div>

            <div>
              <h2 class="text-lg font-semibold text-on-surface">Danh sách bài học</h2>
              <div v-if="lessons.length === 0" class="mt-4"><UiEmptyState title="Chưa có bài học" description="Khóa học này chưa có nội dung để duyệt." /></div>
              <div v-else class="mt-4 space-y-3">
                <div v-for="(lesson, index) in lessons" :key="lesson.id" class="flex items-center gap-4 rounded-2xl border border-surface-dim px-4 py-4">
                  <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-surface-low text-sm font-semibold text-on-surface-variant">{{ index + 1 }}</div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-on-surface">{{ lesson.title }}</p>
                    <p class="text-sm text-on-surface-variant">{{ Math.floor((lesson.duration || 0) / 60) }} phút · {{ lesson.is_preview ? 'Xem thử' : 'Bài học chính' }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </UiCard>

        <div class="space-y-6">
          <UiCard>
            <h2 class="text-lg font-semibold text-on-surface">Kiểm duyệt</h2>
            <div class="mt-4 space-y-4">
              <template v-if="course.status === 'pending_review'">
                <UiButton block size="lg" :disabled="actionLoading" @click="approveCourse">
                   <span class="material-symbols-outlined text-[20px]">check_circle</span>
                   {{ actionLoading ? 'Đang thực thi...' : 'Duyệt & Xuất bản Ngay' }}
                </UiButton>
                <div class="space-y-2 mt-4">
                   <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">Lý do từ chối (Gửi tới giảng viên)</label>
                   <UiTextarea v-model="rejectReason" :rows="4" placeholder="Nhập lý do chi tiết..." class="rounded-2xl" />
                </div>
                <UiButton block variant="danger" size="lg" :disabled="actionLoading || !rejectReason.trim()" @click="rejectCourse">
                   <span class="material-symbols-outlined text-[20px]">cancel</span>
                   Từ chối khóa học
                </UiButton>
              </template>
              <template v-else-if="course.status === 'rejected'">
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ course.reject_reason || 'Khóa học đã bị từ chối.' }}</div>
                <UiButton block :disabled="actionLoading" @click="approveCourse">Duyệt lại khóa học</UiButton>
              </template>
              <template v-else>
                <div class="rounded-2xl border border-secondary/20 bg-secondary-50 p-4 text-sm text-secondary">Khóa học đã được xuất bản.</div>
              </template>
            </div>
          </UiCard>

          <UiCard>
            <h2 class="text-lg font-semibold text-on-surface">Xem thử nhanh</h2>
            <div class="mt-4 space-y-3">
              <NuxtLink v-if="course.preview_urls?.course" :to="course.preview_urls.course" class="flex items-center justify-between rounded-2xl border border-surface-dim/40 px-4 py-3 text-sm font-semibold text-on-surface hover:border-primary/40">
                <span>Xem trang công khai của khóa học</span>
                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
              </NuxtLink>
              <NuxtLink v-if="course.preview_urls?.learn" :to="course.preview_urls.learn" class="flex items-center justify-between rounded-2xl border border-surface-dim/40 px-4 py-3 text-sm font-semibold text-on-surface hover:border-primary/40">
                <span>Vào học thử với quyền admin</span>
                <span class="material-symbols-outlined text-[18px]">play_circle</span>
              </NuxtLink>
              <NuxtLink :to="`/admin/courses/${courseId}/curriculum`" class="flex items-center justify-between rounded-2xl border border-surface-dim/40 px-4 py-3 text-sm font-semibold text-on-surface hover:border-primary/40">
                <span>Xem toàn bộ curriculum</span>
                <span class="material-symbols-outlined text-[18px]">menu_book</span>
              </NuxtLink>
            </div>
          </UiCard>

          <UiCard>
            <h2 class="text-lg font-semibold text-on-surface">Thông tin giảng viên</h2>
            <div class="mt-4 flex items-center gap-4">
              <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-primary/10 font-bold text-primary">
                <img v-if="course.instructor?.avatar" :src="course.instructor.avatar" class="h-full w-full object-cover rounded-2xl">
                <span v-else>{{ course.instructor?.name?.charAt(0) }}</span>
              </div>
              <div>
                <p class="font-semibold text-on-surface">{{ course.instructor?.name }}</p>
              </div>
            </div>
          </UiCard>
        </div>
      </div>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })
const route = useRoute()
const auth = useAuthStore()
const courseId = route.params.id as string
const course = ref<any>(null)
const lessons = ref<any[]>([])
const loading = ref(true)
const actionLoading = ref(false)
const rejectReason = ref('')
const statusLabel = (status: string) => ({ published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }[status] || status)
const formatPrice = (price: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
async function approveCourse() {
  actionLoading.value = true
  try { await useApi(`/admin/courses/${courseId}/approve`, { method: 'PUT', token: auth.token }); if (course.value) course.value.status = 'published' } catch (e: any) { alert(e?.data?.message || 'Thất bại') } finally { actionLoading.value = false }
}
async function rejectCourse() {
  if (!rejectReason.value.trim()) return
  actionLoading.value = true
  try { await useApi(`/admin/courses/${courseId}/reject`, { method: 'PUT', body: { reject_reason: rejectReason.value }, token: auth.token }); if (course.value) { course.value.status = 'rejected'; course.value.reject_reason = rejectReason.value } } catch (e: any) { alert(e?.data?.message || 'Thất bại') } finally { actionLoading.value = false }
}
onMounted(async () => {
  try { const courseData = await useApi<any>(`/admin/courses/${courseId}`, { token: auth.token }); course.value = courseData; lessons.value = Array.isArray(courseData?.lessons) ? courseData.lessons : [] } catch { course.value = null } finally { loading.value = false }
})
</script>
