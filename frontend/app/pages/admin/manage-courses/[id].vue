<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const courseId = route.params.id as string

const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface LessonItem { id: number; title: string; type: string; duration: number; is_preview: boolean }
interface SectionItem { id: number; title: string; position: number; lessons?: LessonItem[] }
interface CourseDetail { id: number; title: string }

const course = ref<CourseDetail | null>(null)
const sections = ref<SectionItem[]>([])
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const sectionModalOpen = ref(false)
const sectionForm = reactive({ title: '', id: null as number | null })

const lessonModalOpen = ref(false)
const lessonForm = reactive({ title: '', section_id: '', type: 'video', video_url: '', duration: 0, is_preview: false, id: null as number | null })

async function fetchCourseDetails() {
  try {
    course.value = await useApi<CourseDetail>(`/courses/${courseId}`, { headers: authHeaders() })
  } catch (error) {
    errorMessage.value = 'Không thể tải thông tin khóa học.'
  }
}

async function fetchCurriculum() {
  loading.value = true
  try {
    const res = await useApi<{ data: SectionItem[] }>(`/courses/${courseId}/sections`, { headers: authHeaders() })
    sections.value = res.data
  } catch (error) {
    errorMessage.value = 'Lỗi tải nội dung bài giảng.'
  } finally {
    loading.value = false
  }
}

function openSectionModal(section?: SectionItem) {
  if (section) {
    sectionForm.id = section.id
    sectionForm.title = section.title
  } else {
    sectionForm.id = null
    sectionForm.title = ''
  }
  sectionModalOpen.value = true
}

async function saveSection() {
  if (!sectionForm.title.trim()) return
  try {
    if (sectionForm.id) {
      await useApi(`/sections/${sectionForm.id}`, { method: 'PUT', headers: authHeaders(), body: { title: sectionForm.title } })
      successMessage.value = 'Đã cập nhật chương.'
    } else {
      await useApi(`/courses/${courseId}/sections`, { method: 'POST', headers: authHeaders(), body: { title: sectionForm.title } })
      successMessage.value = 'Đã thêm chương mới.'
    }
    sectionModalOpen.value = false
    fetchCurriculum()
  } catch (error) {
    errorMessage.value = 'Lỗi lưu chương / phần học.'
  }
}

