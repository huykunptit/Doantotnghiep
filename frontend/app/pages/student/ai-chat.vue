<template>
  <div class="chat-page">
    <!-- Hero Header -->
    <header class="chat-hero">
      <div class="chat-hero-inner">
        <div class="chat-hero-left">
          <div class="chat-hero-avatar">
            <Bot :size="24" />
            <span class="chat-hero-pulse" />
          </div>
          <div>
            <h1 class="chat-hero-title">Trợ lý AI Sylva</h1>
            <p class="chat-hero-sub">
              <span class="chat-online-dot" />
              Đang hoạt động · Trả lời dựa trên tài liệu PTIT
            </p>
          </div>
        </div>
        <button class="chat-clear-btn" title="Xóa hội thoại" @click="clearChat">
          <RotateCcw :size="15" />
          Xóa hội thoại
        </button>
      </div>
    </header>

    <!-- Chat Body -->
    <div class="chat-body">
      <!-- Messages area -->
      <div ref="msgBox" class="chat-messages">
        <!-- Empty state -->
        <div v-if="messages.length === 0" class="chat-empty">
          <div class="chat-empty-icon">
            <Sparkles :size="32" />
          </div>
          <h2 class="chat-empty-title">Xin chào! Tôi có thể giúp gì cho bạn?</h2>
          <p class="chat-empty-lead">Hỏi tôi về bài học, tài liệu PTIT, lộ trình học tập hoặc bất cứ điều gì về hệ thống Sylva LMS.</p>

          <!-- Quick suggestions -->
          <div class="chat-suggestions">
            <button
              v-for="q in suggestions"
              :key="q.text"
              class="chat-suggestion"
              @click="sendQuick(q.text)"
            >
              <component :is="q.icon" :size="15" />
              <span>{{ q.text }}</span>
            </button>
          </div>
        </div>

        <!-- Message list -->
        <template v-else>
          <div
            v-for="(msg, idx) in messages"
            :key="idx"
            class="chat-msg-row"
            :class="msg.role === 'user' ? 'is-user' : 'is-bot'"
          >
            <!-- Bot avatar -->
            <div v-if="msg.role === 'assistant'" class="chat-msg-avatar">
              <Bot :size="16" />
            </div>

            <div class="chat-msg-wrap">
              <!-- Bubble -->
              <div class="chat-bubble" :class="msg.role === 'user' ? 'chat-bubble--user' : 'chat-bubble--bot'">
                <p class="chat-bubble-text">{{ msg.text }}</p>

                <!-- RAG Sources -->
                <div v-if="msg.sources && msg.sources.length > 0" class="chat-sources">
                  <div class="chat-sources-header">
                    <BookOpen :size="11" />
                    <span>Tài liệu tham khảo:</span>
                  </div>
                  <div class="chat-sources-list">
                    <span
                      v-for="(src, sIdx) in msg.sources"
                      :key="sIdx"
                      class="chat-source-tag"
                      :title="src.content_preview"
                    >
                      [{{ sIdx + 1 }}] {{ src.source_file }}
                      <em class="chat-source-pct">{{ Math.round(src.relevance_score) }}%</em>
                    </span>
                  </div>
                </div>
              </div>

              <!-- Timestamp -->
              <span class="chat-msg-time">{{ msg.time }}</span>
            </div>

            <!-- User avatar -->
            <div v-if="msg.role === 'user'" class="chat-msg-avatar chat-msg-avatar--user">
              {{ userInitial }}
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="chat-msg-row is-bot">
            <div class="chat-msg-avatar">
              <Bot :size="16" />
            </div>
            <div class="chat-bubble chat-bubble--bot chat-bubble--typing">
              <span class="chat-dot" style="animation-delay: 0ms" />
              <span class="chat-dot" style="animation-delay: 140ms" />
              <span class="chat-dot" style="animation-delay: 280ms" />
            </div>
          </div>
        </template>
      </div>

      <!-- Input Bar -->
      <div class="chat-input-bar">
        <!-- Quick suggestions when chat has messages -->
        <div v-if="messages.length > 0 && messages.length < 3 && !loading" class="chat-quick-chips">
          <button
            v-for="q in suggestions.slice(0, 3)"
            :key="q.text"
            class="chat-quick-chip"
            @click="sendQuick(q.text)"
          >
            {{ q.text }}
          </button>
        </div>

        <form class="chat-form" @submit.prevent="sendMessage">
          <textarea
            ref="inputRef"
            v-model="input"
            class="chat-textarea"
            placeholder="Nhập câu hỏi... (Enter để gửi, Shift+Enter xuống dòng)"
            rows="1"
            @keydown.enter.exact.prevent="sendMessage"
            @input="autoResize"
          />
          <button
            type="submit"
            class="chat-send-btn"
            :disabled="!input.trim() || loading"
          >
            <ArrowUp :size="18" />
          </button>
        </form>
        <p class="chat-disclaimer">AI có thể mắc lỗi — vui lòng kiểm tra lại thông tin quan trọng.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, reactive, ref, computed } from 'vue'
