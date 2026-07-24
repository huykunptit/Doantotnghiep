<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CourseItem { id: number, title: string }
interface Message {
  role: 'user' | 'assistant'
  content: string
  time: string
}

const { t, locale } = useI18n()
const toast = useToast()

const messages = ref<Message[]>([])
const input = ref('')
const loading = ref(false)
const messagesEl = ref<HTMLElement | null>(null)
const courseId = ref<number | null>(null)
const courses = ref<CourseItem[]>([])
const coursesLoading = ref(true)

const suggestions = computed(() => [
  t('admin.chat.s1'),
  t('admin.chat.s2'),
  t('admin.chat.s3'),
  t('admin.chat.s4'),
])

function now() {
  return new Date().toLocaleTimeString(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function loadCourses() {
  coursesLoading.value = true
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=50')
    courses.value = res.data || []
  }
  catch {
    courses.value = []
  }
  finally {
    coursesLoading.value = false
  }
}

async function scrollDown() {
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
}

async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return
  input.value = ''
  messages.value.push({ role: 'user', content: text, time: now() })
  await scrollDown()
  loading.value = true
  try {
    const history = messages.value
      .slice(0, -1)
      .slice(-10)
      .map(m => ({ role: m.role, content: m.content }))

    const res = await useApi<{ reply?: string, message?: string }>('/ai/chat', {
      method: 'POST',
      body: {
        message: text,
        course_id: courseId.value || undefined,
        history,
      },
    })
    messages.value.push({
      role: 'assistant',
      content: res.reply || res.message || t('admin.chat.fallbackReply'),
      time: now(),
    })
  }
  catch (error: any) {
    messages.value.push({
      role: 'assistant',
      content: t('admin.chat.errorReply'),
      time: now(),
    })
    toast.add({
      severity: 'error',
      summary: t('admin.chat.sendError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
    await scrollDown()
  }
}

function clearChat() {
  messages.value = []
}

function useSuggestion(text: string) {
  input.value = text
}

onMounted(loadCourses)
</script>

<template>
  <div class="page chat-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.system') }}</span>
        <h1>{{ t('admin.chat.title') }}</h1>
        <p>{{ t('admin.chat.subtitle') }}</p>
      </div>
      <Button
        :label="t('admin.chat.clear')"
        icon="pi pi-trash"
        severity="secondary"
        outlined
        :disabled="!messages.length"
        @click="clearChat"
      />
    </header>

    <div class="chat-layout">
      <aside class="sidebar">
        <section class="panel">
          <h2>{{ t('admin.chat.context') }}</h2>
          <p class="panel-hint">{{ t('admin.chat.contextHint') }}</p>
          <Select
            v-model="courseId"
            :options="courses"
            option-label="title"
            option-value="id"
            :placeholder="t('admin.chat.noCourse')"
            show-clear
            filter
            fluid
            :loading="coursesLoading"
          />
        </section>
        <section class="panel">
          <h2>{{ t('admin.chat.suggestions') }}</h2>
          <div class="suggestions">
            <button
              v-for="item in suggestions"
              :key="item"
              type="button"
              class="suggestion"
              @click="useSuggestion(item)"
            >
              {{ item }}
            </button>
          </div>
        </section>
      </aside>

      <section class="panel chat-card">
        <div ref="messagesEl" class="messages">
          <div v-if="!messages.length" class="empty-state">
            <i class="pi pi-comments" />
            <h2>{{ t('admin.chat.emptyTitle') }}</h2>
            <p>{{ t('admin.chat.emptyHint') }}</p>
          </div>
          <template v-else>
            <div
              v-for="(message, index) in messages"
              :key="index"
              class="message-row"
              :class="{ user: message.role === 'user' }"
            >
              <span class="avatar">
                <i class="pi" :class="message.role === 'assistant' ? 'pi-sparkles' : 'pi-user'" />
              </span>
              <div class="bubble">
                <p>{{ message.content }}</p>
                <small>{{ message.time }}</small>
              </div>
            </div>
            <div v-if="loading" class="message-row">
              <span class="avatar"><i class="pi pi-sparkles" /></span>
              <div class="bubble typing">
                <i class="pi pi-spin pi-spinner" />
                {{ t('admin.chat.typing') }}
              </div>
            </div>
          </template>
        </div>

        <div class="composer">
          <Textarea
            v-model="input"
            auto-resize
            rows="2"
            :placeholder="t('admin.chat.inputPh')"
            :disabled="loading"
            @keydown.enter.exact.prevent="send"
          />
          <Button
            icon="pi pi-send"
            :aria-label="t('admin.chat.send')"
            :disabled="!input.trim() || loading"
            :loading="loading"
            @click="send"
          />
        </div>
        <small class="hint">{{ t('admin.chat.hint') }}</small>
      </section>
    </div>
  </div>
</template>

<style scoped>
.chat-page { gap: 14px; min-height: calc(100vh - 8rem); display: flex; flex-direction: column; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.chat-layout {
  display: grid; grid-template-columns: 18rem minmax(0, 1fr); gap: 12px; min-height: 0; flex: 1;
}
.sidebar { display: flex; flex-direction: column; gap: 12px; }
.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 14px;
}
.panel h2 { margin: 0 0 6px; font-size: .95rem; }
.panel-hint { margin: 0 0 10px; color: var(--text-muted); font-size: .78rem; font-weight: 500; }

.suggestions { display: flex; flex-direction: column; gap: 6px; }
.suggestion {
  border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px;
  background: var(--surface-subtle); color: var(--text); font: inherit; font-size: .78rem;
  font-weight: 600; text-align: left; cursor: pointer;
}
.suggestion:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); color: var(--brand); }

.chat-card { display: flex; flex-direction: column; min-height: 0; padding: 0; overflow: hidden; }
.messages {
  display: flex; flex: 1; min-height: 22rem; flex-direction: column; gap: 12px;
  overflow-y: auto; padding: 16px;
}
.empty-state {
  display: grid; place-items: center; text-align: center; margin: auto; max-width: 28rem;
  color: var(--text-muted);
}
.empty-state i { font-size: 2rem; color: var(--brand); }
.empty-state h2 { margin: 12px 0 4px; color: var(--text); font-size: 1rem; }
.empty-state p { margin: 0; font-size: .84rem; }

.message-row { display: flex; max-width: 82%; align-items: flex-end; gap: 8px; }
.message-row.user { align-self: flex-end; flex-direction: row-reverse; }
.avatar {
  display: grid; place-items: center; width: 2rem; height: 2rem; flex: 0 0 auto;
  border-radius: 50%; background: var(--brand-soft); color: var(--brand);
}
.bubble {
  padding: 10px 12px; border: 1px solid var(--border); border-radius: 14px 14px 14px 4px;
  background: var(--surface-subtle);
}
.user .bubble {
  border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
  border-radius: 14px 14px 4px 14px; background: var(--brand); color: #fff;
}
.bubble p { margin: 0; white-space: pre-wrap; font-size: .88rem; line-height: 1.55; font-weight: 500; }
.bubble small { display: block; margin-top: 4px; text-align: right; opacity: .7; font-size: .7rem; }
.typing { font-size: .82rem; display: flex; align-items: center; gap: 8px; }

.composer {
  display: flex; align-items: flex-end; gap: 10px; padding: 12px;
  border-top: 1px solid var(--border); background: var(--surface-subtle);
}
.composer :deep(.p-textarea) {
  min-height: 2.75rem; max-height: 8rem; flex: 1; resize: none;
}
.hint {
  display: block; padding: 0 12px 12px; text-align: center;
  color: var(--text-muted); font-size: .74rem;
}

@media (max-width: 900px) {
  .chat-layout { grid-template-columns: 1fr; }
  .message-row { max-width: 94%; }
}
</style>
