<template>
  <div class="grid gap-6 xl:grid-cols-[280px_1fr]">
    <aside class="space-y-4 rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Question Banks</p>
          <h3 class="mt-1 font-headline text-xl font-bold text-on-surface">Ngân hàng câu hỏi</h3>
        </div>
        <button class="rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white" @click="showBankModal = true">+ Bank</button>
      </div>

      <div class="space-y-2">
        <button
          v-for="bank in banks"
          :key="bank.id"
          class="w-full rounded-2xl border px-4 py-3 text-left transition-all"
          :class="activeBankId === bank.id ? 'border-primary bg-primary/5' : 'border-surface-dim/40 bg-surface-low hover:border-primary/30'"
          @click="selectBank(bank)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold text-on-surface">{{ bank.name }}</p>
              <p class="mt-1 text-xs text-on-surface-variant">{{ bank.questions_count || 0 }} câu hỏi · {{ bank.groups_count || 0 }} nhóm</p>
            </div>
            <div class="flex gap-1">
              <button class="rounded-lg p-1 text-outline hover:bg-surface-high hover:text-primary" @click.stop="openBankEdit(bank)">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
              <button class="rounded-lg p-1 text-outline hover:bg-error-container/20 hover:text-error" @click.stop="deleteBank(bank)">
                <span class="material-symbols-outlined text-[18px]">delete</span>
              </button>
            </div>
          </div>
        </button>
      </div>

      <div class="rounded-2xl border border-surface-dim/40 bg-surface-low p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Question Groups</p>
            <p class="mt-1 text-sm font-semibold text-on-surface">Nhóm câu hỏi cấp khóa học</p>
          </div>
          <button class="rounded-xl border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface" @click="openGroupModal(null)">+ Group</button>
        </div>
        <div class="mt-3 space-y-2">
          <div v-for="group in ungroupedSets" :key="group.id" class="flex items-center justify-between rounded-xl bg-surface-lowest px-3 py-3">
            <div>
              <p class="text-sm font-semibold text-on-surface">{{ group.name }}</p>
              <p class="text-xs text-on-surface-variant">{{ group.questions_count || 0 }} câu hỏi</p>
            </div>
            <div class="flex gap-1">
              <button class="rounded-lg p-1 text-outline hover:bg-surface-high hover:text-primary" @click="openGroupEdit(group)">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
              <button class="rounded-lg p-1 text-outline hover:bg-error-container/20 hover:text-error" @click="deleteGroup(group)">
                <span class="material-symbols-outlined text-[18px]">delete</span>
              </button>
            </div>
          </div>
          <p v-if="ungroupedSets.length === 0" class="text-sm text-on-surface-variant">Chưa có nhóm câu hỏi độc lập.</p>
        </div>
      </div>
    </aside>

    <section class="space-y-6">
      <div v-if="!activeBank" class="rounded-[2rem] border border-dashed border-surface-dim/40 bg-surface-low p-12 text-center">
        <span class="material-symbols-outlined text-5xl text-outline/50">inventory_2</span>
        <h3 class="mt-4 font-headline text-2xl font-bold text-on-surface">Chọn một ngân hàng câu hỏi</h3>
        <p class="mt-2 text-sm text-on-surface-variant">Bạn có thể tạo ngân hàng, nhóm câu hỏi, rồi dùng lại cho lesson quiz hoặc kỳ thi độc lập.</p>
      </div>

      <template v-else>
        <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Active Bank</p>
              <h3 class="mt-1 font-headline text-2xl font-bold text-on-surface">{{ activeBank.name }}</h3>
              <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ activeBank.description || 'Chưa có mô tả cho ngân hàng câu hỏi này.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button class="rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="openGroupModal(activeBank.id)">+ Nhóm trong bank</button>
              <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" @click="openQuestionModal()">+ Thêm câu hỏi</button>
            </div>
          </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
          <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Groups</p>
                <h4 class="mt-1 font-bold text-on-surface">Nhóm câu hỏi</h4>
              </div>
            </div>
            <div class="space-y-2">
              <button
                class="w-full rounded-2xl border px-4 py-3 text-left transition-all"
                :class="activeGroupId === null ? 'border-primary bg-primary/5' : 'border-surface-dim/40 bg-surface-low hover:border-primary/30'"
                @click="activeGroupId = null"
              >
                <p class="font-semibold text-on-surface">Tất cả câu hỏi trong bank</p>
                <p class="text-xs text-on-surface-variant">{{ activeBank.questions?.length || 0 }} câu hỏi</p>
              </button>
              <button
                v-for="group in activeBank.groups || []"
                :key="group.id"
                class="w-full rounded-2xl border px-4 py-3 text-left transition-all"
                :class="activeGroupId === group.id ? 'border-primary bg-primary/5' : 'border-surface-dim/40 bg-surface-low hover:border-primary/30'"
                @click="activeGroupId = group.id"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-semibold text-on-surface">{{ group.name }}</p>
                    <p class="text-xs text-on-surface-variant">{{ group.questions?.length || group.questions_count || 0 }} câu hỏi</p>
                  </div>
                  <div class="flex gap-1">
                    <button class="rounded-lg p-1 text-outline hover:bg-surface-high hover:text-primary" @click.stop="openGroupEdit(group)">
                      <span class="material-symbols-outlined text-[18px]">edit</span>
                    </button>
                    <button class="rounded-lg p-1 text-outline hover:bg-error-container/20 hover:text-error" @click.stop="deleteGroup(group)">
                      <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-5 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Questions</p>
                <h4 class="mt-1 font-bold text-on-surface">{{ activeGroupTitle }}</h4>
              </div>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                <input v-model="search" type="text" placeholder="Lọc câu hỏi..." class="rounded-xl border border-surface-dim/40 bg-surface-low py-2 pl-10 pr-4 text-sm outline-none focus:border-primary">
              </div>
            </div>

            <div v-if="filteredQuestions.length === 0" class="rounded-2xl border border-dashed border-surface-dim/40 bg-surface-low p-10 text-center text-sm text-on-surface-variant">
              Chưa có câu hỏi nào trong khu vực đang chọn.
            </div>

            <div v-else class="space-y-4">
              <div v-for="question in filteredQuestions" :key="question.id" class="rounded-2xl border border-surface-dim/40 bg-surface-low p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="rounded-lg bg-primary/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-primary">{{ questionTypeLabel(question.type) }}</span>
                      <span class="rounded-lg bg-surface-high px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Độ khó {{ question.difficulty || 1 }}/5</span>
                      <span v-if="question.group?.name" class="rounded-lg bg-secondary/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-secondary">{{ question.group.name }}</span>
                    </div>
                    <p class="mt-3 text-sm font-semibold leading-6 text-on-surface">{{ question.content }}</p>
                    <div class="mt-3 grid gap-2">
                      <div v-for="answer in question.answers || []" :key="answer.id" class="rounded-xl border px-3 py-2 text-sm" :class="answer.is_correct ? 'border-secondary/30 bg-secondary/10 text-on-surface' : 'border-surface-dim/30 bg-surface-lowest text-on-surface-variant'">
                        {{ answer.content }}
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <button class="rounded-xl border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface" @click="openQuestionEdit(question)">Sửa</button>
                    <button class="rounded-xl border border-error/20 bg-error-container/20 px-3 py-2 text-xs font-bold text-error" @click="deleteQuestion(question)">Xóa</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>

    <Teleport to="body">
      <div v-if="showBankModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="closeBankModal">
        <div class="w-full max-w-lg rounded-[2rem] bg-surface-lowest p-6 shadow-ambient">
          <h3 class="font-headline text-xl font-bold text-on-surface">{{ editingBank ? 'Cập nhật ngân hàng' : 'Tạo ngân hàng mới' }}</h3>
          <div class="mt-5 space-y-4">
            <input v-model="bankForm.name" type="text" placeholder="Tên ngân hàng" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary">
            <textarea v-model="bankForm.description" rows="4" placeholder="Mô tả ngắn" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary"></textarea>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <button class="rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="closeBankModal">Hủy</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" @click="saveBank">Lưu</button>
          </div>
        </div>
      </div>

      <div v-if="showGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="closeGroupModal">
        <div class="w-full max-w-lg rounded-[2rem] bg-surface-lowest p-6 shadow-ambient">
          <h3 class="font-headline text-xl font-bold text-on-surface">{{ editingGroup ? 'Cập nhật nhóm câu hỏi' : 'Tạo nhóm câu hỏi' }}</h3>
          <div class="mt-5 space-y-4">
            <input v-model="groupForm.name" type="text" placeholder="Tên nhóm câu hỏi" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary">
            <textarea v-model="groupForm.description" rows="4" placeholder="Mô tả ngắn" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary"></textarea>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <button class="rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="closeGroupModal">Hủy</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" @click="saveGroup">Lưu</button>
          </div>
        </div>
      </div>

      <div v-if="showQuestionModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 p-4 backdrop-blur-sm">
        <div class="mx-auto mt-10 w-full max-w-3xl rounded-[2rem] bg-surface-lowest p-6 shadow-ambient">
          <div class="flex items-center justify-between">
            <h3 class="font-headline text-xl font-bold text-on-surface">{{ editingQuestion ? 'Cập nhật câu hỏi' : 'Thêm câu hỏi mới' }}</h3>
            <button class="rounded-full p-2 text-outline hover:bg-surface-low" @click="closeQuestionModal">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="mt-5 grid gap-4">
            <textarea v-model="questionForm.content" rows="4" placeholder="Nội dung câu hỏi" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary"></textarea>
            <div class="grid gap-4 md:grid-cols-3">
              <select v-model="questionForm.type" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary">
                <option value="single_choice">Một đáp án</option>
                <option value="multiple_choice">Nhiều đáp án</option>
                <option value="essay">Tự luận</option>
                <option value="ordering">Sắp xếp</option>
              </select>
              <input v-model.number="questionForm.difficulty" type="number" min="1" max="5" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Độ khó">
              <select v-model="questionForm.question_group_id" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary">
                <option :value="null">Không gán nhóm</option>
                <option v-for="group in activeBank?.groups || []" :key="group.id" :value="group.id">{{ group.name }}</option>
              </select>
            </div>
            <textarea v-model="questionForm.explanation" rows="3" placeholder="Giải thích đáp án (tuỳ chọn)" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary"></textarea>
            <div class="space-y-3">
              <div v-for="(answer, index) in questionForm.answers" :key="index" class="flex items-center gap-3">
                <input v-model="answer.is_correct" type="checkbox" class="h-4 w-4 rounded border-surface-dim/40 text-primary">
                <input v-model="answer.content" type="text" class="flex-1 rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" :placeholder="`Đáp án ${index + 1}`">
                <button class="rounded-xl border border-surface-dim/40 px-3 py-2 text-xs font-bold text-on-surface" :disabled="questionForm.answers.length <= 2" @click="questionForm.answers.splice(index, 1)">Xóa</button>
              </div>
            </div>
            <button class="w-fit rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="questionForm.answers.push({ content: '', is_correct: false })">+ Thêm đáp án</button>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <button class="rounded-xl border border-surface-dim/40 px-4 py-2 text-sm font-bold text-on-surface" @click="closeQuestionModal">Hủy</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" @click="saveQuestion">Lưu câu hỏi</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ courseId: number }>()

const auth = useAuthStore()
const banks = ref<any[]>([])
const ungroupedSets = ref<any[]>([])
const activeBank = ref<any | null>(null)
const activeBankId = ref<number | null>(null)
const activeGroupId = ref<number | null>(null)
const search = ref('')

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
    return `${question.content} ${question.explanation || ''}`.toLowerCase().includes(keyword)
  })
})

const activeGroupTitle = computed(() => {
  if (!activeBank.value) return ''
  if (activeGroupId.value === null) return 'Tất cả câu hỏi trong ngân hàng'
  return (activeBank.value.groups || []).find((group: any) => group.id === activeGroupId.value)?.name || 'Nhóm câu hỏi'
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