import { Bot, Sparkles, RotateCcw, ArrowUp, BookOpen, GraduationCap, BookMarked, Map, HelpCircle } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

useHead({ title: 'Chat với AI | Sylva LMS' })

const auth = useAuthStore()
const loading = ref(false)
const input = ref('')
const msgBox = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)

const userInitial = computed(() => {
  const name = auth.user?.name || 'S'
  return name.split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase()
})

interface ChatSource {
  source_file: string
  subject_name: string
  relevance_score: number
  content_preview: string
}

interface Message {
  role: 'user' | 'assistant'
  text: string
  time: string
  sources?: ChatSource[]
  has_rag_context?: boolean
}

const messages = reactive<Message[]>([])

const suggestions = [
  { text: 'Giải thích khái niệm hướng đối tượng (OOP)', icon: BookMarked },
  { text: 'Tìm khóa học phù hợp với tôi', icon: GraduationCap },
  { text: 'Lộ trình học lập trình Web từ đầu', icon: Map },
  { text: 'Cách sử dụng hệ thống Sylva LMS', icon: HelpCircle },
  { text: 'Ôn tập cấu trúc dữ liệu và giải thuật', icon: BookMarked },
  { text: 'Các môn học ngành Công nghệ thông tin PTIT', icon: GraduationCap },
]

function formatTime() {
  return new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
}

function sendQuick(text: string) {
  input.value = text
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isLoggedIn || !auth.token) {
    messages.push({
      role: 'assistant',
      text: 'Vui lòng đăng nhập để sử dụng Trợ lý AI.',
      time: formatTime(),
    })
    return
  }

  messages.push({ role: 'user', text, time: formatTime() })
  input.value = ''
  loading.value = true
  resetTextarea()
  scrollToBottom()

  try {
    const history = messages
      .slice(-12)
      .map(m => ({ role: m.role, content: m.text }))

    const route = useRoute()
    const courseId = route.params.id ? Number(route.params.id) : null

    const res = await useApi<any>('/ai/chat', {
      method: 'POST',
      body: { message: text, history, course_id: courseId },
      token: auth.token,
    })

    messages.push({
      role: 'assistant',
      text: res.reply || 'Hệ thống chưa có phản hồi cho yêu cầu này.',
      time: formatTime(),
      sources: res.sources,
      has_rag_context: res.has_rag_context,
    })
  } catch {
    messages.push({
      role: 'assistant',
      text: 'Lỗi kết nối. Trợ lý AI hiện không khả dụng, vui lòng thử lại sau.',
      time: formatTime(),
    })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function clearChat() {
  messages.splice(0, messages.length)
}

function scrollToBottom() {
  nextTick(() => {
    msgBox.value?.scrollTo({ top: msgBox.value.scrollHeight, behavior: 'smooth' })
  })
}

function autoResize() {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 140) + 'px'
}

function resetTextarea() {
  if (inputRef.value) inputRef.value.style.height = 'auto'
}
</script>

<style scoped>
/* ── Layout ── */
.chat-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 0px);
  background: var(--bg, #eff2f0);
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
}

/* ── Hero Header ── */
.chat-hero {
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 100%);
  flex-shrink: 0;
  padding: 0 24px;
}

.chat-hero-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 900px;
  margin: 0 auto;
  height: 64px;
}

.chat-hero-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.chat-hero-avatar {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(29, 158, 117, 0.2);
  border: 1.5px solid rgba(29, 158, 117, 0.4);
  display: grid;
  place-items: center;
  color: #5ddfb4;
  flex-shrink: 0;
}

.chat-hero-pulse {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #5ddfb4;
  border: 2px solid #071812;
  animation: hero-pulse 2.5s ease-in-out infinite;
}

