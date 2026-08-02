<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const props = defineProps<{
  courseId: number
  enrolled?: boolean
}>()

interface QaReply {
  id: number
  content: string
  created_at?: string
  user?: { id: number, name: string, avatar?: string | null } | null
}
interface QaItem {
  id: number
  subject: string
  content: string
  created_at?: string
  user?: { id: number, name: string, avatar?: string | null } | null
  replies?: QaReply[]
}

const { t, locale } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const loading = ref(false)
const posting = ref(false)
const items = ref<QaItem[]>([])
const openId = ref<number | null>(null)
const replyDraft = reactive<Record<number, string>>({})
const form = reactive({ subject: '', content: '' })

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function fmt(value?: string) {
  if (!value) return ''
  return new Intl.DateTimeFormat(numberLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

async function load() {
  if (!props.courseId) return
  loading.value = true
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/qas`)
    items.value = res.data || res || []
  }
  catch {
    items.value = []
  }
  finally {
    loading.value = false
  }
}

async function submitQa() {
  if (!auth.isAuthenticated) {
    toast.add({ severity: 'warn', summary: t('student.qa.loginRequired'), life: 2500 })
    return
  }
  if (!props.enrolled) {
    toast.add({ severity: 'warn', summary: t('student.qa.enrollRequired'), life: 2500 })
    return
  }
  if (!form.subject.trim() || !form.content.trim()) {
    toast.add({ severity: 'warn', summary: t('student.qa.required'), life: 2500 })
    return
  }
  posting.value = true
  try {
    await useApi(`/courses/${props.courseId}/qas`, {
      method: 'POST',
      body: { subject: form.subject.trim(), content: form.content.trim() },
    })
    form.subject = ''
    form.content = ''
    toast.add({ severity: 'success', summary: t('student.qa.posted'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.qa.postError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    posting.value = false
  }
}

async function submitReply(qa: QaItem) {
  const content = (replyDraft[qa.id] || '').trim()
  if (!content) return
  try {
    await useApi(`/courses/${props.courseId}/qas/${qa.id}/replies`, {
      method: 'POST',
      body: { content },
    })
    replyDraft[qa.id] = ''
    toast.add({ severity: 'success', summary: t('student.qa.replied'), life: 2000 })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.qa.replyError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
}

watch(() => props.courseId, load, { immediate: true })
</script>

<template>
  <section class="qa">
    <header>
      <h2>{{ t('student.qa.title') }}</h2>
      <p>{{ t('student.qa.subtitle') }}</p>
    </header>

    <div v-if="enrolled" class="composer">
      <InputText v-model="form.subject" :placeholder="t('student.qa.subjectPh')" class="w-full" />
      <Textarea v-model="form.content" :placeholder="t('student.qa.contentPh')" rows="3" class="w-full" />
      <Button :label="t('student.qa.submit')" icon="pi pi-send" :loading="posting" @click="submitQa" />
    </div>
    <p v-else class="hint">{{ t('student.qa.enrollRequired') }}</p>

    <div v-if="loading" class="empty">{{ t('common.loading') }}</div>
    <div v-else-if="!items.length" class="empty">{{ t('student.qa.empty') }}</div>
    <div v-else class="list">
      <article v-for="qa in items" :key="qa.id" class="item">
        <button type="button" class="item-head" @click="openId = openId === qa.id ? null : qa.id">
          <div>
            <strong>{{ qa.subject }}</strong>
            <small>{{ qa.user?.name }} · {{ fmt(qa.created_at) }}</small>
          </div>
          <i :class="openId === qa.id ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" />
        </button>
        <div v-if="openId === qa.id" class="item-body">
          <p>{{ qa.content }}</p>
          <div v-for="reply in qa.replies || []" :key="reply.id" class="reply">
            <strong>{{ reply.user?.name }}</strong>
            <span>{{ reply.content }}</span>
            <small>{{ fmt(reply.created_at) }}</small>
          </div>
          <div v-if="enrolled" class="reply-form">
            <InputText v-model="replyDraft[qa.id]" :placeholder="t('student.qa.replyPh')" class="w-full" />
            <Button :label="t('student.qa.reply')" size="small" @click="submitReply(qa)" />
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.qa { display: flex; flex-direction: column; gap: .85rem; margin-top: 1.25rem; }
header h2 { margin: 0; font-size: 1.15rem; }
header p { margin: .2rem 0 0; color: var(--p-text-muted-color); }
.composer, .reply-form { display: flex; flex-direction: column; gap: .55rem; }
.hint, .empty { color: var(--p-text-muted-color); }
.list { display: flex; flex-direction: column; gap: .55rem; }
.item { border: 1px solid var(--p-content-border-color); border-radius: 10px; overflow: hidden; }
.item-head { width: 100%; display: flex; justify-content: space-between; gap: .75rem; align-items: center; padding: .75rem .9rem; background: transparent; border: 0; text-align: left; cursor: pointer; color: inherit; }
.item-head small { display: block; color: var(--p-text-muted-color); margin-top: .2rem; }
.item-body { padding: 0 .9rem .9rem; display: flex; flex-direction: column; gap: .65rem; }
.reply { display: flex; flex-direction: column; gap: .15rem; padding: .55rem .65rem; border-radius: 8px; background: var(--p-surface-50); }
.reply small { color: var(--p-text-muted-color); }
</style>
