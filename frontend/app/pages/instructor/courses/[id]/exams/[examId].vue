<template>
  <NuxtLayout name="instructor">
    <div class="mx-auto max-w-6xl px-4 py-8">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <NuxtLink :to="`/instructor/courses/${courseId}/exams`" class="text-sm text-on-surface-variant hover:text-primary">← Quay lại danh sách kỳ thi</NuxtLink>
          <h1 class="mt-2 text-2xl font-bold text-on-surface">Quiz cho kỳ thi độc lập</h1>
          <p class="mt-1 text-sm text-on-surface-variant">{{ exam?.title }}</p>
        </div>
        <UiButton @click="saveQuiz" :loading="saving">Lưu Quiz</UiButton>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <UiCard class="space-y-5">
          <input v-model="quiz.title" type="text" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Tên quiz cho kỳ thi">
          <textarea v-model="quiz.description" rows="4" class="w-full rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Mô tả / hướng dẫn"></textarea>
          <div class="grid grid-cols-2 gap-4">
            <input v-model.number="quiz.time_limit" type="number" min="0" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Phút">
            <input v-model.number="quiz.pass_score" type="number" min="0" max="100" class="rounded-xl border border-surface-dim/40 bg-surface-low px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Điểm đạt">
          </div>

          <div class="rounded-2xl border border-surface-dim/40 bg-surface-low p-4">
            <h3 class="font-bold text-on-surface">Câu hỏi đã gắn</h3>
            <div class="mt-3 space-y-3">
              <div v-for="question in selectedQuestions" :key="question.id" class="rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-on-surface">{{ question.content }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ question.group?.name || 'Không nhóm' }} · {{ question.answers?.length || 0 }} đáp án</p>
                  </div>
                  <button class="text-xs font-bold text-error" @click="removeQuestion(question.id)">Gỡ</button>
                </div>
              </div>
              <p v-if="selectedQuestions.length === 0" class="text-sm text-on-surface-variant">Chưa gắn câu hỏi nào vào quiz.</p>
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
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const examId = Number(route.params.examId)
const exam = ref<any>(null)
const banks = ref<any[]>([])
const bankQuestions = ref<any[]>([])
const selectedBankId = ref<number | null>(null)
const selectedQuestions = ref<any[]>([])
const selectedQuestionIds = computed(() => selectedQuestions.value.map((question) => question.id))
const saving = ref(false)
const quiz = reactive({
  title: '',
  description: '',
  time_limit: 60,
  pass_score: 80,
})

function headers() {
  return { Authorization: `Bearer ${auth.token}` }
}

async function loadBank() {
  if (!selectedBankId.value) {
    bankQuestions.value = []
    return
  }
  const bank = await $fetch<any>(`/api/courses/${courseId}/question-banks/${selectedBankId.value}`, { headers: headers() })
  bankQuestions.value = bank.questions || []
}

async function loadData() {
  exam.value = await $fetch<any>(`/api/courses/${courseId}/exams/${examId}`, { headers: headers() })
  const bankRes = await $fetch<any>(`/api/courses/${courseId}/question-banks`, { headers: headers() })
  banks.value = bankRes.banks || []
  try {
    const res = await $fetch<any>(`/api/courses/${courseId}/exams/${examId}/quiz`, { headers: headers() })
    quiz.title = res.quiz.title
    quiz.description = res.quiz.description || ''
    quiz.time_limit = res.quiz.time_limit || exam.value.duration || 60
    quiz.pass_score = res.quiz.pass_score || exam.value.pass_score || 80
    selectedQuestions.value = res.questions || []
  } catch {
    quiz.title = exam.value.title
    quiz.description = exam.value.description || ''
    quiz.time_limit = exam.value.duration || 60
    quiz.pass_score = exam.value.pass_score || 80
    selectedQuestions.value = []
  }
}

function toggleQuestion(question: any) {
  const exists = selectedQuestions.value.find((item) => item.id === question.id)
  if (exists) {
    selectedQuestions.value = selectedQuestions.value.filter((item) => item.id !== question.id)
  } else {
    selectedQuestions.value = [...selectedQuestions.value, question]
  }
}

function removeQuestion(id: number) {
  selectedQuestions.value = selectedQuestions.value.filter((item) => item.id !== id)
}

async function saveQuiz() {
  saving.value = true
  try {
    await $fetch(`/api/courses/${courseId}/exams/${examId}/quiz`, {
      method: 'POST',
      headers: headers(),
      body: {
        ...quiz,
        question_ids: selectedQuestionIds.value,
      },
    })
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>