@keyframes hero-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.85); }
}

.chat-hero-title {
  font-size: 1rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 2px;
}

.chat-hero-sub {
  font-size: 0.74rem;
  color: rgba(255, 255, 255, 0.65);
  display: flex;
  align-items: center;
  gap: 5px;
  margin: 0;
}

.chat-online-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #5ddfb4;
  flex-shrink: 0;
}

.chat-clear-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.07);
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: background 140ms ease, color 140ms ease;
}

.chat-clear-btn:hover {
  background: rgba(255, 255, 255, 0.14);
  color: #fff;
}

/* ── Body ── */
.chat-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Messages ── */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 28px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  scroll-behavior: smooth;
}

.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb {
  background: var(--line-strong, rgba(31,49,43,.16));
  border-radius: 4px;
}

/* ── Empty State ── */
.chat-empty {
  margin: auto;
  text-align: center;
  max-width: 540px;
  padding: 40px 20px;
}

.chat-empty-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 20px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(29,158,117,.15) 0%, rgba(8,80,65,.08) 100%);
  border: 1px solid rgba(29,158,117,.25);
  display: grid;
  place-items: center;
  color: var(--green, #1d9e75);
}

.chat-empty-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--text, #1f312b);
  margin: 0 0 8px;
}

.chat-empty-lead {
  font-size: 0.88rem;
  color: var(--muted, #4a6059);
  line-height: 1.6;
  margin: 0 0 28px;
}

.chat-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.chat-suggestion {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  border-radius: 12px;
  border: 1.5px solid rgba(8,80,65,.2);
  background: var(--surface-strong, #fff);
  color: var(--green-deep, #085041);
  font-size: 0.81rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all 160ms ease;
  text-align: left;
  box-shadow: 0 1px 4px rgba(31,49,43,.07);
}

.chat-suggestion:hover {
  background: var(--green-soft, #e1f5ee);
  border-color: var(--green, #1d9e75);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(29,158,117,.18);
}

/* ── Message Row ── */
.chat-msg-row {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  max-width: 860px;
  margin: 0 auto;
  width: 100%;
}

.chat-msg-row.is-user { flex-direction: row-reverse; }

.chat-msg-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 72%;
}

.is-user .chat-msg-wrap { align-items: flex-end; }

/* ── Avatars ── */
.chat-msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  font-size: 0.72rem;
  font-weight: 800;
  background: #c8e6da;
  border: 1.5px solid rgba(8,80,65,.25);
  color: var(--green-deep, #085041);
}

.chat-msg-avatar--user {
  background: linear-gradient(135deg, var(--green,#1d9e75), var(--green-deep,#085041));
  color: #fff;
  border-color: transparent;
}

/* ── Bubbles ── */
.chat-bubble {
  padding: 12px 16px;
  border-radius: 18px;
  font-size: 0.875rem;
  line-height: 1.7;
  word-break: break-word;
}

.chat-bubble--user {
  background: linear-gradient(135deg, var(--green,#1d9e75) 0%, var(--green-deep,#085041) 100%);
  color: #fff;
  border-radius: 18px 18px 4px 18px;
  box-shadow: 0 4px 16px -6px rgba(29,158,117,.55);
}

.chat-bubble--bot {
  background: var(--surface-strong, #fff);
  color: var(--text, #1f312b);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 4px 18px 18px 18px;
  box-shadow: 0 2px 8px rgba(31,49,43,.07);
}

.chat-bubble-text { margin: 0; white-space: pre-wrap; }

/* ── Typing ── */
.chat-bubble--typing {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 14px 20px;
}

.chat-dot {
  display: block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--green, #1d9e75);
  animation: chat-bounce 1s ease-in-out infinite;
}

@keyframes chat-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
  40% { transform: translateY(-6px); opacity: 1; }
}

/* ── Timestamp ── */
.chat-msg-time {
  font-size: 0.7rem;
  color: var(--muted, #7a9c8e);
  padding: 0 4px;
}

/* ── RAG Sources ── */
.chat-sources {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed rgba(0,0,0,.1);
}

.chat-sources-header {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.71rem;
  font-weight: 700;
  color: var(--muted, #4a6059);
  margin-bottom: 6px;
}

.chat-sources-list {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.chat-source-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: rgba(8,80,65,.06);
  border: 1px solid rgba(8,80,65,.12);
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.7rem;
  color: var(--green-deep, #085041);
  font-weight: 500;
  cursor: help;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-source-pct {
  font-size: 0.65rem;
  color: var(--green, #1d9e75);
  font-style: normal;
  font-weight: 600;
}

/* ── Input Bar ── */
.chat-input-bar {
  flex-shrink: 0;
  padding: 12px 24px 16px;
  border-top: 1px solid var(--line, #dde5e1);
  background: var(--surface-strong, #fff);
}

.chat-quick-chips {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.chat-quick-chip {
  padding: 5px 12px;
  border-radius: 999px;
  border: 1.5px solid rgba(8,80,65,.22);
  background: var(--bg, #eff2f0);
  color: var(--green-deep, #085041);
  font-size: 0.77rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: all 140ms ease;
}

.chat-quick-chip:hover {
  background: var(--green-soft, #e1f5ee);
  border-color: var(--green, #1d9e75);
}

.chat-form {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  background: var(--bg, #eff2f0);
  border: 1.5px solid var(--line, #dde5e1);
  border-radius: 16px;
  padding: 6px 6px 6px 16px;
  transition: border-color 160ms ease, box-shadow 160ms ease;
  max-width: 900px;
  margin: 0 auto;
}

.chat-form:focus-within {
  border-color: rgba(29,158,117,.5);
  box-shadow: 0 0 0 3px rgba(29,158,117,.1);
}

.chat-textarea {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 0.875rem;
  color: var(--text, #1f312b);
  outline: none;
  resize: none;
  font-family: inherit;
  line-height: 1.6;
  padding: 7px 0;
  min-height: 36px;
  max-height: 140px;
  overflow-y: auto;
}

.chat-textarea::placeholder { color: var(--muted, #7a9c8e); }

.chat-send-btn {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, var(--green,#1d9e75) 0%, var(--green-deep,#085041) 100%);
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 140ms ease, transform 140ms ease;
  box-shadow: 0 4px 12px -4px rgba(29,158,117,.6);
}

.chat-send-btn:hover:not(:disabled) {
  opacity: 0.88;
  transform: scale(1.06);
}

.chat-send-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.chat-disclaimer {
  text-align: center;
  font-size: 0.69rem;
  color: var(--muted, #7a9c8e);
  margin: 8px 0 0;
}

/* ── Dark Mode ── */
[data-theme="dark"] .chat-hero { background: linear-gradient(135deg, #030d07 0%, #071812 100%); }

[data-theme="dark"] .chat-messages { background: #0a1910; }

[data-theme="dark"] .chat-bubble--bot {
  background: #132a1f;
  border-color: rgba(255,255,255,.1);
  color: #d4e8df;
  box-shadow: none;
}

[data-theme="dark"] .chat-msg-avatar {
  background: #1d4535;
  border-color: rgba(93,223,180,.3);
}

[data-theme="dark"] .chat-empty-title { color: #d4e8df; }
[data-theme="dark"] .chat-empty-lead  { color: #a3b8b0; }

[data-theme="dark"] .chat-suggestion {
  background: #132a1f;
  border-color: rgba(93,223,180,.2);
  color: #5ddfb4;
  box-shadow: none;
}

[data-theme="dark"] .chat-suggestion:hover {
  background: #1d4535;
  border-color: #5ddfb4;
}

[data-theme="dark"] .chat-source-tag {
  background: rgba(93,223,180,.08);
  border-color: rgba(93,223,180,.2);
  color: #5ddfb4;
}

[data-theme="dark"] .chat-input-bar {
  background: #0f1f18;
  border-color: rgba(255,255,255,.08);
}

[data-theme="dark"] .chat-form {
  background: rgba(255,255,255,.05);
  border-color: rgba(255,255,255,.1);
}

[data-theme="dark"] .chat-textarea { color: #e2ede9; }
[data-theme="dark"] .chat-textarea::placeholder { color: rgba(255,255,255,.35); }

[data-theme="dark"] .chat-quick-chip {
  background: #132a1f;
  border-color: rgba(93,223,180,.25);
  color: #5ddfb4;
}

[data-theme="dark"] .chat-quick-chip:hover {
  background: #1d4535;
  border-color: #5ddfb4;
}

[data-theme="dark"] .chat-sources { border-top-color: rgba(255,255,255,.1); }
[data-theme="dark"] .chat-sources-header { color: #a3b8b0; }
</style>
