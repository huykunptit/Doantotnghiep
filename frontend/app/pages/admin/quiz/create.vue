<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'

definePageMeta({ layout: 'admin' })

// ── Types ──────────────────────────────────────────────────────────────────
interface CourseItem { id: number; title: string; category?: { name: string } | null }
interface BankItem {
  id: number; name: string; questions_count: number
  difficulty_distribution?: Record<string, number>
  course?: { id: number; title: string } | null
}
interface UserItem { id: number; name: string; email: string }

const DIFFICULTY_LABELS: Record<number, string> = {
  1: 'Nhận biết', 2: 'Thông hiểu', 3: 'Vận dụng', 4: 'Vận dụng cao', 5: 'Sáng tạo'
}
const DIFFICULTY_LEVELS = [1, 2, 3, 4, 5]

// ── Auth ───────────────────────────────────────────────────────────────────
const user = useAuthUserCookie()
const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

// ── Initial type from query param ──────────────────────────────────────────
const route = useRoute()
const initType = (route.query.type === 'course_final' ? 'course_final' : 'standalone') as 'course_final' | 'standalone'

// ── Step state ─────────────────────────────────────────────────────────────
const step = ref(1)
const saving = ref(false)
const errorMsg = ref('')

// ── Form data ──────────────────────────────────────────────────────────────
const form = reactive({
  type: initType,
  title: '',
  description: '',
  courseId: null as number | null,
  passScore: 70,
  maxAttempts: 1,
  duration: 60,
  startsAt: '',
  endsAt: '',
  shuffleQuestions: false,
  shuffleAnswers: false,
  questionMode: 'random_all' as 'random_all' | 'random_by_difficulty' | 'manual',
  selectedBankIds: [] as number[],
  totalQuestions: 20,
  difficultyDist: { 1: 20, 2: 30, 3: 30, 4: 15, 5: 5 } as Record<number, number>,
  bankCounts: {} as Record<number, number>,
  manualQuestionIds: [] as number[],
  enrollUserIds: [] as number[],
})

const totalSteps = computed(() => form.type === 'standalone' ? 5 : 4)

const stepLabels = computed(() => {
  const base = [
    'Loại & Tên',
    form.type === 'course_final' ? 'Khóa học' : 'Cài đặt',
    'Thời gian',
    'Câu hỏi',
  ]
  if (form.type === 'standalone') base.push('Ghi danh')
  return base
})

// ── Remote data ────────────────────────────────────────────────────────────
const courses = ref<CourseItem[]>([])
const allBanks = ref<BankItem[]>([])
const courseBanks = ref<BankItem[]>([])
const bankQuestions = ref<Record<number, any[]>>({})
const loadingBanks = ref(false)
const loadingBankQ = ref(false)
const allUsers = ref<UserItem[]>([])
const loadingUsers = ref(false)
const userSearch = ref('')
const userFilter = ref<'all' | 'selected' | 'available'>('all')
const importError = ref('')
const importing = ref(false)
const importMessage = ref('')

const activeBanks = computed(() =>
  form.type === 'standalone' ? allBanks.value : courseBanks.value
)

const filteredUsers = computed(() => {
  const q = userSearch.value.toLowerCase().trim()
  let items = allUsers.value

  if (q) {
    items = items.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
  }

  if (userFilter.value === 'selected') {
    items = items.filter(u => form.enrollUserIds.includes(u.id))
  } else if (userFilter.value === 'available') {
    items = items.filter(u => !form.enrollUserIds.includes(u.id))
  }

  return items
})

// ── Computed for difficulty mode ───────────────────────────────────────────
const availableByDifficulty = computed(() => {
  const r: Record<number, number> = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }
  for (const b of activeBanks.value.filter(b => form.selectedBankIds.includes(b.id))) {
    for (const l of DIFFICULTY_LEVELS) r[l] += (b.difficulty_distribution?.[String(l)] ?? 0)
  }
  return r
})

const questionsByDifficulty = computed(() => {
  const r: Record<number, number> = {}
  for (const l of DIFFICULTY_LEVELS) {
    r[l] = Math.round(form.totalQuestions * (form.difficultyDist[l] ?? 0) / 100)
  }
  return r
})

const totalPercent = computed(() =>
  DIFFICULTY_LEVELS.reduce((s, l) => s + (form.difficultyDist[l] ?? 0), 0)
)

const difficultyWarning = computed(() => {
  if (form.questionMode !== 'random_by_difficulty') return {} as Record<number, boolean>
  const w: Record<number, boolean> = {}
  for (const l of DIFFICULTY_LEVELS) {
    w[l] = questionsByDifficulty.value[l] > (availableByDifficulty.value[l] ?? 0)
  }
  return w
})

// ── Fetch helpers ──────────────────────────────────────────────────────────
async function fetchCourses() {
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = res.data || []
  } catch { /* ignore */ }
}

