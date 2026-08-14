<script setup lang="ts">
const props = defineProps<{ courseId?: number | null }>()

const auth = useAuthStore()
const { t } = useI18n()

const isOpen = ref(false)
const loading = ref(false)
const input = ref('')
const messageBox = ref<HTMLElement | null>(null)
const chatInput = ref<HTMLInputElement | null>(null)

interface RagSource { source?: string, subject?: string | null, score?: number | null }
interface ChatMsg {
  role: 'user' | 'assistant'
  text: string
  sources?: RagSource[]
  ragUsed?: boolean
}

const welcome = computed(() =>
  props.courseId
    ? t('student.ai.chatWelcomeCourse')
    : t('student.ai.chatWelcome'),
)

const messages = ref<ChatMsg[]>([{ role: 'assistant', text: welcome.value }])
/** true khi đã nhận token đầu tiên của câu trả lời hiện tại — ẩn "typing dots" khi text đang chảy dần. */
const streaming = ref(false)

const quickQuestions = computed(() =>
  props.courseId
    ? [t('student.ai.qCourse1'), t('student.ai.qCourse2'), t('student.ai.qCourse3')]
    : [t('student.ai.q1'), t('student.ai.q2'), t('student.ai.q3')],
)

watch(() => props.courseId, () => {
  messages.value = [{ role: 'assistant', text: welcome.value }]
})

function openChat() {
  isOpen.value = true
  nextTick(() => chatInput.value?.focus())
}

function clearChat() {
  messages.value = [{ role: 'assistant', text: t('student.ai.chatCleared') }]
}

function sendQuick(question: string) {
  input.value = question
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isAuthenticated) {
    messages.value.push({ role: 'assistant', text: t('student.ai.chatNeedLogin') })
    input.value = ''
    scrollToBottom()
    return
  }

  messages.value.push({ role: 'user', text })
  input.value = ''
  loading.value = true
  streaming.value = false
  scrollToBottom()

  const history = messages.value
    .slice(1)
    .slice(-10)
    .map(m => ({ role: m.role, content: m.text }))

  let streamingIndex = -1

  await useApiStream('/ai/chat/stream', {
    body: {
      message: text,
      course_id: props.courseId || undefined,
      history,
      use_rag: true,
    },
    onDelta: (delta) => {
      if (streamingIndex === -1) {
        messages.value.push({ role: 'assistant', text: '' })
        streamingIndex = messages.value.length - 1
        streaming.value = true
      }
      const msg = messages.value[streamingIndex]
      if (msg) msg.text += delta
      scrollToBottom()
    },
    onDone: (data) => {
      if (streamingIndex === -1) {
        messages.value.push({ role: 'assistant', text: t('student.ai.chatEmptyReply') })
        streamingIndex = messages.value.length - 1
      }
      const msg = messages.value[streamingIndex]
      if (msg) {
        msg.ragUsed = !!data.rag_used
        msg.sources = data.sources || []
      }
    },
    onError: () => {},
  })

  if (streamingIndex === -1) {
    try {
      const res = await useApi<{ reply?: string, rag_used?: boolean, sources?: RagSource[] }>('/ai/chat', {
        method: 'POST',
        timeout: 35000,
        body: {
          message: text,
          course_id: props.courseId || undefined,
          history,
          use_rag: true,
        },
      })
      const reply = (res.reply || '').trim()
      messages.value.push({
        role: 'assistant',
        text: reply || t('student.ai.chatEmptyReply'),
        ragUsed: !!res.rag_used,
        sources: res.sources || [],
      })
    }
    catch {
      messages.value.push({
        role: 'assistant',
        text: t('student.ai.chatError'),
      })
    }
  }

  loading.value = false
  streaming.value = false
  scrollToBottom()
}

function scrollToBottom() {
  nextTick(() => {
    messageBox.value?.scrollTo({ top: messageBox.value.scrollHeight, behavior: 'smooth' })
  })
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape' && isOpen.value) isOpen.value = false
}

