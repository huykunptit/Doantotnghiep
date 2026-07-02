<template>
  <div class="space-y-6">
    <!-- Left Sidebar - Banks (always visible as compact) -->
    <aside class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Question Banks</p>
          <h3 class="mt-1 font-headline text-lg font-bold text-on-surface">Ngân hàng câu hỏi</h3>
        </div>
        <button class="rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white" @click="showBankModal = true">+ Bank</button>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="bank in banks"
          :key="bank.id"
          class="rounded-lg px-3 py-2 text-sm font-bold transition-all whitespace-nowrap"
          :class="activeBankId === bank.id ? 'bg-primary text-white shadow-md' : 'border border-surface-dim/40 bg-surface-low text-on-surface hover:border-primary/30'"
          @click="selectBank(bank)"
          :title="`${bank.questions_count || 0} câu hỏi · ${bank.groups_count || 0} nhóm`"
        >
          {{ bank.name }}
          <button class="ml-2 inline-block text-[12px] opacity-70 hover:opacity-100" @click.stop="openBankEdit(bank)">✎</button>
          <button class="ml-1 inline-block text-[12px] opacity-70 hover:opacity-100" @click.stop="deleteBank(bank)">✕</button>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <section v-if="!activeBank" class="rounded-[2rem] border border-dashed border-surface-dim/40 bg-surface-low p-12 text-center">
      <span class="material-symbols-outlined text-5xl text-outline/50">inventory_2</span>
      <h3 class="mt-4 font-headline text-2xl font-bold text-on-surface">Chọn một ngân hàng câu hỏi</h3>
      <p class="mt-2 text-sm text-on-surface-variant">Bạn có thể tạo ngân hàng, nhóm câu hỏi, rồi dùng lại cho lesson quiz hoặc kỳ thi độc lập.</p>
    </section>

    <template v-else>
      <!-- Bank Header -->
      <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Active Bank</p>
            <h3 class="mt-1 font-headline text-2xl font-bold text-on-surface">{{ activeBank.name }}</h3>
            <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ activeBank.description || 'Chưa có mô tả cho ngân hàng câu hỏi này.' }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="openGroupModal(activeBank.id)">+ Nhóm</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" @click="openQuestionModal()">+ Câu hỏi</button>
          </div>
        </div>
      </div>

      <!-- Filters (Compact Dropdowns) -->
      <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-col gap-3 sm:flex-row sm:flex-1 sm:gap-3">
            <!-- Group Filter Dropdown -->
            <div class="flex-1">
              <label class="text-xs font-bold uppercase text-outline-variant">Nhóm</label>
              <select v-model="activeGroupId" class="w-full mt-1 rounded-lg border border-surface-dim/40 bg-surface-low px-3 py-2 text-sm outline-none focus:border-primary">
                <option :value="null">Tất cả câu hỏi</option>
                <option v-for="group in activeBank.groups || []" :key="group.id" :value="group.id">
                  {{ group.name }} ({{ group.questions?.length || group.questions_count || 0 }})
                </option>
              </select>
            </div>

            <!-- Search Filter -->
            <div class="flex-1">
              <label class="text-xs font-bold uppercase text-outline-variant">Tìm kiếm</label>
              <div class="relative mt-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
                <input v-model="search" type="text" placeholder="Lọc câu hỏi..." class="w-full rounded-lg border border-surface-dim/40 bg-surface-low py-2 pl-9 pr-3 text-sm outline-none focus:border-primary">
              </div>
            </div>
          </div>

          <!-- Pagination Info -->
          <div class="flex items-center gap-3 text-sm text-on-surface-variant">
            <span>{{ filteredQuestions.length }} câu</span>
            <div v-if="totalPages > 1" class="flex items-center gap-2">
              <button
                :disabled="currentPage === 1"
                @click="currentPage--"
                class="rounded px-2 py-1 text-xs font-bold border border-surface-dim/40 disabled:opacity-50"
              >
                ←
              </button>
              <span class="text-xs font-bold">{{ currentPage }}/{{ totalPages }}</span>
              <button
                :disabled="currentPage === totalPages"
                @click="currentPage++"
                class="rounded px-2 py-1 text-xs font-bold border border-surface-dim/40 disabled:opacity-50"
              >
                →
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Questions Table/List -->
      <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
        <div v-if="filteredQuestions.length === 0" class="rounded-2xl border border-dashed border-surface-dim/40 bg-surface-low p-10 text-center text-sm text-on-surface-variant">
          Chưa có câu hỏi nào trong khu vực đang chọn.
        </div>

        <div v-else class="space-y-4">
          <div v-for="question in paginatedQuestions" :key="question.id" class="rounded-2xl border border-surface-dim/40 bg-surface-low p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                  <span class="rounded-lg bg-primary/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-primary">{{ questionTypeLabel(question.type) }}</span>
                  <span class="rounded-lg bg-surface-high px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Độ khó {{ question.difficulty || 1 }}/5</span>
                  <span v-if="question.group?.name" class="rounded-lg bg-secondary/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-secondary">{{ question.group.name }}</span>
                </div>
                <p class="text-sm font-semibold leading-6 text-on-surface" v-html="question.content"></p>
                <div class="mt-3 grid gap-2">
                  <div v-for="answer in question.answers || []" :key="answer.id" class="rounded-xl border px-3 py-2 text-sm flex items-center gap-2" :class="answer.is_correct ? 'border-secondary/30 bg-secondary/10 text-on-surface' : 'border-surface-dim/30 bg-surface-lowest text-on-surface-variant'">
                    <span v-if="answer.is_correct" class="material-symbols-outlined text-base flex-shrink-0">check_circle</span>
                    <span v-else class="w-5 flex-shrink-0"></span>
                    {{ answer.content }}
                  </div>
                </div>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <button class="rounded-xl border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface" @click="openQuestionEdit(question)">Sửa</button>
                <button class="rounded-xl border border-error/20 bg-error-container/20 px-3 py-2 text-xs font-bold text-error" @click="deleteQuestion(question)">Xóa</button>
              </div>
            </div>
          </div>

          <!-- Full Pagination Controls -->
          <div v-if="totalPages > 1" class="mt-6 flex items-center justify-between flex-wrap gap-3">
            <span class="text-sm text-on-surface-variant">
              Trang {{ currentPage }} / {{ totalPages }}
            </span>
            <div class="flex gap-2 items-center">
              <button
                :disabled="currentPage === 1"
                @click="currentPage--"
                class="rounded-lg border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface disabled:opacity-50"
              >
                ← Trước
              </button>
              <div class="flex gap-1">
                <button
                  v-for="page in Math.min(totalPages, 5)"
                  :key="page"
                  :class="page === currentPage ? 'bg-primary text-white' : 'border border-surface-dim/40 text-on-surface'"
                  @click="currentPage = page"
                  class="rounded-lg px-3 py-2 text-xs font-bold"
                >
                  {{ page }}
                </button>
                <span v-if="totalPages > 5" class="px-2">...</span>
              </div>
              <button
                :disabled="currentPage === totalPages"
                @click="currentPage++"
                class="rounded-lg border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface disabled:opacity-50"
              >
                Sau →
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import RichTextEditor from '~/components/dashboard/RichTextEditor.vue'

