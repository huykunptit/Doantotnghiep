<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

const { t } = useI18n()
const toast = useToast()

const sending = ref(false)
const testing = ref(false)
const myNotifs = ref<{ id: number, title?: string, message?: string, created_at?: string, read_at?: string | null }[]>([])
const form = reactive({
  title: '',
  message: '',
  link: '/student/notifications',
  audience: 'all_students',
  role: 'student',
  administrative_class_id: null as number | null,
  send_email: false,
})

const audienceOptions = computed(() => [
  { label: t('admin.notifications.audStudents'), value: 'all_students' },
  { label: t('admin.notifications.audInstructors'), value: 'all_instructors' },
  { label: t('admin.notifications.audAll'), value: 'all_users' },
  { label: t('admin.notifications.audClass'), value: 'admin_class' },
])

const classes = ref<{ id: number, name: string }[]>([])
const classOptions = computed(() =>
  classes.value.map(c => ({ label: c.name, value: c.id })),
)

async function loadClasses() {
  try {
    const res = await useApi<{ data?: { id: number, name: string }[] } | { id: number, name: string }[]>(
      '/admin/academic/administrative-classes',
      { query: { per_page: 200 } },
    )
    const rows = Array.isArray(res) ? res : (res as { data?: { id: number, name: string }[] }).data || []
    classes.value = rows.map((r: any) => ({ id: r.id, name: r.name || r.code || `#${r.id}` }))
  }
  catch {
    classes.value = []
  }
}

async function loadMyInbox() {
  try {
    const res = await useApi<{ data?: typeof myNotifs.value }>('/notifications', { query: { per_page: 8 } })
    myNotifs.value = res.data || []
  }
  catch {
    myNotifs.value = []
  }
}

async function send() {
  if (!form.title.trim() || !form.message.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.notifications.required'), life: 2500 })
    return
  }
  if (form.audience === 'admin_class' && !form.administrative_class_id) {
    toast.add({ severity: 'warn', summary: t('admin.notifications.pickClass'), life: 2500 })
    return
  }

  sending.value = true
  try {
    const res = await useApi<{ sent?: number, smtp_configured?: boolean, message?: string }>(
      '/admin/notifications/broadcast',
      {
        method: 'POST',
        body: {
          title: form.title.trim(),
          message: form.message.trim(),
          link: form.link || null,
          audience: form.audience,
          administrative_class_id: form.administrative_class_id,
          send_email: form.send_email,
        },
      },
    )
    toast.add({
      severity: 'success',
      summary: t('admin.notifications.sent', { n: res.sent ?? 0 }),
      detail: form.send_email
        ? (res.smtp_configured ? t('admin.notifications.emailOn') : t('admin.notifications.emailNoSmtp'))
        : t('admin.notifications.inAppOnly'),
      life: 4000,
    })
    form.title = ''
    form.message = ''
  }
  catch (e: any) {
    toast.add({
      severity: 'error',
      summary: e?.data?.message || t('admin.notifications.sendError'),
      life: 3500,
    })
  }
  finally {
    sending.value = false
  }
}

async function testEmail() {
  testing.value = true
  try {
    const res = await useApi<{ message?: string }>('/admin/notifications/test-email', { method: 'POST' })
    toast.add({ severity: 'success', summary: res.message || t('admin.notifications.testOk'), life: 4000 })
  }
  catch (e: any) {
    toast.add({
      severity: 'error',
      summary: e?.data?.message || t('admin.notifications.testFail'),
      life: 5000,
    })
  }
  finally {
    testing.value = false
  }
}

onMounted(() => {
  loadClasses()
  loadMyInbox()
})
</script>

<template>
  <div class="page-stack">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.notifications.eyebrow') }}</span>
        <h1>{{ t('admin.notifications.title') }}</h1>
        <p>{{ t('admin.notifications.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button
          :label="t('admin.notifications.testEmail')"
          severity="secondary"
          outlined
          :loading="testing"
          @click="testEmail"
        />
        <NuxtLink to="/admin/settings">
          <Button :label="t('admin.notifications.openSmtp')" severity="secondary" text />
        </NuxtLink>
      </div>
    </header>

    <section v-if="myNotifs.length" class="panel inbox">
      <h3>{{ t('student.notif.title') }}</h3>
      <ul>
        <li v-for="n in myNotifs" :key="n.id" :class="{ unread: !n.read_at }">
          <strong>{{ n.title }}</strong>
          <span>{{ n.message }}</span>
        </li>
      </ul>
    </section>

    <section class="panel">
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.notifications.fieldTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.notifications.fieldMessage') }}</span>
          <Textarea v-model="form.message" rows="5" class="w-full" auto-resize />
        </label>
        <label class="field">
          <span>{{ t('admin.notifications.fieldLink') }}</span>
          <InputText v-model="form.link" class="w-full" placeholder="/student/notifications" />
        </label>
        <label class="field">
          <span>{{ t('admin.notifications.audience') }}</span>
          <Select
            v-model="form.audience"
            :options="audienceOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label v-if="form.audience === 'admin_class'" class="field full">
          <span>{{ t('admin.notifications.adminClass') }}</span>
          <Select
            v-model="form.administrative_class_id"
            :options="classOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            :placeholder="t('admin.notifications.pickClass')"
          />
        </label>
        <label class="field full email-row">
          <Checkbox v-model="form.send_email" binary input-id="send-email" />
          <label for="send-email">{{ t('admin.notifications.sendEmail') }}</label>
        </label>
      </div>
      <div class="actions">
        <Button :label="t('admin.notifications.send')" icon="pi pi-send" :loading="sending" @click="send" />
      </div>
      <p class="hint">{{ t('admin.notifications.realtimeNote') }}</p>
    </section>
  </div>
</template>

<style scoped>
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
  margin-bottom: 14px;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  padding: 16px;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.email-row { flex-direction: row; align-items: center; gap: 10px; }
.actions { margin-top: 16px; }
.hint { margin: 14px 0 0; color: var(--text-muted); font-size: .84rem; }
.inbox h3 { margin: 0 0 10px; font-size: 1rem; }
.inbox ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.inbox li { display: grid; gap: 2px; padding: 10px; border-radius: 10px; border: 1px solid var(--border); }
.inbox li.unread { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.inbox li span { color: var(--text-muted); font-size: .85rem; }
@media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
</style>