async function fetchAllBanks() {
  if (allBanks.value.length) return
  loadingBanks.value = true
  try {
    const res = await useApi<{ banks: BankItem[] }>('/admin/question-banks', { headers: authHeaders() })
    allBanks.value = res.banks || []
  } catch { /* ignore */ }
  finally { loadingBanks.value = false }
}

async function fetchCourseBanks(courseId: number) {
  loadingBanks.value = true
  try {
    const res = await useApi<{ banks: any[] }>(`/courses/${courseId}/question-banks`, { headers: authHeaders() })
    courseBanks.value = (res.banks || []).map((b: any) => ({
      id: b.id, name: b.name, questions_count: b.questions_count ?? 0,
      difficulty_distribution: b.difficulty_distribution ?? null,
    }))
  } catch { /* ignore */ }
  finally { loadingBanks.value = false }
}

async function fetchBankQuestions(bankId: number) {
  if (bankQuestions.value[bankId]) return
  loadingBankQ.value = true
  try {
    const courseId = form.courseId
    if (courseId) {
      const res = await useApi<any>(`/courses/${courseId}/question-banks/${bankId}`, { headers: authHeaders() })
      bankQuestions.value[bankId] = [
        ...(res.questions || []),
        ...(res.groups?.flatMap((g: any) => g.questions || []) || [])
      ]
    }
  } catch { bankQuestions.value[bankId] = [] }
  finally { loadingBankQ.value = false }
}

async function fetchUsers() {
  if (allUsers.value.length) return
  loadingUsers.value = true
  try {
    const res = await useApi<{ data: UserItem[] }>('/admin/users?per_page=200', { headers: authHeaders() })
    allUsers.value = res.data || []
  } catch { /* ignore */ }
  finally { loadingUsers.value = false }
}

// ── Navigation ─────────────────────────────────────────────────────────────
function validate(): boolean {
  errorMsg.value = ''
  if (step.value === 1 && !form.title.trim()) {
    errorMsg.value = 'Vui lòng nhập tên đề thi.'
    return false
  }
  if (step.value === 2 && form.type === 'course_final' && !form.courseId) {
    errorMsg.value = 'Vui lòng chọn khóa học.'
    return false
  }
  if (step.value === 3 && (!form.duration || form.duration < 1)) {
    errorMsg.value = 'Thời lượng thi phải ít nhất 1 phút.'
    return false
  }
  return true
}

async function goNext() {
  if (!validate()) return
  // Preload data for next step
  if (step.value === 3) {
    if (form.type === 'standalone') await fetchAllBanks()
    else if (form.courseId) await fetchCourseBanks(form.courseId)
  }
  if (step.value === 4 && form.type === 'standalone') {
    await fetchUsers()
  }
  step.value++
}

function goBack() {
  errorMsg.value = ''
  if (step.value > 1) step.value--
}

// ── Bank / question selection ──────────────────────────────────────────────
function toggleBank(bankId: number) {
  const idx = form.selectedBankIds.indexOf(bankId)
  if (idx > -1) {
    form.selectedBankIds.splice(idx, 1)
    delete form.bankCounts[bankId]
  } else {
    form.selectedBankIds.push(bankId)
    const n = form.selectedBankIds.length
    const perBank = Math.ceil(form.totalQuestions / n)
    form.bankCounts[bankId] = perBank
  }
}

async function toggleBankManual(bankId: number) {
  toggleBank(bankId)
  if (form.selectedBankIds.includes(bankId)) await fetchBankQuestions(bankId)
}

function toggleQuestion(qId: number) {
  const idx = form.manualQuestionIds.indexOf(qId)
  if (idx > -1) form.manualQuestionIds.splice(idx, 1)
  else form.manualQuestionIds.push(qId)
}

function toggleEnroll(userId: number) {
  const idx = form.enrollUserIds.indexOf(userId)
  if (idx > -1) form.enrollUserIds.splice(idx, 1)
  else form.enrollUserIds.push(userId)
}

function parseCsvRows(content: string) {
  const lines = content.split(/\r?\n/).map(line => line.trim()).filter(line => line)
  if (!lines.length) return []

  const headers = lines[0].split(/,|;|\t/).map(h => h.trim().toLowerCase())
  const hasHeader = headers.some(h => ['email', 'name', 'id'].includes(h))
  const rows = hasHeader ? lines.slice(1) : lines
  const result: { id?: number; email?: string }[] = []

  for (const row of rows) {
    const cells = row.split(/,|;|\t/).map(cell => cell.trim())
    if (hasHeader) {
      const rowData: any = {}
      cells.forEach((cell, idx) => { rowData[headers[idx]] = cell })
      if (rowData.email) result.push({ email: rowData.email.toLowerCase() })
      else if (rowData.id && !Number.isNaN(Number(rowData.id))) result.push({ id: Number(rowData.id) })
    } else {
      const maybeEmail = cells[0]?.toLowerCase()
      if (maybeEmail && maybeEmail.includes('@')) result.push({ email: maybeEmail })
      else if (maybeEmail && !Number.isNaN(Number(maybeEmail))) result.push({ id: Number(maybeEmail) })
    }
  }

  return result
}