const props = defineProps<{ courseId: number }>()

const auth = useAuthStore()
const banks = ref<any[]>([])
const ungroupedSets = ref<any[]>([])
const activeBank = ref<any | null>(null)
const activeBankId = ref<number | null>(null)
const activeGroupId = ref<number | null>(null)
const search = ref('')
const currentPage = ref(1)
const perPage = 5

const showBankModal = ref(false)
const showGroupModal = ref(false)
const showQuestionModal = ref(false)
const editingBank = ref<any | null>(null)
const editingGroup = ref<any | null>(null)
const editingQuestion = ref<any | null>(null)

const bankForm = reactive({ name: '', description: '' })
const groupForm = reactive({ name: '', description: '', question_bank_id: null as number | null })
const questionForm = reactive({
  content: '',
  type: 'single_choice',
  difficulty: 1,
  explanation: '',
  question_group_id: null as number | null,
  answers: [{ content: '', is_correct: true }, { content: '', is_correct: false }],
})

const filteredQuestions = computed(() => {
  const source = activeGroupId.value
    ? (activeBank.value?.groups || []).find((group: any) => group.id === activeGroupId.value)?.questions || []
    : activeBank.value?.questions || []

  return source.filter((question: any) => {
    const keyword = search.value.trim().toLowerCase()
    if (!keyword) return true
    // Strip HTML tags for search
    const contentText = question.content?.replace(/<[^>]*>/g, '') || ''
    const explanationText = question.explanation?.replace(/<[^>]*>/g, '') || ''
    return `${contentText} ${explanationText}`.toLowerCase().includes(keyword)
  })
})

