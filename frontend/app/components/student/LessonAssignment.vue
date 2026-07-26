<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const props = defineProps<{ courseId: number, lessonId: number }>()
const emit = defineEmits<{ completed: [] }>()

const { t } = useI18n()
const toast = useToast()

interface Assignment {
  id: number
  instructions?: string | null
  max_file_size?: number
  allowed_extensions?: string | null
  due_at?: string | null
}

const loading = ref(true)
const submitting = ref(false)
const assignment = ref<Assignment | null>(null)
const file = ref<File | null>(null)
const note = ref('')
const submitted = ref(false)

async function load() {
  loading.value = true
  try {
    assignment.value = await useApi<Assignment>(
      `/courses/${props.courseId}/lessons/${props.lessonId}/assignment`,
    )
  }
  catch {
    assignment.value = null
  }
  finally {
    loading.value = false
  }
}

async function submit() {
  if (!file.value) {
    toast.add({ severity: 'warn', summary: t('student.learn.assignmentNeedFile'), life: 2500 })
    return
  }
  submitting.value = true
  try {
    const form = new FormData()
    form.append('file', file.value)
    if (note.value.trim()) form.append('student_note', note.value.trim())
    await useApi(`/courses/${props.courseId}/lessons/${props.lessonId}/assignment/submit`, {
      method: 'POST',
      body: form,
    })
    submitted.value = true
    toast.add({ severity: 'success', summary: t('student.learn.assignmentSubmitted'), life: 2200 })
    emit('completed')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.learn.assignmentError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    submitting.value = false
  }
}

watch(() => [props.courseId, props.lessonId], load, { immediate: true })
</script>

<template>
  <div class="asg">
    <div v-if="loading" class="muted">…</div>
    <template v-else-if="assignment">
      <h3>{{ t('student.learn.assignmentTitle') }}</h3>
      <div class="instructions" v-html="assignment.instructions || ''" />
      <p v-if="assignment.due_at" class="meta">
        {{ t('student.learn.assignmentDue') }}: {{ new Date(assignment.due_at).toLocaleString() }}
      </p>
      <p v-if="assignment.allowed_extensions" class="meta">
        {{ t('student.learn.assignmentExt') }}: {{ assignment.allowed_extensions }}
      </p>

      <div v-if="submitted" class="ok">
        <i class="pi pi-check-circle" /> {{ t('student.learn.assignmentSubmitted') }}
      </div>
      <template v-else>
        <CommonFileDropzone
          v-model="file"
          :label="t('student.learn.assignmentUpload')"
          :hint="assignment.allowed_extensions || 'PDF, DOC, ZIP…'"
          :max-size-mb="Math.max(1, Math.round((assignment.max_file_size || 10240) / 1024))"
          icon="pi pi-upload"
        />
        <label class="field">
          <span>{{ t('student.learn.assignmentNote') }}</span>
          <CommonRichTextEditor v-model="note" height="160px" />
        </label>
        <Button
          :label="t('student.learn.assignmentSubmit')"
          icon="pi pi-send"
          :loading="submitting"
          @click="submit"
        />
      </template>
    </template>
    <p v-else class="muted">{{ t('student.learn.assignmentEmpty') }}</p>
  </div>
</template>

<style scoped>
.asg { display: grid; gap: 12px; }
.instructions {
  padding: 14px; border: 1px solid var(--border); border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 94%, transparent); line-height: 1.55; font-weight: 500;
}
.meta { margin: 0; color: var(--text-muted); font-size: .88rem; font-weight: 600; }
.field { display: grid; gap: 6px; font-weight: 650; }
.ok {
  display: flex; gap: 8px; align-items: center; padding: 12px 14px; border-radius: 12px;
  background: var(--brand-soft); color: var(--brand); font-weight: 700;
}
.muted { color: var(--text-muted); }
</style>