async function handleImportFile(event: Event) {
  importError.value = ''
  importMessage.value = ''
  const target = event.target as HTMLInputElement
  const file = target?.files?.[0]
  if (!file) return
  if (!allUsers.value.length) await fetchUsers()

  const ext = file.name.split('.').pop()?.toLowerCase()
  if (ext !== 'csv') {
    importError.value = 'Hiện tại chỉ hỗ trợ import file CSV. Vui lòng lưu file Excel dưới dạng CSV.'
    return
  }

  importing.value = true
  try {
    const text = await file.text()
    const parsed = parseCsvRows(text)
    if (!parsed.length) {
      importError.value = 'File import không chứa dữ liệu hợp lệ.'
      return
    }

    const matchedIds = new Set<number>()
    const unmatched: string[] = []

    for (const item of parsed) {
      if (item.id) {
        const user = allUsers.value.find(u => u.id === item.id)
        if (user) matchedIds.add(user.id)
        else unmatched.push(String(item.id))
      } else if (item.email) {
        const user = allUsers.value.find(u => u.email.toLowerCase() === item.email.toLowerCase())
        if (user) matchedIds.add(user.id)
        else unmatched.push(item.email)
      }
    }

    form.enrollUserIds = Array.from(new Set([...form.enrollUserIds, ...Array.from(matchedIds)]))
    importMessage.value = `Đã thêm ${matchedIds.size} học viên từ file.`
    if (unmatched.length) {
      importMessage.value += ` Không tìm thấy ${unmatched.length} email/ID (${unmatched.slice(0, 5).join(', ')}${unmatched.length > 5 ? ', ...' : ''}).` }
  } catch (error) {
    importError.value = 'Không thể đọc file import. Vui lòng thử lại với file CSV hợp lệ.'
  } finally {
    importing.value = false
    if (target) target.value = ''
  }
}

// ── Submit ─────────────────────────────────────────────────────────────────
function buildQuizSettings() {
  if (form.questionMode === 'manual') return null
  const rules: any[] = []
  if (form.questionMode === 'random_all') {
    for (const id of form.selectedBankIds) {
      const count = form.bankCounts[id] ?? 0
      if (count > 0) rules.push({ bank_id: id, count })
    }
  } else {
    for (const id of form.selectedBankIds) {
      const nBanks = form.selectedBankIds.length
      for (const l of DIFFICULTY_LEVELS) {
        const count = Math.ceil((questionsByDifficulty.value[l] ?? 0) / nBanks)
        if (count > 0) rules.push({ bank_id: id, count, difficulty: l })
      }
    }
  }
  return rules.length ? { random_rules: rules } : null
}

async function submit() {
  if (!validate()) return
  saving.value = true; errorMsg.value = ''
  try {
    const examBody: any = {
      title: form.title, description: form.description || null,
      pass_score: form.passScore, max_attempts: form.maxAttempts,
      duration: form.duration,
      starts_at: form.startsAt || null, ends_at: form.endsAt || null,
      shuffle_questions: form.shuffleQuestions, shuffle_answers: form.shuffleAnswers,
    }

    let exam: any
    if (form.type === 'standalone') {
      examBody.type = 'standalone'
      exam = await useApi('/exams/standalone', { method: 'POST', headers: authHeaders(), body: examBody })
    } else {
      examBody.type = 'course_final'
      exam = await useApi(`/courses/${form.courseId}/exams`, { method: 'POST', headers: authHeaders(), body: examBody })
    }

    const examId = exam?.id ?? exam?.exam?.id

    // Setup quiz if questions configured
    const hasQ = form.questionMode === 'manual'
      ? form.manualQuestionIds.length > 0
      : form.selectedBankIds.length > 0

    if (examId && hasQ) {
      const quizBody = {
        title: form.title,
        pass_score: form.passScore,
        time_limit: form.duration,
        question_ids: form.questionMode === 'manual' ? form.manualQuestionIds : [],
        settings: buildQuizSettings(),
      }
      if (form.type === 'standalone') {
        await useApi(`/exams/${examId}/quiz`, { method: 'POST', headers: authHeaders(), body: quizBody })
      } else {
        await useApi(`/courses/${form.courseId}/exams/${examId}/quiz`, { method: 'POST', headers: authHeaders(), body: quizBody })
      }
    }

    // Enroll users (standalone)
    if (form.type === 'standalone' && examId && form.enrollUserIds.length > 0) {
      await useApi(`/exams/${examId}/enroll`, {
        method: 'POST', headers: authHeaders(),
        body: { user_ids: form.enrollUserIds }
      })
    }

    await navigateTo('/admin/quiz')
  } catch (e: any) {
    errorMsg.value = e?.data?.message || 'Không thể tạo đề thi. Vui lòng thử lại.'
  } finally {
    saving.value = false
  }
}