const paginatedQuestions = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredQuestions.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredQuestions.value.length / perPage)
})

const activeGroupTitle = computed(() => {
  if (!activeBank.value) return ''
  if (activeGroupId.value === null) return 'Tất cả câu hỏi trong ngân hàng'
  return (activeBank.value.groups || []).find((group: any) => group.id === activeGroupId.value)?.name || 'Nhóm câu hỏi'
})

watch([activeGroupId, search], () => {
  currentPage.value = 1
})

function headers() {
  return { Authorization: `Bearer ${auth.token}` }
}

async function loadBanks() {
  const res = await $fetch<any>(`/api/courses/${props.courseId}/question-banks`, { headers: headers() })
  banks.value = res.banks || []
  ungroupedSets.value = res.ungrouped_sets || []
  if (activeBankId.value) {
    const found = banks.value.find((bank: any) => bank.id === activeBankId.value)
    if (found) {
      await selectBank(found)
      return
    }
  }
  activeBank.value = null
}

async function selectBank(bank: any) {
  activeBankId.value = bank.id
  activeGroupId.value = null
  activeBank.value = await $fetch<any>(`/api/courses/${props.courseId}/question-banks/${bank.id}`, { headers: headers() })
}

function resetBankForm() {
  bankForm.name = ''
  bankForm.description = ''
}

function openBankEdit(bank: any) {
  editingBank.value = bank
  bankForm.name = bank.name
  bankForm.description = bank.description || ''
  showBankModal.value = true
}

function closeBankModal() {
  editingBank.value = null
  resetBankForm()
  showBankModal.value = false
}

async function saveBank() {
  if (!bankForm.name.trim()) return
  if (editingBank.value) {
    await $fetch(`/api/courses/${props.courseId}/question-banks/${editingBank.value.id}`, { method: 'PUT', headers: headers(), body: bankForm })
  } else {
    await $fetch(`/api/courses/${props.courseId}/question-banks`, { method: 'POST', headers: headers(), body: bankForm })
  }
  closeBankModal()
  await loadBanks()
}

async function deleteBank(bank: any) {
  if (!confirm(`Xóa ngân hàng "${bank.name}"?`)) return
  await $fetch(`/api/courses/${props.courseId}/question-banks/${bank.id}`, { method: 'DELETE', headers: headers() })
  if (activeBankId.value === bank.id) {
    activeBankId.value = null
    activeBank.value = null
  }
  await loadBanks()
}

function openGroupModal(bankId: number | null) {
  editingGroup.value = null
  groupForm.name = ''
  groupForm.description = ''
  groupForm.question_bank_id = bankId
  showGroupModal.value = true
}

function openGroupEdit(group: any) {
  editingGroup.value = group
  groupForm.name = group.name
  groupForm.description = group.description || ''
  groupForm.question_bank_id = group.question_bank_id ?? activeBankId.value
  showGroupModal.value = true
}

