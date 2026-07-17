<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

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
const deleting = ref(false)

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
  deleting.value = true
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
  finally {
    deleting.value = false
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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học &bull; Kỳ thi</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Kỳ thi độc lập</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý các kỳ thi riêng biệt ngoài bài học cho khoá học này.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <NuxtLink :to="`/instructor/courses/${courseId}/question-bank`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <span class="material-symbols-outlined text-sm">database</span>
          Ngân hàng câu hỏi
        </NuxtLink>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <span class="material-symbols-outlined text-sm">auto_stories</span>
          Giáo trình
        </NuxtLink>
        <button type="button" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" @click="openCreate">
          <span class="material-symbols-outlined text-sm">add_circle</span>
          Tạo kỳ thi mới
        </button>
      </div>
    </div>

    <div v-if="success" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-semibold">{{ success }}</div>
    <div v-if="error && !showForm" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>

    <!-- Form tạo/sửa -->
    <section v-if="showForm" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <div class="border-b border-[var(--line)] pb-3">
        <h3 class="text-sm font-bold text-[var(--text)]">{{ editingExam ? 'Cập nhật kỳ thi' : 'Tạo kỳ thi mới' }}</h3>
      </div>
      <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex flex-col gap-1.5 md:col-span-3">
          <span class="text-xs font-semibold text-[var(--text)]">Tên kỳ thi <span class="text-red-500">*</span></span>
          <input v-model="form.title" type="text" placeholder="Ví dụ: Kiểm tra giữa kỳ môn Lập trình Web" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full">
        </div>
        <div class="flex flex-col gap-1.5 md:col-span-3">
          <span class="text-xs font-semibold text-[var(--text)]">Mô tả / Hướng dẫn</span>
          <textarea v-model="form.description" rows="3" placeholder="Hướng dẫn cho thí sinh..." class="p-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Trạng thái</span>
          <select v-model="form.status" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full">
            <option value="draft">Bản nháp</option>
            <option value="scheduled">Đã lên lịch</option>
            <option value="published">Đang mở</option>
            <option value="closed">Đã đóng</option>
          </select>
        </div>
        <div class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Thời gian (phút)</span>
          <input v-model.number="form.duration" type="number" min="1" max="600" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full">
        </div>
        <div class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Điểm đạt (%)</span>
          <input v-model.number="form.pass_score" type="number" min="1" max="100" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full">
        </div>
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" @click="cancelForm">Huỷ</button>
        <button type="button" class="h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50" :disabled="saving" @click="saveExam">
          {{ saving ? 'Đang lưu...' : (editingExam ? 'Cập nhật' : 'Tạo kỳ thi') }}
        </button>
      </div>
    </section>

    <!-- Danh sách kỳ thi -->
    <section class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <div class="flex justify-between items-center border-b border-[var(--line)] pb-3">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Khoá học #{{ courseId }}</p>
          <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Danh sách kỳ thi ({{ exams.length }})</h3>
        </div>
        <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" @click="openCreate">+ Thêm</button>
      </div>

      <div v-if="loading" class="text-center py-8 text-xs text-[var(--muted)]">Đang tải...</div>

      <div v-else-if="exams.length === 0" class="text-center py-12 flex flex-col items-center gap-2 text-xs text-[var(--muted)]">
        <span class="material-symbols-outlined text-4xl opacity-20">quiz</span>
        Chưa có kỳ thi nào. Nhấn "Tạo kỳ thi mới" để bắt đầu.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="exam in exams" :key="exam.id" class="border border-[var(--line)] bg-[var(--surface-strong)] rounded-2xl p-4 flex flex-col justify-between gap-4 hover:shadow-md transition-shadow">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="{
                'bg-emerald-50 text-emerald-600 border-emerald-100': exam.status === 'published',
                'bg-sky-50 text-sky-600 border-sky-100': exam.status === 'scheduled',
                'bg-[var(--surface)] text-[var(--muted)] border-[var(--line)]': exam.status === 'draft',
                'bg-red-50 text-red-500 border-red-100': exam.status === 'closed'
              }">
                {{ statusLabel[exam.status] || exam.status }}
              </span>
              <span class="text-[10px] text-[var(--muted)] font-semibold">
                {{ exam.duration || 60 }} phút · Pass {{ exam.pass_score || 80 }}%
              </span>
            </div>
            <h4 class="text-sm font-bold text-[var(--text)] mt-3">{{ exam.title }}</h4>
            <p class="text-xs text-[var(--muted)] leading-relaxed mt-1.5 line-clamp-2">{{ exam.description || 'Chưa có mô tả.' }}</p>
          </div>
          <div class="flex flex-wrap gap-1.5 pt-3 border-t border-[var(--line)]">
            <NuxtLink :to="`/instructor/courses/${courseId}/exams/${exam.id}`" class="h-7 px-2.5 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] flex items-center justify-center transition-colors">
              Quản lý câu hỏi
            </NuxtLink>
            <NuxtLink :to="`/exam/${exam.id}`" target="_blank" class="h-7 px-2.5 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] flex items-center justify-center transition-colors">
              Thi thử
            </NuxtLink>
            <button type="button" class="h-7 px-2.5 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] transition-colors" @click="openEdit(exam)">Sửa</button>
            <button type="button" class="h-7 px-2.5 rounded-lg bg-red-50 hover:bg-red-100 text-[10px] font-bold text-red-600 transition-colors" @click="deleteTarget = exam">Xoá</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Confirm delete modal -->
    <CrudConfirmModal
      :open="deleteTarget !== null"
      title="Xoá kỳ thi"
      :description="`Bạn có chắc muốn xoá kỳ thi ${deleteTarget?.title}? Hành động này không thể hoàn tác.`"
      confirm-text="Xoá kỳ thi"
      tone="danger"
      :loading="deleting"
      @close="deleteTarget = null"
      @confirm="confirmDelete"
    />
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