async function deleteSection(id: number) {
  if (!confirm('Xác nhận xóa chương này? Tất cả bài giảng bên trong phải được xóa trước.')) return
  try {
    await useApi(`/sections/${id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa chương.'
    fetchCurriculum()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Lỗi khi xóa chương.'
  }
}

function openLessonModal(sectionId: number, lesson?: LessonItem) {
  lessonForm.section_id = String(sectionId)
  if (lesson) {
    lessonForm.id = lesson.id
    lessonForm.title = lesson.title
    lessonForm.type = lesson.type || 'video'
    lessonForm.duration = lesson.duration || 0
    lessonForm.is_preview = lesson.is_preview || false
  } else {
    lessonForm.id = null
    lessonForm.title = ''
    lessonForm.type = 'video'
    lessonForm.video_url = ''
    lessonForm.duration = 0
    lessonForm.is_preview = false
  }
  lessonModalOpen.value = true
}

async function saveLesson() {
  if (!lessonForm.title.trim() || !lessonForm.section_id) return
  try {
    const payload = {
      title: lessonForm.title,
      section_id: Number(lessonForm.section_id),
      type: lessonForm.type,
      duration: lessonForm.duration,
      is_preview: lessonForm.is_preview,
      video_url: lessonForm.video_url || undefined,
    }
    
    if (lessonForm.id) {
      await useApi(`/courses/${courseId}/lessons/${lessonForm.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã cập nhật bài giảng.'
    } else {
      await useApi(`/courses/${courseId}/lessons`, { method: 'POST', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã thêm bài giảng mới.'
    }
    lessonModalOpen.value = false
    fetchCurriculum()
  } catch (error) {
    errorMessage.value = 'Lỗi lưu bài giảng.'
  }
}

async function deleteLesson(lessonId: number) {
  if (!confirm('Xác nhận xóa bài giảng này?')) return
  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa bài giảng.'
    fetchCurriculum()
  } catch (error) {
    errorMessage.value = 'Lỗi khi xóa bài.'
  }
}

onMounted(() => {
  fetchCourseDetails()
  fetchCurriculum()
})
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Khóa học', course?.title || 'Đang tải...']" description="Sắp xếp và quản lý nội dung giảng dạy. Bạn có thể xây dựng cấu trúc theo các phần học và bài giảng." title="Xây dựng Nội dung">
    
    <div class="crud-toolbar" style="margin-bottom: 24px;">
      <button class="crud-primary-btn" type="button" @click="openSectionModal()">+ Thêm Chương mới</button>
      <button class="crud-secondary-btn" type="button" @click="navigateTo('/admin/manage-courses')">← Quay lại danh sách</button>
    </div>

    <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
    <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

    <div v-if="loading" style="padding: 40px; text-align: center; color: var(--muted);">Đang tải cấu trúc nội dung...</div>
    
    <div v-else-if="sections.length === 0" class="crud-empty dashboard-card">
      Chưa có nội dung nào được tạo. Hãy bắt đầu bằng cách bấm "Thêm Chương mới".
    </div>

    <div v-else class="curriculum-builder">
      <div v-for="(section, index) in sections" :key="section.id" class="section-card dashboard-card">
        <div class="section-header">
          <div class="section-title">
            <strong>Chương {{ index + 1 }}:</strong> <span>{{ section.title }}</span>
          </div>
          <div class="section-actions">
            <button class="action-btn is-edit" type="button" @click="openSectionModal(section)">Sửa chương</button>
            <button class="action-btn is-delete" type="button" @click="deleteSection(section.id)">Xóa chương</button>
            <button class="crud-secondary-btn" type="button" @click="openLessonModal(section.id)" style="margin-left: 8px;">+ Thêm bài</button>
          </div>
        </div>

        <div class="lessons-list">
          <div v-if="!section.lessons || section.lessons.length === 0" class="no-lessons">
            Chưa có bài giảng nào trong phần này.
          </div>
          <div v-for="(lesson, lIndex) in section.lessons" :key="lesson.id" class="lesson-item">
            <div class="lesson-info">
              <span class="lesson-icon">
                <svg v-if="lesson.type === 'video'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              </span>
              <span class="lesson-name">{{ lIndex + 1 }}. {{ lesson.title }}</span>
              <span v-if="lesson.is_preview" class="crud-badge role-instructor">Học thử</span>
              <span class="lesson-duration" v-if="lesson.duration">{{ lesson.duration }} phút</span>
            </div>
            <div class="lesson-actions">
              <button class="action-btn is-edit" type="button" @click="openLessonModal(section.id, lesson)">Sửa</button>
              <button class="action-btn is-delete" type="button" @click="deleteLesson(lesson.id)">Xóa</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Section Modal -->
    <Teleport to="body">
      <div v-if="sectionModalOpen" class="crud-modal-backdrop" @click.self="sectionModalOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Chương / Phần</p>
              <h3>{{ sectionForm.id ? 'Sửa tên chương' : 'Thêm chương mới' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="sectionModalOpen = false">✕</button>
          </div>
          <div class="crud-form-grid">
            <label class="crud-field crud-field-full">
              <span>Tên chương</span>
              <input v-model="sectionForm.title" type="text" placeholder="Ví dụ: Giới thiệu khóa học">
            </label>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="sectionModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" @click="saveSection">Lưu chương</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Lesson Modal -->
    <Teleport to="body">
      <div v-if="lessonModalOpen" class="crud-modal-backdrop" @click.self="lessonModalOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Bài giảng mới</p>
              <h3>{{ lessonForm.id ? 'Cập nhật bài giảng' : 'Tạo bài giảng' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="lessonModalOpen = false">✕</button>
          </div>
          <div class="crud-form-grid">
            <label class="crud-field crud-field-full">
              <span>Tên bài giảng</span>
              <input v-model="lessonForm.title" type="text" placeholder="Ví dụ: Bài 1: Cài đặt môi trường">
            </label>
            <label class="crud-field">
              <span>Loại nội dung</span>
              <select v-model="lessonForm.type" class="crud-select">
                <option value="video">Video</option>
                <option value="document">Tài liệu đọc</option>
              </select>
            </label>
            <label class="crud-field" v-if="lessonForm.type === 'video' && !lessonForm.id">
              <span>Đường dẫn Video (URL) - Tùy chọn</span>
              <input v-model="lessonForm.video_url" type="url" placeholder="https://...">
            </label>
            <label class="crud-field">
              <span>Thời lượng (Phút)</span>
              <input v-model="lessonForm.duration" type="number" min="0">
            </label>
            <label class="crud-field checkbox-field" style="display: flex; align-items: center; gap: 8px;">
              <input v-model="lessonForm.is_preview" type="checkbox">
              <span>Cho phép Học thử (Miễn phí)</span>
            </label>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="lessonModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" @click="saveLesson">Lưu bài giảng</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.curriculum-builder {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-card {
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: var(--bg-alt);
  border-bottom: 1px solid var(--border);
}

.section-title {
  font-size: 1rem;
}
.section-title strong {
  margin-right: 6px;
  color: var(--text);
}
.section-title span {
  font-weight: 500;
  color: var(--text);
}

.lessons-list {
  padding: 12px 20px;
}

.no-lessons {
  padding: 20px;
  text-align: center;
  color: var(--muted);
  font-size: 0.95rem;
  background: rgba(17,17,17,0.02);
  border-radius: 8px;
  border: 1px dashed rgba(17,17,17,0.1);
}

.lesson-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  margin-bottom: 8px;
  background: #fff;
  border: 1px solid rgba(17,17,17,0.08);
  border-radius: 10px;
  transition: all 0.2s;
}

.lesson-item:last-child {
  margin-bottom: 0;
}

.lesson-item:hover {
  border-color: rgba(17,17,17,0.15);
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  transform: translateY(-1px);
}

.lesson-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.lesson-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: rgba(47, 122, 69, 0.08);
  color: var(--green-deep);
  border-radius: 8px;
}

.lesson-icon svg {
  width: 16px;
  height: 16px;
}

.lesson-name {
  font-weight: 500;
  color: var(--text);
  font-size: 0.95rem;
}

.lesson-duration {
  font-size: 0.85rem;
  color: var(--muted);
  background: rgba(17,17,17,0.04);
  padding: 4px 8px;
  border-radius: 6px;
  margin-left: auto;
}
</style>
