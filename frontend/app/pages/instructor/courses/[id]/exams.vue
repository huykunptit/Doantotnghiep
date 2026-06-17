<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const courseId = route.params.id as string
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const exams = ref<any[]>([])
const editingExam = ref<any | null>(null)
const showForm = ref(false)
const deleteTarget = ref<any | null>(null)

const form = reactive({
  title: '',
  description: '',
  status: 'draft',
  duration: 60,
  pass_score: 80,
})

async function loadExams() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>(`/courses/${courseId}/exams`, { headers: authHeaders() })
    exams.value = Array.isArray(res) ? res : res.data || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải danh sách kỳ thi.'
  }
  finally { loading.value = false }
}

function openCreate() {
  editingExam.value = null
  form.title = ''
  form.description = ''
  form.status = 'draft'
  form.duration = 60
  form.pass_score = 80
  showForm.value = true
}

function openEdit(exam: any) {
  editingExam.value = exam
  form.title = exam.title
  form.description = exam.description || ''
  form.status = exam.status || 'draft'
  form.duration = exam.duration || 60
  form.pass_score = exam.pass_score || 80
  showForm.value = true
}

function cancelForm() {
  editingExam.value = null
  showForm.value = false
  error.value = ''
}

async function saveExam() {
  if (!form.title.trim()) { error.value = 'Tên kỳ thi không được để trống.'; return }
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    if (editingExam.value) {
      await useApi(`/courses/${courseId}/exams/${editingExam.value.id}`, {
        method: 'PUT',
        headers: authHeaders(),
        body: { ...form },
      })
      success.value = 'Cập nhật kỳ thi thành công.'
    }
    else {
      await useApi(`/courses/${courseId}/exams`, {
        method: 'POST',
        headers: authHeaders(),
        body: { ...form },
      })
      success.value = 'Tạo kỳ thi thành công.'
    }
    cancelForm()
    await loadExams()
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Có lỗi xảy ra.'
  }
  finally { saving.value = false }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await useApi(`/courses/${courseId}/exams/${deleteTarget.value.id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    deleteTarget.value = null
    await loadExams()
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể xoá kỳ thi.'
    deleteTarget.value = null
  }
}

const statusLabel: Record<string, string> = {
  draft: 'Bản nháp',
  scheduled: 'Đã lên lịch',
  published: 'Đang mở',
  closed: 'Đã đóng',
}
const statusClass: Record<string, string> = {
  draft: 'role-admin',
  scheduled: 'role-student',
  published: 'role-instructor',
  closed: '',
}

onMounted(loadExams)
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên / Kỳ thi</p>
        <h2>Kỳ thi độc lập</h2>
        <p>Quản lý các kỳ thi riêng biệt ngoài bài học cho khoá học này.</p>
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <NuxtLink :to="`/instructor/courses/${courseId}/question-bank`" class="crud-secondary-btn">Ngân hàng câu hỏi</NuxtLink>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="crud-secondary-btn">Giáo trình</NuxtLink>
        <button type="button" class="crud-primary-btn" @click="openCreate">+ Tạo kỳ thi mới</button>
      </div>
    </header>

    <div v-if="success" class="crud-alert is-success" style="margin-bottom: 16px;">{{ success }}</div>
    <div v-if="error && !showForm" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>

    <!-- Form tạo/sửa -->
    <section v-if="showForm" class="dashboard-card crud-panel" style="margin-bottom: 24px;">
      <div class="card-head" style="margin-bottom: 20px;">
        <h3>{{ editingExam ? 'Cập nhật kỳ thi' : 'Tạo kỳ thi mới' }}</h3>
      </div>
      <div v-if="error" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>
      <div class="crud-form-grid">
        <label class="crud-field crud-field-full">
          <span>Tên kỳ thi <span style="color:#ef4444">*</span></span>
          <input v-model="form.title" type="text" placeholder="Ví dụ: Kiểm tra giữa kỳ môn Lập trình Web">
        </label>
        <label class="crud-field crud-field-full">
          <span>Mô tả / Hướng dẫn</span>
          <textarea v-model="form.description" rows="3" placeholder="Hướng dẫn cho thí sinh..." />
        </label>
        <label class="crud-field">
          <span>Trạng thái</span>
          <select v-model="form.status" class="crud-select">
            <option value="draft">Bản nháp</option>
            <option value="scheduled">Đã lên lịch</option>
            <option value="published">Đang mở</option>
            <option value="closed">Đã đóng</option>
          </select>
        </label>
        <label class="crud-field">
          <span>Thời gian (phút)</span>
          <input v-model.number="form.duration" type="number" min="1" max="600">
        </label>
        <label class="crud-field">
          <span>Điểm đạt (%)</span>
          <input v-model.number="form.pass_score" type="number" min="1" max="100">
        </label>
      </div>
      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="button" class="crud-primary-btn" :disabled="saving" @click="saveExam">
          {{ saving ? 'Đang lưu...' : (editingExam ? 'Cập nhật' : 'Tạo kỳ thi') }}
        </button>
        <button type="button" class="crud-secondary-btn" @click="cancelForm">Huỷ</button>
      </div>
    </section>

    <!-- Danh sách kỳ thi -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Khoá học #{{ courseId }}</p>
          <h3>Danh sách kỳ thi ({{ exams.length }})</h3>
        </div>
        <button type="button" class="crud-primary-btn" @click="openCreate">+ Thêm</button>
      </div>

      <div v-if="loading" class="crud-empty">Đang tải...</div>

      <div v-else-if="exams.length === 0" class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 36px; display: block; margin: 0 auto 8px; opacity: 0.2;">quiz</span>
        Chưa có kỳ thi nào. Nhấn "Tạo kỳ thi mới" để bắt đầu.
      </div>

      <div v-else class="exams-grid">
        <div v-for="exam in exams" :key="exam.id" class="exam-card">
          <div class="exam-card-top">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <span class="crud-badge" :class="statusClass[exam.status] || ''">
                {{ statusLabel[exam.status] || exam.status }}
              </span>
              <span style="font-size: 0.78rem; color: var(--muted);">
                {{ exam.duration || 60 }} phút · Pass {{ exam.pass_score || 80 }}%
              </span>
            </div>
            <h4 class="exam-title">{{ exam.title }}</h4>
            <p class="exam-desc">{{ exam.description || 'Chưa có mô tả.' }}</p>
          </div>
          <div class="exam-card-actions">
            <NuxtLink :to="`/instructor/courses/${courseId}/exams/${exam.id}`" class="action-btn is-view">
              Quản lý câu hỏi
            </NuxtLink>
            <NuxtLink :to="`/exam/${exam.id}`" target="_blank" class="action-btn is-edit">
              Thi thử
            </NuxtLink>
            <button type="button" class="action-btn is-edit" @click="openEdit(exam)">Sửa</button>
            <button type="button" class="action-btn is-danger" @click="deleteTarget = exam">Xoá</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Confirm delete modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="dashboard-card" style="max-width: 420px; padding: 28px;">
        <h3 style="margin: 0 0 12px;">Xác nhận xoá</h3>
        <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 20px;">
          Bạn có chắc muốn xoá kỳ thi <strong>{{ deleteTarget.title }}</strong>? Hành động này không thể hoàn tác.
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button type="button" class="crud-secondary-btn" @click="deleteTarget = null">Huỷ</button>
          <button type="button" class="crud-danger-btn" @click="confirmDelete">Xoá</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.exams-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
  margin-top: 4px;
}
.exam-card {
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: rgba(255,255,255,0.6);
  transition: box-shadow 0.2s;
}
.exam-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.exam-card-top { flex: 1; }
.exam-title { font-size: 1rem; font-weight: 700; margin: 10px 0 6px; }
.exam-desc { font-size: 0.8rem; color: var(--muted); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.exam-card-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 100; display: flex; align-items: center; justify-content: center; }
</style>
