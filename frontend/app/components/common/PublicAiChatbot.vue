<script setup lang="ts">
const { t } = useI18n()

const isOpen = ref(false)
const loading = ref(false)
const input = ref('')
const messageBox = ref<HTMLElement | null>(null)
const chatInput = ref<HTMLInputElement | null>(null)

interface ChatMsg { role: 'user' | 'assistant', text: string }

const welcome = computed(() => t('public.ai.chatWelcome'))
const messages = ref<ChatMsg[]>([{ role: 'assistant', text: welcome.value }])
/** true khi đã nhận token đầu tiên của câu trả lời hiện tại — ẩn "typing dots" khi text đang chảy dần. */
const streaming = ref(false)
const quickQuestions = computed(() => [
  t('public.ai.q1'),
  t('public.ai.q2'),
  t('public.ai.q3'),
])

function openChat() {
  isOpen.value = true
  nextTick(() => chatInput.value?.focus())
}

function clearChat() {
  messages.value = [{ role: 'assistant', text: t('public.ai.chatCleared') }]
}

function sendQuick(question: string) {
  input.value = question
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  messages.value.push({ role: 'user', text })
  input.value = ''
  loading.value = true
  streaming.value = false
  scrollToBottom()

  const history = messages.value
    .slice(1)
    .slice(-8)
    .map(m => ({ role: m.role, content: m.text }))

  let streamingIndex = -1

  await useApiStream('/ai/chat/guest/stream', {
    token: null,
    body: { message: text, history },
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
    onDone: () => {
      if (streamingIndex === -1) {
        messages.value.push({ role: 'assistant', text: t('public.ai.chatEmptyReply') })
      }
    },
    onError: () => {},
  })

  if (streamingIndex === -1) {
    try {
      const res = await useApi<{ reply?: string }>('/ai/chat/guest', {
        method: 'POST',
        token: null,
        timeout: 25000,
        body: { message: text, history },
      })
      const reply = (res.reply || '').trim()
      messages.value.push({
        role: 'assistant',
        text: reply || t('public.ai.chatEmptyReply'),
      })
    }
    catch (error: any) {
      const status = Number(error?.statusCode || error?.response?.status || 0)
      messages.value.push({
        role: 'assistant',
        text: status === 429 ? t('public.ai.chatRateLimit') : t('public.ai.chatError'),
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
      <button v-if="!isOpen" type="button" class="fab" :title="t('public.ai.chatTitle')" @click="openChat">
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
              <strong>{{ t('public.ai.chatTitle') }}</strong>
            </div>
          </div>
          <div class="actions">
            <button type="button" :title="t('public.ai.chatClear')" @click="clearChat"><i class="pi pi-replay" /></button>
            <button type="button" :title="t('public.ai.chatClose')" @click="isOpen = false"><i class="pi pi-times" /></button>
          </div>
        </header>

        <div ref="messageBox" class="messages">
          <div
            v-for="(msg, idx) in messages"
            :key="idx"
            class="row"
            :class="msg.role === 'user' ? 'user' : 'bot'"
          >
            <div class="bubble">{{ msg.text }}</div>
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
              maxlength="500"
            >
            <button type="submit" :disabled="!input.trim() || loading"><i class="pi pi-arrow-up" /></button>
          </form>
          <p>{{ t('public.ai.chatDisclaimer') }}</p>
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
  filter: drop-shadow(0 10px 18px rgba(15, 118, 110, .35));
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
  background: color-mix(in srgb, var(--brand-soft, var(--brand)) 18%, var(--surface));
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
  font-size: .9rem; font-weight: 500; line-height: 1.45; white-space: pre-wrap;
}
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
.foot p { margin: 8px 0 0; color: var(--text-muted); font-size: .72rem; font-weight: 500; }
.cb-fade-enter-active, .cb-fade-leave-active { transition: opacity .18s ease, transform .18s ease; }
.cb-fade-enter-from, .cb-fade-leave-to { opacity: 0; transform: translateY(8px); }
.cb-slide-enter-active, .cb-slide-leave-active { transition: opacity .2s ease, transform .2s ease; }
.cb-slide-enter-from, .cb-slide-leave-to { opacity: 0; transform: translateY(16px) scale(.98); }
</style>