onMounted(() => {
  if (import.meta.client) document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  if (import.meta.client) document.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div class="cb">
    <Transition name="cb-fade">
      <button v-if="!isOpen" type="button" class="fab" :title="t('student.ai.chatTitle')" @click="openChat">
        <img src="/images/chatbot-icon.png" alt="" class="fab-img">
      </button>
    </Transition>

    <Transition name="cb-slide">
      <div v-if="isOpen" class="window">
        <header class="head">
          <div class="head-left">
            <span class="avatar">
              <img src="/images/chatbot-icon.png" alt="" class="avatar-img">
            </span>
            <div>
              <strong>{{ t('student.ai.chatTitle') }}</strong>
              <span>{{ courseId ? t('student.ai.chatContextOn') : t('student.ai.chatStatus') }}</span>
            </div>
          </div>
          <div class="actions">
            <button type="button" :title="t('student.ai.chatClear')" @click="clearChat"><i class="pi pi-replay" /></button>
            <button type="button" :title="t('student.ai.chatClose')" @click="isOpen = false"><i class="pi pi-times" /></button>
          </div>
        </header>

        <div ref="messageBox" class="messages">
          <div
            v-for="(msg, idx) in messages"
            :key="idx"
            class="row"
            :class="msg.role === 'user' ? 'user' : 'bot'"
          >
            <div class="bubble">
              <p class="bubble-text">{{ msg.text }}</p>
              <div v-if="msg.ragUsed && msg.sources?.length" class="sources">
                <span class="sources-label">{{ t('student.ai.ragSources') }}</span>
                <ul>
                  <li v-for="(src, sIdx) in msg.sources" :key="`${src.source}-${sIdx}`">
                    {{ src.subject || src.source }}
                    <small v-if="src.source && src.subject">· {{ src.source }}</small>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div v-if="loading && !streaming" class="row bot">
            <div class="bubble typing">
              <span /><span /><span />
            </div>
          </div>
        </div>

        <div v-if="messages.length <= 1 && !loading" class="quick">
          <button v-for="q in quickQuestions" :key="q" type="button" @click="sendQuick(q)">{{ q }}</button>
        </div>

        <footer class="foot">
          <form @submit.prevent="sendMessage">
            <input
              ref="chatInput"
              v-model="input"
              type="text"
              :placeholder="t('student.ai.chatPlaceholder')"
            >
            <button type="submit" :disabled="!input.trim() || loading"><i class="pi pi-arrow-up" /></button>
          </form>
        </footer>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.cb { position: fixed; bottom: 22px; right: 22px; z-index: 9999; }
.fab {
  width: 64px; height: 64px; border: 0; border-radius: 0;
  padding: 0; overflow: visible; cursor: pointer;
  background: transparent;
  filter: drop-shadow(0 10px 18px rgba(0, 150, 180, .35));
  transition: transform .15s ease;
}
.fab:hover { transform: translateY(-2px) scale(1.05); }
.fab-img { width: 100%; height: 100%; object-fit: contain; display: block; }
.window {
  width: min(380px, calc(100vw - 28px));
  height: min(560px, calc(100dvh - 100px));
  display: flex; flex-direction: column;
  border: 1px solid var(--border); border-radius: 16px; overflow: hidden;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  backdrop-filter: blur(16px);
  box-shadow: 0 18px 48px -18px rgba(15, 23, 42, .35);
}
.head {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
  padding: 12px 14px; border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--brand-soft) 55%, var(--surface));
}
.head-left { display: flex; gap: 10px; align-items: center; min-width: 0; }
.avatar {
  width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center;
  background: transparent; overflow: hidden; flex-shrink: 0;
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.head strong { display: block; font-size: .92rem; }
.head span { color: var(--text-muted); font-size: .75rem; font-weight: 600; }
.actions { display: flex; gap: 2px; }
.actions button {
  width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
  color: var(--text-muted); cursor: pointer;
}
.actions button:hover { background: var(--surface-subtle); color: var(--text); }
.messages { flex: 1; overflow: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.row { display: flex; }
.row.user { justify-content: flex-end; }
.bubble {
  max-width: 88%; padding: 10px 12px; border-radius: 14px;
  font-size: .9rem; font-weight: 500; line-height: 1.45;
}
.bubble-text { margin: 0; white-space: pre-wrap; }
.sources {
  margin-top: 8px; padding-top: 8px; border-top: 1px dashed color-mix(in srgb, var(--border) 80%, transparent);
  font-size: .74rem;
}
.sources-label { display: block; font-weight: 700; color: var(--brand); margin-bottom: 4px; }
.sources ul { margin: 0; padding-left: 1.1rem; color: var(--text-muted); }
.sources small { opacity: .85; }
.bot .bubble {
  background: var(--surface-subtle); border: 1px solid var(--border); border-bottom-left-radius: 4px;
}
.user .bubble {
  background: var(--brand); color: #fff; border-bottom-right-radius: 4px;
}
.typing { display: flex; gap: 5px; align-items: center; min-height: 20px; }
.typing span {
  width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted);
  animation: blink 1s ease infinite;
}
.typing span:nth-child(2) { animation-delay: .15s; }
.typing span:nth-child(3) { animation-delay: .3s; }
@keyframes blink { 50% { opacity: .35; } }
.quick { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 14px 10px; }
.quick button {
  border: 1px solid var(--border); border-radius: 999px; padding: 6px 10px;
  background: var(--surface); color: var(--text); font: inherit; font-size: .78rem; font-weight: 650;
  cursor: pointer;
}
.quick button:hover { border-color: var(--brand); color: var(--brand); }
.foot { padding: 10px 14px 12px; border-top: 1px solid var(--border); }
.foot form { display: flex; gap: 8px; }
.foot input {
  flex: 1; min-width: 0; border: 1px solid var(--border); border-radius: 999px;
  padding: 10px 14px; background: var(--surface-subtle); color: var(--text); font: inherit;
}
.foot button {
  width: 40px; height: 40px; border: 0; border-radius: 50%;
  background: var(--brand); color: #fff; cursor: pointer;
}
.foot button:disabled { opacity: .45; cursor: not-allowed; }
.cb-fade-enter-active, .cb-fade-leave-active { transition: opacity .18s ease, transform .18s ease; }
.cb-fade-enter-from, .cb-fade-leave-to { opacity: 0; transform: translateY(8px); }
.cb-slide-enter-active, .cb-slide-leave-active { transition: opacity .2s ease, transform .2s ease; }
.cb-slide-enter-from, .cb-slide-leave-to { opacity: 0; transform: translateY(16px) scale(.98); }
</style>
