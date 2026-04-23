<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Kỳ thi độc lập" :description="`Quản lý quiz độc lập ngoài lesson cho khóa học #${courseId}`">
        <template #actions>
          <UiButton :to="`/instructor/courses/${courseId}/question-bank`" variant="secondary">Question Bank</UiButton>
          <UiButton :to="`/instructor/courses/${courseId}/curriculum`" variant="secondary">Curriculum</UiButton>
        </template>
      </AppPageHeader>

      <UiCard class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
          <div class="space-y-4">
            <div v-for="exam in exams" :key="exam.id" class="rounded-[1.5rem] border border-surface-dim/40 bg-surface-low p-5">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="rounded-lg bg-primary/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-primary">{{ exam.status || 'draft' }}</span>
                    <span class="rounded-lg bg-surface-high px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Pass {{ exam.pass_score || 80 }}%</span>
                  </div>
                  <h3 class="mt-3 font-headline text-xl font-bold text-on-surface">{{ exam.title }}</h3>
                  <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ exam.description || 'Chưa có mô tả cho kỳ thi này.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <UiButton :to="`/instructor/courses/${courseId}/exams/${exam.id}`" size="sm">Quản lý Quiz</UiButton>
                  <UiButton size="sm" variant="secondary" @click="openEdit(exam)">Sửa meta</UiButton>
                  <UiButton size="sm" variant="danger" @click="removeExam(exam)">Xóa</UiButton>
                </div>
              </div>
            </div>

            <UiEmptyState v-if="!loading && exams.length === 0" title="Chưa có kỳ thi độc lập" description="Tạo kỳ thi riêng để dùng quiz ngoài lesson." />
          </div>

          <div class="rounded-[1.5rem] border border-surface-dim/40 bg-surface-low p-5">
            <h3 class="font-headline text-xl font-bold text-on-surface">{{ editingExam ? 'Cập nhật kỳ thi' : 'Tạo kỳ thi mới' }}</h3>
            <div class="mt-4 space-y-4">
              <input v-model="form.title" type="text" class="w-full rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Tên kỳ thi">
              <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Mô tả"></textarea>
              <select v-model="form.status" class="w-full rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary">
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
                <option value="closed">Closed</option>
              </select>
              <div class="grid grid-cols-2 gap-3">
                <input v-model.number="form.duration" type="number" min="0" class="rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Phút">
                <input v-model.number="form.pass_score" type="number" min="0" max="100" class="rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary" placeholder="Điểm đạt">
              </div>
              <div class="flex gap-3">
                <UiButton block @click="saveExam">{{ editingExam ? 'Lưu thay đổi' : 'Tạo kỳ thi' }}</UiButton>
                <UiButton v-if="editingExam" block variant="secondary" @click="resetForm">Hủy</UiButton>
              </div>
            </div>
          </div>
        </div>
      </UiCard>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = route.params.id
const loading = ref(true)
const exams = ref<any[]>([])
const editingExam = ref<any | null>(null)
const form = reactive({
  title: '',
  description: '',
  status: 'draft',
  duration: 60,
  pass_score: 80,
})

function headers() {
  return { Authorization: `Bearer ${auth.token}` }
}

async function loadExams() {
  loading.value = true
  try {
    exams.value = await $fetch<any[]>(`/api/courses/${courseId}/exams`, { headers: headers() })
  } finally {
    loading.value = false
  }
}

function openEdit(exam: any) {
  editingExam.value = exam
  form.title = exam.title
  form.description = exam.description || ''
  form.status = exam.status || 'draft'
  form.duration = exam.duration || 60
  form.pass_score = exam.pass_score || 80
}

function resetForm() {
  editingExam.value = null
  form.title = ''
  form.description = ''
  form.status = 'draft'
  form.duration = 60
  form.pass_score = 80
}

async function saveExam() {
  if (!form.title.trim()) return
  if (editingExam.value) {
    await $fetch(`/api/courses/${courseId}/exams/${editingExam.value.id}`, { method: 'PUT', headers: headers(), body: form })
  } else {
    await $fetch(`/api/courses/${courseId}/exams`, { method: 'POST', headers: headers(), body: form })
  }
  resetForm()
  await loadExams()
}

async function removeExam(exam: any) {
  if (!confirm(`Xóa kỳ thi "${exam.title}"?`)) return
  await $fetch(`/api/courses/${courseId}/exams/${exam.id}`, { method: 'DELETE', headers: headers() })
  await loadExams()
}

onMounted(loadExams)
</script>
