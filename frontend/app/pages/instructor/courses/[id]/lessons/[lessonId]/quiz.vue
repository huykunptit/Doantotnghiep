<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="text-sm text-on-surface-variant hover:text-primary">← Quay lại Curriculum</NuxtLink>
        <h1 class="mt-2 text-2xl font-bold text-on-surface">Quản lý Quiz bài học</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Bài học: {{ lesson?.title }}</p>
      </div>
      <UiButton @click="saveQuiz" :loading="saving">Lưu Quiz</UiButton>
    </div>

    <div v-if="loading" class="rounded-3xl border border-surface-dim/40 bg-surface-low p-10 text-center text-on-surface-variant">Đang tải dữ liệu...</div>

    <div v-else class="grid gap-6 xl:grid-cols-[1fr_360px]">
      <UiCard class="space-y-5">
        <input v-model="quiz.title" type="text" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Tên quiz">
        <textarea v-model="quiz.description" rows="4" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Mô tả / hướng dẫn"></textarea>
        <div class="grid grid-cols-2 gap-4">
          <input v-model.number="quiz.time_limit" type="number" min="0" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Phút">
          <input v-model.number="quiz.pass_score" type="number" min="0" max="100" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Điểm đạt">
        </div>

        <div class="rounded-2xl border border-surface-dim/40 bg-surface-low p-4">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-on-surface">Câu hỏi đã gắn</h3>
            <button class="text-sm font-bold text-primary" @click="addInlineQuestion">+ Soạn nhanh</button>
          </div>
          <div class="mt-4 space-y-4">
            <div v-for="(question, index) in selectedQuestions" :key="question.localKey" class="rounded-2xl border border-surface-dim/40 bg-surface-lowest p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-bold uppercase tracking-wide text-outline-variant">
                    {{ question.id ? 'Từ ngân hàng câu hỏi' : 'Câu hỏi soạn nhanh' }}
                  </p>
                  <textarea v-if="!question.id" v-model="question.content" rows="3" class="mt-2 w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Nội dung câu hỏi"></textarea>
                  <p v-else class="mt-2 text-sm font-semibold leading-6 text-on-surface">{{ question.content }}</p>
                  <div v-if="question.id" class="mt-2 text-xs text-on-surface-variant">{{ question.group?.name || 'Không nhóm' }} · {{ question.answers?.length || 0 }} đáp án</div>
                  <div v-else class="mt-3 space-y-2">
                    <div v-for="(answer, answerIndex) in question.answers" :key="`${question.localKey}-${answerIndex}`" class="flex items-center gap-3">
                      <input v-model="answer.is_correct" type="checkbox" class="h-4 w-4 rounded border-surface-dim/40 text-primary">
                      <input v-model="answer.content" type="text" class="flex-1 rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" :placeholder="`Đáp án ${answerIndex + 1}`">
                    </div>
                    <button class="text-xs font-bold text-primary" @click="question.answers.push({ content: '', is_correct: false })">+ Thêm đáp án</button>
                  </div>
                </div>
                <button class="rounded-xl border border-error/20 bg-error-container/20 px-3 py-2 text-xs font-bold text-error" @click="removeQuestion(index)">Gỡ</button>
              </div>
            </div>
            <p v-if="selectedQuestions.length === 0" class="text-sm text-on-surface-variant">Quiz này chưa có câu hỏi nào.</p>
          </div>
        </div>
      </UiCard>

      <UiCard class="space-y-4">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline-variant">Question Bank</p>
          <select v-model="selectedBankId" class="mt-2 w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" @change="loadBank">
            <option :value="null">Chọn ngân hàng</option>
            <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
          </select>
        </div>

        <div class="space-y-3">
          <label v-for="question in bankQuestions" :key="question.id" class="flex cursor-pointer items-start gap-3 rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3">
            <input :checked="selectedQuestionIds.includes(question.id)" type="checkbox" class="mt-1 h-4 w-4 rounded border-surface-dim/40 text-primary" @change="toggleQuestion(question)">
            <div>
              <p class="text-sm font-semibold text-on-surface">{{ question.content }}</p>
              <p class="mt-1 text-xs text-on-surface-variant">{{ question.group?.name || 'Không nhóm' }} · {{ question.answers?.length || 0 }} đáp án</p>
            </div>
          </label>
          <p v-if="selectedBankId && bankQuestions.length === 0" class="text-sm text-on-surface-variant">Ngân hàng này chưa có câu hỏi.</p>
        </div>
      </UiCard>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const lessonId = Number(route.params.lessonId)
const loading = ref(true)
const saving = ref(false)
const lesson = ref<any>(null)
const banks = ref<any[]>([])
const bankQuestions = ref<any[]>([])
const selectedBankId = ref<number | null>(null)
const quiz = reactive({
  title: 'Bài tập trắc nghiệm',
  description: '',
  time_limit: null as number | null,
  pass_score: 80,
})
const selectedQuestions = ref<any[]>([])
const selectedQuestionIds = computed(() => selectedQuestions.value.filter((question) => !!question.id).map((question) => question.id))

function headers() {
  return { Authorization: `Bearer ${auth.token}` }
}

function localKey() {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}

async function loadBank() {
  if (!selectedBankId.value) {
    bankQuestions.value = []
    return
  }
  const bank = await $fetch<any>(`/api/courses/${courseId}/question-banks/${selectedBankId.value}`, { headers: headers() })
  bankQuestions.value = bank.questions || []
}

function addInlineQuestion() {
  selectedQuestions.value.push({
    localKey: localKey(),
    id: null,
    content: '',
    type: 'single_choice',
    answers: [
      { content: '', is_correct: true },
      { content: '', is_correct: false },
    ],
  })
}

function removeQuestion(index: number) {
  selectedQuestions.value.splice(index, 1)
}

function toggleQuestion(question: any) {
  const exists = selectedQuestions.value.find((item) => item.id === question.id)
  if (exists) {
    selectedQuestions.value = selectedQuestions.value.filter((item) => item.id !== question.id)
  } else {
    selectedQuestions.value = [...selectedQuestions.value, { ...question, localKey: localKey() }]
  }
}

async function loadData() {
  loading.value = true
  try {
    lesson.value = await useApi(`/courses/${courseId}/lessons/${lessonId}`, { token: auth.token })
    const bankRes = await $fetch<any>(`/api/courses/${courseId}/question-banks`, { headers: headers() })
    banks.value = bankRes.banks || []

    try {
      const res = await useApi<any>(`/courses/${courseId}/lessons/${lessonId}/quiz`, { token: auth.token })
      quiz.title = res.quiz.title
      quiz.description = res.quiz.description || ''
      quiz.time_limit = res.quiz.time_limit
      quiz.pass_score = res.quiz.pass_score || 80
      selectedQuestions.value = (res.questions || []).map((question: any) => ({ ...question, localKey: localKey() }))
    } catch {
      selectedQuestions.value = []
    }
  } finally {
    loading.value = false
  }
}

async function saveQuiz() {
  if (!quiz.title.trim()) return
  saving.value = true
  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}/quiz`, {
      method: 'POST',
      token: auth.token,
      body: {
        ...quiz,
        question_ids: selectedQuestionIds.value,
        questions: selectedQuestions.value
          .filter((question) => !question.id)
          .map((question) => ({
            content: question.content,
            type: question.type || 'single_choice',
            answers: question.answers,
          })),
      },
    })
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>