function closeGroupModal() {
  editingGroup.value = null
  groupForm.name = ''
  groupForm.description = ''
  groupForm.question_bank_id = null
  showGroupModal.value = false
}

async function saveGroup() {
  if (!groupForm.name.trim()) return
  if (editingGroup.value) {
    await $fetch(`/api/courses/${props.courseId}/question-groups/${editingGroup.value.id}`, { method: 'PUT', headers: headers(), body: groupForm })
  } else {
    await $fetch(`/api/courses/${props.courseId}/question-groups`, { method: 'POST', headers: headers(), body: groupForm })
  }
  closeGroupModal()
  await loadBanks()
  if (activeBankId.value) {
    const found = banks.value.find((bank: any) => bank.id === activeBankId.value)
    if (found) await selectBank(found)
  }
}

async function deleteGroup(group: any) {
  if (!confirm(`Xóa nhóm "${group.name}"?`)) return
  await $fetch(`/api/courses/${props.courseId}/question-groups/${group.id}`, { method: 'DELETE', headers: headers() })
  await loadBanks()
  if (activeBankId.value) {
    const found = banks.value.find((bank: any) => bank.id === activeBankId.value)
    if (found) await selectBank(found)
  }
}

function resetQuestionForm() {
  questionForm.content = ''
  questionForm.type = 'single_choice'
  questionForm.difficulty = 1
  questionForm.explanation = ''
  questionForm.question_group_id = null
  questionForm.answers = [{ content: '', is_correct: true }, { content: '', is_correct: false }]
}

function openQuestionModal() {
  editingQuestion.value = null
  resetQuestionForm()
  showQuestionModal.value = true
}

function openQuestionEdit(question: any) {
  editingQuestion.value = question
  questionForm.content = question.content
  questionForm.type = question.type || 'single_choice'
  questionForm.difficulty = question.difficulty || 1
  questionForm.explanation = question.explanation || ''
  questionForm.question_group_id = question.question_group_id || null
  questionForm.answers = (question.answers || []).map((answer: any) => ({
    content: answer.content,
    is_correct: !!answer.is_correct,
  }))
  showQuestionModal.value = true
}

function closeQuestionModal() {
  editingQuestion.value = null
  resetQuestionForm()
  showQuestionModal.value = false
}

async function saveQuestion() {
  if (!activeBank.value || !questionForm.content.trim()) return
  const payload = {
    question_group_id: questionForm.question_group_id,
    content: questionForm.content,
    type: questionForm.type,
    difficulty: questionForm.difficulty,
    explanation: questionForm.explanation,
    answers: questionForm.answers,
  }

  if (editingQuestion.value) {
    await $fetch(`/api/courses/${props.courseId}/question-banks/${activeBank.value.id}/questions/${editingQuestion.value.id}`, {
      method: 'PUT',
      headers: headers(),
      body: payload,
    })
  } else {
    await $fetch(`/api/courses/${props.courseId}/question-banks/${activeBank.value.id}/questions`, {
      method: 'POST',
      headers: headers(),
      body: payload,
    })
  }

  closeQuestionModal()
  await selectBank(activeBank.value)
  await loadBanks()
}

async function deleteQuestion(question: any) {
  if (!activeBank.value || !confirm('Xóa câu hỏi này?')) return
  await $fetch(`/api/courses/${props.courseId}/question-banks/${activeBank.value.id}/questions/${question.id}`, {
    method: 'DELETE',
    headers: headers(),
  })
  await selectBank(activeBank.value)
  await loadBanks()
}

function questionTypeLabel(type: string) {
  return {
    single_choice: 'Một đáp án',
    multiple_choice: 'Nhiều đáp án',
    essay: 'Tự luận',
    ordering: 'Sắp xếp',
  }[type] || type
}

onMounted(loadBanks)
</script>
