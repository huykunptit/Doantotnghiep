<template>
  <AdminWorkspaceShell
    title="Chi tiết khóa học"
    description="Kiểm tra thông tin khóa học, giảng viên và thực hiện duyệt hoặc từ chối."
    :breadcrumb="['Trang chủ', 'Khóa học', 'Chi tiết']"
  >
    <template #actions>
      <NuxtLink to="/admin/courses" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Quay lại
      </NuxtLink>
      <NuxtLink :to="`/admin/courses/${courseId}/curriculum`" class="crud-secondary-btn">
        <span class="material-symbols-outlined">menu_book</span>
        Curriculum
      </NuxtLink>
      <NuxtLink v-if="course?.preview_urls?.course" :to="course.preview_urls.course" class="crud-secondary-btn">
        <span class="material-symbols-outlined">visibility</span>
        Trang khóa học
      </NuxtLink>
      <NuxtLink v-if="course?.preview_urls?.learn" :to="course.preview_urls.learn" class="crud-primary-btn">
        <span class="material-symbols-outlined">play_circle</span>
        Xem thử bài học
      </NuxtLink>
    </template>

    <div v-if="loading" class="crud-empty" style="padding:3rem;">Đang tải...</div>

    <template v-else-if="course">
      <div class="course-detail-grid">
        <!-- Main content -->
        <div class="dashboard-card crud-panel">
          <div class="course-hero">
            <div class="course-thumbnail">
              <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
              <span v-else class="material-symbols-outlined thumb-placeholder">book</span>
            </div>
            <div class="course-info">
              <div class="course-badges">
                <span class="ds-badge" :class="statusBadgeClass(course.status)">{{ statusLabel(course.status) }}</span>
                <span v-if="course.category?.name" class="ds-badge ds-badge--info">{{ course.category.name }}</span>
              </div>
              <h1 class="course-title">{{ course.title }}</h1>
              <p class="course-desc">{{ course.description }}</p>
              <div class="course-meta-grid">
                <div class="meta-item">
                  <span class="meta-label">Giảng viên</span>
                  <strong>{{ course.instructor?.name }}</strong>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Bài học</span>
                  <strong>{{ lessons.length }}</strong>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Học viên</span>
                  <strong>{{ course.enrollments_count || 0 }}</strong>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Giá</span>
                  <strong>{{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}</strong>
                </div>
              </div>
            </div>
          </div>

          <div class="lesson-section">
            <h2 class="section-title">Danh sách bài học</h2>
            <div v-if="lessons.length === 0" class="crud-empty">
              <span class="material-symbols-outlined" style="font-size:48px;opacity:0.2;">menu_book</span>
              <div>
                <strong>Chưa có bài học</strong>
                <p>Khóa học này chưa có nội dung để duyệt.</p>
              </div>
            </div>
            <div v-else class="lesson-list">
              <div v-for="(lesson, index) in lessons" :key="lesson.id" class="lesson-item">
                <div class="lesson-index">{{ index + 1 }}</div>
                <div class="lesson-details">
                  <strong>{{ lesson.title }}</strong>
                  <p>{{ Math.floor((lesson.duration || 0) / 60) }} phút · {{ lesson.is_preview ? 'Xem thử' : 'Bài học chính' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="course-sidebar">
          <!-- Review card -->
          <div class="dashboard-card crud-panel">
            <h2 class="section-title">Kiểm duyệt</h2>
            <div class="review-actions">
              <template v-if="course.status === 'pending_review'">
                <button class="crud-primary-btn" :disabled="actionLoading" @click="approveCourse">
                  <span class="material-symbols-outlined">check_circle</span>
                  {{ actionLoading ? 'Đang thực thi...' : 'Duyệt & Xuất bản' }}
                </button>
                <div class="form-field">
                  <label>Lý do từ chối (Gửi tới giảng viên)</label>
                  <textarea v-model="rejectReason" rows="4" placeholder="Nhập lý do chi tiết..." class="form-textarea" />
                </div>
                <button class="crud-danger-btn" :disabled="actionLoading || !rejectReason.trim()" @click="rejectCourse">
                  <span class="material-symbols-outlined">cancel</span>
                  Từ chối khóa học
                </button>
              </template>
              <template v-else-if="course.status === 'rejected'">
                <div class="alert-rejected">{{ course.reject_reason || 'Khóa học đã bị từ chối.' }}</div>
                <button class="crud-primary-btn" :disabled="actionLoading" @click="approveCourse">
                  <span class="material-symbols-outlined">check_circle</span>
                  Duyệt lại khóa học
                </button>
              </template>
              <template v-else>
                <div class="alert-published">Khóa học đã được xuất bản.</div>
              </template>
            </div>
          </div>

          <!-- Quick links -->
          <div class="dashboard-card crud-panel">
            <h2 class="section-title">Xem thử nhanh</h2>
            <div class="quick-links">
              <NuxtLink v-if="course.preview_urls?.course" :to="course.preview_urls.course" class="quick-link">
                <span>Xem trang công khai của khóa học</span>
                <span class="material-symbols-outlined">open_in_new</span>
              </NuxtLink>
              <NuxtLink v-if="course.preview_urls?.learn" :to="course.preview_urls.learn" class="quick-link">
                <span>Vào học thử với quyền admin</span>
                <span class="material-symbols-outlined">play_circle</span>
              </NuxtLink>
              <NuxtLink :to="`/admin/courses/${courseId}/curriculum`" class="quick-link">
                <span>Xem toàn bộ curriculum</span>
                <span class="material-symbols-outlined">menu_book</span>
              </NuxtLink>
            </div>
          </div>

          <!-- Instructor info -->
          <div class="dashboard-card crud-panel">
            <h2 class="section-title">Thông tin giảng viên</h2>
            <div class="instructor-card">
              <div class="instructor-avatar">
                <img v-if="course.instructor?.avatar" :src="course.instructor.avatar" :alt="course.instructor.name">
                <span v-else>{{ course.instructor?.name?.charAt(0) }}</span>
              </div>
              <div>
                <strong>{{ course.instructor?.name }}</strong>
                <p>{{ course.instructor?.email || '—' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AdminWorkspaceShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })
const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const courseId = route.params.id as string
const course = ref<any>(null)
const lessons = ref<any[]>([])
const loading = ref(true)
const actionLoading = ref(false)
const rejectReason = ref('')
const statusLabel = (status: string) => ({ published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }[status] || status)
const statusBadgeClass = (status: string) => {
  const map: Record<string, string> = {
    published: 'ds-badge--active',
    pending_review: 'ds-badge--pending',
    draft: 'ds-badge--draft',
    rejected: 'ds-badge--closed'
  }
  return map[status] || 'ds-badge--draft'
}
const formatPrice = (price: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
async function approveCourse() {
  actionLoading.value = true
  try {
    await useApi(`/admin/courses/${courseId}/approve`, { method: 'PUT', token: auth.token })
    if (course.value) course.value.status = 'published'
    toast.success('Khóa học đã được duyệt', 'Khóa học đã chuyển sang trạng thái xuất bản.')
  } catch (e: any) {
    toast.error('Duyệt thất bại', e?.data?.message || 'Không thể duyệt khóa học này.')
  } finally { actionLoading.value = false }
}
async function rejectCourse() {
  if (!rejectReason.value.trim()) return
  actionLoading.value = true
  try {
    await useApi(`/admin/courses/${courseId}/reject`, { method: 'PUT', body: { reject_reason: rejectReason.value }, token: auth.token })
    if (course.value) { course.value.status = 'rejected'; course.value.reject_reason = rejectReason.value }
    toast.warning('Khóa học bị từ chối', 'Lý do từ chối đã được gửi đến giảng viên.')
  } catch (e: any) {
    toast.error('Từ chối thất bại', e?.data?.message || 'Không thể từ chối khóa học này.')
  } finally { actionLoading.value = false }
}
onMounted(async () => {
  try { const courseData = await useApi<any>(`/admin/courses/${courseId}`, { token: auth.token }); course.value = courseData; lessons.value = Array.isArray(courseData?.lessons) ? courseData.lessons : [] } catch { course.value = null } finally { loading.value = false }
})
</script>

<style scoped>
.course-detail-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
@media (min-width: 1280px) { .course-detail-grid { grid-template-columns: 1fr 400px; } }

.course-hero { display: flex; flex-direction: column; gap: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--line); }
@media (min-width: 768px) { .course-hero { flex-direction: row; } }

.course-thumbnail {
  width: 100%; height: 200px; border-radius: 16px; overflow: hidden;
  background: var(--bg); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; border: 1px solid var(--line);
}
@media (min-width: 768px) { .course-thumbnail { width: 280px; } }
.course-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.thumb-placeholder { font-size: 64px; color: var(--muted); opacity: 0.3; }

.course-info { flex: 1; display: flex; flex-direction: column; gap: 14px; }
.course-badges { display: flex; flex-wrap: wrap; gap: 8px; }
.course-title { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
.course-desc { margin: 0; font-size: 0.88rem; color: var(--muted); line-height: 1.6; }

.course-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
.meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.meta-item strong { font-size: 0.9rem; color: var(--text); }

.lesson-section { padding-top: 20px; }
.section-title { margin: 0 0 16px; font-size: 1.05rem; font-weight: 800; color: var(--text); }

.lesson-list { display: flex; flex-direction: column; gap: 10px; }
.lesson-item {
  display: flex; align-items: center; gap: 14px; padding: 14px 16px;
  border: 1px solid var(--line); border-radius: 14px; background: var(--bg);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.lesson-item:hover { border-color: rgba(var(--green-rgb),0.3); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.lesson-index {
  width: 32px; height: 32px; border-radius: 10px; background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 800; color: var(--muted); flex-shrink: 0;
}
.lesson-details { flex: 1; min-width: 0; }
.lesson-details strong { display: block; font-size: 0.88rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lesson-details p { margin: 3px 0 0; font-size: 0.76rem; color: var(--muted); }

.course-sidebar { display: flex; flex-direction: column; gap: 20px; }

.review-actions { display: flex; flex-direction: column; gap: 14px; margin-top: 14px; }
.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-field label { font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.form-textarea {
  width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 12px;
  background: var(--bg); color: var(--text); font-size: 0.85rem; font-family: inherit;
  resize: vertical; transition: border-color 0.2s, box-shadow 0.2s;
}
.form-textarea:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(var(--green-rgb),0.1); }

.alert-rejected {
  padding: 14px; border-radius: 12px; font-size: 0.85rem; line-height: 1.5;
  background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #b91c1c;
}
.alert-published {
  padding: 14px; border-radius: 12px; font-size: 0.85rem; line-height: 1.5;
  background: rgba(29,158,117,0.08); border: 1px solid rgba(29,158,117,0.2); color: var(--green-deep);
}

.quick-links { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
.quick-link {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px;
  background: var(--bg); text-decoration: none; color: var(--text);
  font-size: 0.85rem; font-weight: 600; transition: all 0.2s;
}
.quick-link:hover { border-color: var(--green); background: var(--green-soft); color: var(--green-deep); transform: translateX(3px); }
.quick-link .material-symbols-outlined { font-size: 18px; color: var(--muted); }

.instructor-card { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
.instructor-avatar {
  width: 48px; height: 48px; border-radius: 12px; overflow: hidden;
  background: rgba(var(--green-rgb),0.1); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; font-weight: 800; flex-shrink: 0;
}
.instructor-avatar img { width: 100%; height: 100%; object-fit: cover; }
.instructor-card strong { display: block; font-size: 0.9rem; color: var(--text); }
.instructor-card p { margin: 3px 0 0; font-size: 0.78rem; color: var(--muted); }
</style>