// Watch manual mode: load questions for already-selected banks
watch(() => form.questionMode, async (mode) => {
  if (mode === 'manual') {
    for (const id of form.selectedBankIds) await fetchBankQuestions(id)
  }
})

// Init
await fetchCourses()
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý thi', { label: 'Quiz / Đề thi', to: '/admin/quiz' }, 'Tạo đề thi mới']"
    description="Điền thông tin theo từng bước để tạo đề thi."
    title="Tạo đề thi mới"
  >
    <div class="create-layout">

      <!-- Left: step sidebar -->
      <aside class="step-sidebar">
        <div
          v-for="(label, i) in stepLabels"
          :key="i"
          :class="['sidebar-step', {
            active: step === i + 1,
            done: step > i + 1,
            pending: step < i + 1,
          }]"
        >
          <div class="sidebar-step-num">
            <span v-if="step > i + 1">✓</span>
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span class="sidebar-step-label">{{ label }}</span>
        </div>
      </aside>

      <!-- Right: step content -->
      <div class="step-content">
        <div v-if="errorMsg" class="crud-alert is-error" style="margin-bottom:1.25rem;">{{ errorMsg }}</div>

        <!-- ── Step 1: Type + Name ──────────────────────────────────────── -->
        <section v-if="step === 1" class="step-section">
          <h2 class="step-title">Chọn loại đề thi</h2>

          <div class="type-cards">
            <label :class="['type-card', { selected: form.type === 'standalone' }]">
              <input v-model="form.type" type="radio" value="standalone" style="display:none">
              <div class="type-card-icon">🏆</div>
              <div>
                <div class="type-card-name">Kỳ thi độc lập</div>
                <div class="type-card-desc">Không gắn với khóa học, ghi danh thí sinh riêng</div>
              </div>
            </label>
            <label :class="['type-card', { selected: form.type === 'course_final' }]">
              <input v-model="form.type" type="radio" value="course_final" style="display:none">
              <div class="type-card-icon">📘</div>
              <div>
                <div class="type-card-name">Đề thi khóa học</div>
                <div class="type-card-desc">Gắn vào khóa học, học viên đã đăng ký có thể tham gia</div>
              </div>
            </label>
          </div>

          <h2 class="step-title" style="margin-top:2rem;">Thông tin đề thi</h2>
          <div class="field-stack">
            <label class="crud-field">
              <span>Tên đề thi <span class="req">*</span></span>
              <input v-model="form.title" type="text" class="crud-input" placeholder="Ví dụ: Đề thi giữa kỳ Toán cao cấp">
            </label>
            <label class="crud-field">
              <span>Mô tả</span>
              <textarea v-model="form.description" rows="3" class="crud-input" placeholder="Phạm vi kiến thức, lưu ý khi thi..."></textarea>
            </label>
          </div>
        </section>

        <!-- ── Step 2: Course or settings ─────────────────────────────── -->
        <section v-if="step === 2" class="step-section">
          <template v-if="form.type === 'course_final'">
            <h2 class="step-title">Chọn khóa học <span class="req">*</span></h2>
            <SearchableCourseSelect v-model="form.courseId" :courses="courses" style="max-width:420px;" />
            <div class="field-divider" />
          </template>

          <h2 class="step-title">Cài đặt bài thi</h2>
          <div class="field-row">
            <label class="crud-field">
              <span>Điểm đạt tối thiểu (%)</span>
              <input v-model.number="form.passScore" type="number" min="0" max="100" class="crud-input">
            </label>
            <label class="crud-field">
              <span>Số lần thi tối đa</span>
              <input v-model.number="form.maxAttempts" type="number" min="1" max="99" class="crud-input">
            </label>
          </div>
        </section>

        <!-- ── Step 3: Time ────────────────────────────────────────────── -->
        <section v-if="step === 3" class="step-section">
          <h2 class="step-title">Cài đặt thời gian</h2>
          <div class="field-row">
            <label class="crud-field">
              <span>Thời lượng thi (phút) <span class="req">*</span></span>
              <input v-model.number="form.duration" type="number" min="1" class="crud-input">
            </label>
            <label class="crud-field">
              <span>Ngày giờ bắt đầu</span>
              <input v-model="form.startsAt" type="datetime-local" class="crud-input">
            </label>
            <label class="crud-field">
              <span>Ngày giờ kết thúc</span>
              <input v-model="form.endsAt" type="datetime-local" class="crud-input">
            </label>
          </div>

          <div class="field-divider" />
          <h2 class="step-title">Tuỳ chọn trình bày</h2>
          <div class="check-row">
            <label class="check-label">
              <input v-model="form.shuffleQuestions" type="checkbox">
              <span>Trộn thứ tự câu hỏi</span>
            </label>
            <label class="check-label">
              <input v-model="form.shuffleAnswers" type="checkbox">
              <span>Trộn thứ tự đáp án</span>
            </label>
          </div>
        </section>

        <!-- ── Step 4: Questions ───────────────────────────────────────── -->
        <section v-if="step === 4" class="step-section">
          <h2 class="step-title">Phương thức lấy câu hỏi</h2>
          <div class="mode-tabs">
            <button :class="['mode-tab', { active: form.questionMode === 'random_all' }]" type="button" @click="form.questionMode = 'random_all'">
              Ngẫu nhiên hoàn toàn
            </button>
            <button :class="['mode-tab', { active: form.questionMode === 'random_by_difficulty' }]" type="button" @click="form.questionMode = 'random_by_difficulty'">
              Ngẫu nhiên theo cấp độ
            </button>
            <button :class="['mode-tab', { active: form.questionMode === 'manual' }]" type="button" @click="form.questionMode = 'manual'">
              Chọn thủ công
            </button>
          </div>
          <p class="mode-hint">
            <template v-if="form.questionMode === 'random_all'">Chọn ngân hàng và số câu cần lấy — hệ thống rút ngẫu nhiên mỗi lần thi.</template>
            <template v-else-if="form.questionMode === 'random_by_difficulty'">Thiết lập tỷ lệ % theo từng cấp độ — hệ thống rút ngẫu nhiên đúng phân phối.</template>
            <template v-else>Duyệt và tick chọn từng câu hỏi cụ thể.</template>
          </p>

          <!-- Bank selector -->
          <div class="field-divider" />
          <h2 class="step-title">Chọn ngân hàng câu hỏi</h2>

          <div v-if="loadingBanks" class="crud-empty">Đang tải ngân hàng câu hỏi...</div>
          <div v-else-if="activeBanks.length === 0" class="crud-empty">Không tìm thấy ngân hàng câu hỏi nào.</div>
          <div v-else class="bank-grid">
            <div
              v-for="bank in activeBanks"
              :key="bank.id"
              :class="['bank-card', { selected: form.selectedBankIds.includes(bank.id) }]"
              @click="form.questionMode === 'manual' ? toggleBankManual(bank.id) : toggleBank(bank.id)"
            >
              <input :checked="form.selectedBankIds.includes(bank.id)" type="checkbox" tabindex="-1" style="pointer-events:none">
              <div class="bank-card-info">
                <div class="bank-card-name">{{ bank.name }}</div>
                <div v-if="bank.course" class="bank-card-course">{{ bank.course.title }}</div>
                <div class="bank-card-meta">
                  {{ bank.questions_count }} câu hỏi
                  <span v-if="bank.difficulty_distribution" style="margin-left:0.4rem;color:#666;">
                    · {{ Object.values(bank.difficulty_distribution).reduce((a, b) => a + b, 0) }} phân loại
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Config per mode -->
          <template v-if="form.selectedBankIds.length > 0">

            <!-- random_all: per-bank counts -->
            <template v-if="form.questionMode === 'random_all'">
              <div class="field-divider" />
              <h2 class="step-title">Số câu lấy từ mỗi ngân hàng</h2>
              <div class="bank-count-list">
                <div v-for="bankId in form.selectedBankIds" :key="bankId" class="bank-count-row">
                  <span class="bank-count-name">{{ activeBanks.find(b => b.id === bankId)?.name }}</span>
                  <div style="display:flex;align-items:center;gap:0.5rem;">
                    <input v-model.number="form.bankCounts[bankId]" type="number" min="1" class="crud-input" style="width:80px">
                    <span class="bank-count-avail">/ {{ activeBanks.find(b => b.id === bankId)?.questions_count ?? 0 }} câu</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- random_by_difficulty: distribution table -->
            <template v-if="form.questionMode === 'random_by_difficulty'">
              <div class="field-divider" />
              <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                <h2 class="step-title" style="margin:0;">Phân phối theo cấp độ</h2>
                <label class="crud-field" style="margin:0;flex-direction:row;align-items:center;gap:0.5rem;">
                  <span style="white-space:nowrap;font-size:0.85rem;">Tổng số câu:</span>
                  <input v-model.number="form.totalQuestions" type="number" min="1" class="crud-input" style="width:80px">
                </label>
              </div>
              <div class="diff-table">
                <div class="diff-header">
                  <span>Cấp độ</span>
                  <span>Tỷ lệ (%)</span>
                  <span>Số câu</span>
                  <span>Sẵn có</span>
                </div>
                <div v-for="l in DIFFICULTY_LEVELS" :key="l" :class="['diff-row', { warning: difficultyWarning[l] }]">
                  <span class="diff-name">{{ DIFFICULTY_LABELS[l] }}</span>
                  <label class="diff-pct-cell">
                    <input v-model.number="form.difficultyDist[l]" type="number" min="0" max="100" class="crud-input" style="width:70px">
                    <span>%</span>
                  </label>
                  <span class="diff-count-cell">{{ questionsByDifficulty[l] }} câu</span>
                  <span :class="['diff-avail-cell', { warn: difficultyWarning[l] }]">
                    {{ availableByDifficulty[l] ?? 0 }} câu
                    <span v-if="difficultyWarning[l]" title="Ngân hàng không đủ câu hỏi ở cấp độ này">⚠️</span>
                  </span>
                </div>
                <div :class="['diff-footer', { error: totalPercent !== 100 }]">
                  <span>Tổng cộng</span>
                  <span :style="{ color: totalPercent === 100 ? 'var(--success, #4caf50)' : '#e53e3e', fontWeight: 700 }">
                    {{ totalPercent }}%
                    <span v-if="totalPercent !== 100" style="font-size:0.78rem;font-weight:400;"> (cần bằng 100%)</span>
                  </span>
                  <span style="font-weight:700;">{{ form.totalQuestions }} câu</span>
                  <span></span>
                </div>
              </div>
            </template>

            <!-- manual: question checklist -->
            <template v-if="form.questionMode === 'manual'">
              <div class="field-divider" />
              <div class="manual-header">
                <h2 class="step-title" style="margin:0;">Danh sách câu hỏi</h2>
                <span class="manual-count">{{ form.manualQuestionIds.length }} câu đã chọn</span>
              </div>
              <div v-if="loadingBankQ" class="crud-empty">Đang tải câu hỏi...</div>
              <div v-else class="question-checklist">
                <template v-for="bankId in form.selectedBankIds" :key="bankId">
                  <div class="q-bank-header">
                    {{ activeBanks.find(b => b.id === bankId)?.name }}
                  </div>
                  <div v-if="!(bankQuestions[bankId]?.length)" class="crud-empty" style="padding:0.5rem 1rem;font-size:0.82rem;">
                    Không có câu hỏi.
                  </div>
                  <div
                    v-for="q in bankQuestions[bankId] ?? []"
                    :key="q.id"
                    :class="['q-row', { selected: form.manualQuestionIds.includes(q.id) }]"
                    @click="toggleQuestion(q.id)"
                  >
                    <input :checked="form.manualQuestionIds.includes(q.id)" type="checkbox" tabindex="-1" style="pointer-events:none;flex-shrink:0;">
                    <span class="q-text">{{ q.content }}</span>
                    <span class="q-diff-badge">{{ DIFFICULTY_LABELS[q.difficulty] ?? '—' }}</span>
                  </div>
                </template>
              </div>
            </template>

          </template>

          <div v-if="form.selectedBankIds.length === 0 && form.questionMode !== 'manual'" class="skip-hint">
            Có thể bỏ qua bước này — cấu hình câu hỏi sau khi đề thi đã được tạo.
          </div>
        </section>

        <!-- ── Step 5: Enrollment (standalone only) ────────────────────── -->
        <section v-if="step === 5" class="step-section">
          <h2 class="step-title">Ghi danh học viên</h2>
          <p class="step-desc">Chọn học viên tham gia kỳ thi. Có thể bỏ qua và ghi danh sau.</p>

          <div class="enroll-toolbar">
            <input v-model="userSearch" type="text" class="crud-input" style="max-width:320px;" placeholder="Tìm theo tên hoặc email...">
            <select v-model="userFilter" class="crud-input" style="max-width:180px;">
              <option value="all">Tất cả</option>
              <option value="selected">Đã chọn</option>
              <option value="available">Chưa chọn</option>
            </select>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
              <button type="button" class="crud-btn-secondary" @click="form.enrollUserIds = allUsers.map(u => u.id)">Chọn tất cả</button>
              <button type="button" class="crud-btn-secondary" @click="form.enrollUserIds = []">Bỏ chọn</button>
            </div>
          </div>

          <div class="enroll-meta-row">
            <div class="enroll-count">{{ form.enrollUserIds.length }} / {{ allUsers.length }} học viên đã chọn</div>
            <label class="import-label">
              <span class="import-title">Import từ Excel/CSV</span>
              <input type="file" accept=".csv,.xlsx,.xls" @change="handleImportFile" class="import-input">
            </label>
          </div>
          <p class="import-note">Tải file Excel về dạng CSV trước khi import. File cần có cột <strong>email</strong> hoặc <strong>id</strong>.</p>
          <div v-if="importMessage" class="crud-alert is-success">{{ importMessage }}</div>
          <div v-if="importError" class="crud-alert is-error">{{ importError }}</div>

          <div v-if="loadingUsers" class="crud-empty">Đang tải danh sách học viên...</div>
          <div v-else class="user-checklist">
            <div
              v-for="u in filteredUsers"
              :key="u.id"
              :class="['user-row', { selected: form.enrollUserIds.includes(u.id) }]"
              @click="toggleEnroll(u.id)"
            >
              <input :checked="form.enrollUserIds.includes(u.id)" type="checkbox" tabindex="-1" style="pointer-events:none;flex-shrink:0;">
              <div class="user-info">
                <span class="user-name">{{ u.name }}</span>
                <span class="user-email">{{ u.email }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Navigation bar ──────────────────────────────────────────── -->
        <div class="step-nav">
          <NuxtLink v-if="step === 1" to="/admin/quiz" class="crud-btn-secondary">
            Huỷ
          </NuxtLink>
          <button v-else type="button" class="crud-btn-secondary" @click="goBack">
            ← Quay lại
          </button>

          <div style="display:flex;align-items:center;gap:0.75rem;">
            <span class="step-progress">Bước {{ step }} / {{ totalSteps }}</span>
            <button
              v-if="step < totalSteps"
              type="button"
              class="crud-primary-btn"
              @click="goNext"
            >
              Tiếp theo →
            </button>
            <button
              v-else
              type="button"
              class="crud-primary-btn"
              :disabled="saving"
              @click="submit"
            >
              {{ saving ? 'Đang tạo...' : 'Tạo đề thi' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
/* ── Layout ── */
.create-layout {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 1.5rem;
  align-items: start;
}

/* ── Sidebar ── */
.step-sidebar {
  background: var(--surface, #fff);
  border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 12px;
  padding: 1rem 0;
  position: sticky;
  top: 1rem;
}
.sidebar-step {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 1.25rem;
  cursor: default;
  transition: background 0.15s;
}
.sidebar-step.active { background: color-mix(in srgb, var(--primary, #1976d2) 8%, transparent); }
.sidebar-step-num {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; font-weight: 700; flex-shrink: 0;
  background: var(--border-color, #e0e0e0); color: var(--text-secondary, #666);
}
.sidebar-step.active .sidebar-step-num {
  background: var(--primary, #1976d2); color: #fff;
}
.sidebar-step.done .sidebar-step-num {
  background: var(--primary, #1976d2); color: #fff; font-size: 0.7rem;
}
.sidebar-step-label {
  font-size: 0.85rem; font-weight: 500; color: var(--text-secondary, #666);
}
.sidebar-step.active .sidebar-step-label { color: var(--primary, #1976d2); font-weight: 700; }
.sidebar-step.done .sidebar-step-label { color: var(--text-primary, #333); }

/* ── Step content card ── */
.step-content {
  background: var(--surface, #fff);
  border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 12px;
  padding: 2rem;
}
.step-section { display: flex; flex-direction: column; gap: 0.5rem; }
.step-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--text-primary, #222); }
.step-desc { font-size: 0.87rem; color: var(--text-secondary, #666); margin: 0 0 1rem; }
.req { color: #e53e3e; }
.field-stack { display: flex; flex-direction: column; gap: 1rem; }
.field-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.field-divider { height: 1px; background: var(--border-color, #e0e0e0); margin: 1.5rem 0; }

/* ── Type cards ── */
.type-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.type-card {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 1.1rem 1rem; border: 2px solid var(--border-color, #e0e0e0);
  border-radius: 10px; cursor: pointer; transition: all 0.15s;
}
.type-card:hover { border-color: var(--primary, #1976d2); }
.type-card.selected {
  border-color: var(--primary, #1976d2);
  background: color-mix(in srgb, var(--primary, #1976d2) 7%, transparent);
}
.type-card-icon { font-size: 1.6rem; flex-shrink: 0; line-height: 1; }
.type-card-name { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.2rem; }
.type-card-desc { font-size: 0.78rem; color: var(--text-secondary, #666); line-height: 1.4; }

/* ── Checkboxes ── */
.check-row { display: flex; gap: 2rem; }
.check-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; }

/* ── Mode tabs ── */
.mode-tabs {
  display: flex; border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 8px; overflow: hidden; align-self: flex-start;
}
.mode-tab {
  padding: 0.55rem 1.1rem; border: none; background: transparent;
  cursor: pointer; font-size: 0.82rem; font-weight: 600;
  color: var(--text-secondary, #666); transition: all 0.15s; white-space: nowrap;
}
.mode-tab:not(:last-child) { border-right: 1px solid var(--border-color, #e0e0e0); }
.mode-tab.active { background: var(--primary, #1976d2); color: #fff; }
.mode-tab:not(.active):hover { background: var(--surface-hover, #f5f5f5); }
.mode-hint { font-size: 0.82rem; color: var(--text-secondary, #666); margin: 0; }

/* ── Bank cards ── */
.bank-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
.bank-card {
  display: flex; align-items: flex-start; gap: 0.6rem;
  padding: 0.8rem; border: 1.5px solid var(--border-color, #e0e0e0);
  border-radius: 8px; cursor: pointer; transition: all 0.15s;
}
.bank-card:hover { border-color: var(--primary, #1976d2); }
.bank-card.selected {
  border-color: var(--primary, #1976d2);
  background: color-mix(in srgb, var(--primary, #1976d2) 6%, transparent);
}
.bank-card-name { font-weight: 600; font-size: 0.85rem; }
.bank-card-course { font-size: 0.75rem; color: var(--text-secondary, #666); }
.bank-card-meta { font-size: 0.75rem; color: var(--text-secondary, #666); margin-top: 0.2rem; }

/* ── Bank count list ── */
.bank-count-list { display: flex; flex-direction: column; gap: 0.6rem; }
.bank-count-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.6rem 0.9rem; background: var(--surface-hover, #f9f9f9);
  border-radius: 8px; border: 1px solid var(--border-color, #e0e0e0);
}
.bank-count-name { font-size: 0.85rem; font-weight: 600; }
.bank-count-avail { font-size: 0.8rem; color: var(--text-secondary, #666); }

/* ── Difficulty table ── */
.diff-table { border: 1px solid var(--border-color, #e0e0e0); border-radius: 10px; overflow: hidden; }
.diff-header {
  display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr;
  padding: 0.6rem 1rem;
  background: var(--surface-hover, #f5f5f5);
  font-size: 0.8rem; font-weight: 700; color: var(--text-secondary, #666);
}
.diff-row {
  display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr;
  padding: 0.6rem 1rem;
  border-top: 1px solid var(--border-color, #e0e0e0);
  align-items: center; font-size: 0.85rem;
}
.diff-row.warning { background: #fff8e1; }
.diff-name { font-weight: 600; }
.diff-pct-cell { display: flex; align-items: center; gap: 0.3rem; }
.diff-count-cell { font-weight: 600; }
.diff-avail-cell { font-size: 0.82rem; color: var(--text-secondary, #666); }
.diff-avail-cell.warn { color: #e67e22; font-weight: 600; }
.diff-footer {
  display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr;
  padding: 0.65rem 1rem; font-size: 0.85rem; font-weight: 700;
  background: var(--surface-hover, #f5f5f5);
  border-top: 2px solid var(--border-color, #e0e0e0);
}
.diff-footer.error { background: #fff5f5; }

/* ── Manual question checklist ── */
.manual-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 0.75rem;
}
.manual-count {
  font-size: 0.85rem; font-weight: 600;
  color: var(--primary, #1976d2);
}
.question-checklist {
  border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 10px; overflow: hidden;
  max-height: 400px; overflow-y: auto;
}
.q-bank-header {
  padding: 0.45rem 1rem;
  background: var(--surface-hover, #f5f5f5);
  font-size: 0.8rem; font-weight: 700; color: var(--text-secondary, #666);
  position: sticky; top: 0;
  border-bottom: 1px solid var(--border-color, #e0e0e0);
}
.q-row {
  display: flex; align-items: center; gap: 0.7rem;
  padding: 0.55rem 1rem; cursor: pointer; font-size: 0.83rem;
  border-top: 1px solid var(--border-color, #e0e0e0);
  transition: background 0.1s;
}
.q-row:hover { background: var(--surface-hover, #f5f5f5); }
.q-row.selected { background: color-mix(in srgb, var(--primary, #1976d2) 8%, transparent); }
.q-text { flex: 1; }
.q-diff-badge {
  font-size: 0.72rem; padding: 0.15rem 0.45rem;
  border-radius: 4px; background: var(--surface-hover, #eee);
  color: var(--text-secondary, #666); flex-shrink: 0;
}

.skip-hint { font-size: 0.83rem; color: var(--text-secondary, #888); text-align: center; padding: 1.5rem; }

/* ── Enrollment ── */
.enroll-toolbar { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; }
.enroll-meta-row {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.75rem;
}
.import-label {
  display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer;
  border: 1px dashed var(--border-color, #cbd5e1); padding: 0.75rem 1rem; border-radius: 10px;
  background: var(--surface-hover, #fafafa);
}
.import-title { font-size: 0.84rem; font-weight: 600; color: var(--text-primary, #222); }
.import-input { display: none; }
.import-note { font-size: 0.82rem; color: var(--text-secondary, #666); margin: 0 0 0.75rem; }
.enroll-count { font-size: 0.85rem; font-weight: 600; color: var(--primary, #1976d2); }
.user-checklist {
  border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 10px; overflow: hidden;
  max-height: 400px; overflow-y: auto;
}
.user-row {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.6rem 1rem; cursor: pointer;
  border-top: 1px solid var(--border-color, #e0e0e0);
  transition: background 0.1s;
}
.user-row:first-child { border-top: none; }
.user-row:hover { background: var(--surface-hover, #f5f5f5); }
.user-row.selected { background: color-mix(in srgb, var(--primary, #1976d2) 8%, transparent); }
.user-info { display: flex; flex-direction: column; gap: 0.1rem; }
.user-name { font-size: 0.85rem; font-weight: 600; }
.user-email { font-size: 0.75rem; color: var(--text-secondary, #666); }

/* ── Nav bar ── */
.step-nav {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 2rem; padding-top: 1.5rem;
  border-top: 1px solid var(--border-color, #e0e0e0);
}
.step-progress { font-size: 0.83rem; color: var(--text-secondary, #666); }
</style>
