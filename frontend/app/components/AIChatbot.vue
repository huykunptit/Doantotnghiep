<template>
  <div class="cb-root">
    <!-- FAB -->
    <Transition name="cb-fade-up">
      <button v-if="!isOpen" class="cb-fab" type="button" title="Trợ lý AI" @click="openChat">
        <Bot :size="24" />
        <span class="cb-fab-pulse" />
        <div class="cb-fab-tooltip">Trợ lý AI</div>
      </button>
    </Transition>

    <!-- Chat Window -->
    <Transition name="cb-slide-up">
      <div v-if="isOpen" class="cb-window">
        <!-- Header -->
        <header class="cb-header">
          <div class="cb-header-left">
            <div class="cb-avatar">
              <Sparkles :size="18" />
            </div>
            <div class="cb-header-info">
              <span class="cb-header-name">Trợ lý AI</span>
              <span class="cb-header-status">
                <span class="cb-status-dot" />
                Đang hoạt động
              </span>
            </div>
          </div>
          <div class="cb-header-actions">
            <button class="cb-icon-btn" type="button" title="Xóa hội thoại" @click="clearChat">
              <RotateCcw :size="15" />
            </button>
            <button class="cb-icon-btn cb-icon-btn--close" type="button" title="Đóng" @click="isOpen = false">
              <X :size="15" />
            </button>
          </div>
        </header>

        <!-- Messages -->
        <div ref="messageBox" class="cb-messages">
          <div
            v-for="(msg, idx) in messages"
            :key="idx"
            class="cb-row"
            :class="msg.role === 'user' ? 'cb-row--user' : 'cb-row--bot'"
          >
            <div v-if="msg.role === 'assistant'" class="cb-bot-avatar">
              <Bot :size="14" />
            </div>
            <div class="cb-bubble" :class="msg.role === 'user' ? 'cb-bubble--user' : 'cb-bubble--bot'">
              <p class="cb-bubble-text">{{ msg.text }}</p>
              
              <!-- RAG Sources citations -->
              <div v-if="msg.sources && msg.sources.length > 0" class="cb-sources-wrap">
                <span class="cb-sources-title">Trích dẫn tài liệu tham khảo:</span>
                <div class="cb-sources-list">
                  <div 
                    v-for="(src, sIdx) in msg.sources" 
                    :key="sIdx" 
                    class="cb-source-item" 
                    :title="src.content_preview"
                  >
                    <BookOpen :size="10" />
                    <span class="cb-source-file-name">[{{ sIdx + 1 }}] {{ src.source_file }}</span>
                    <span class="cb-source-score">({{ Math.round(src.relevance_score) }}%)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Typing dots -->
          <div v-if="loading" class="cb-row cb-row--bot">
            <div class="cb-bot-avatar"><Bot :size="14" /></div>
            <div class="cb-bubble cb-bubble--bot cb-bubble--typing">
              <span class="cb-dot" style="animation-delay: 0ms" />
              <span class="cb-dot" style="animation-delay: 140ms" />
              <span class="cb-dot" style="animation-delay: 280ms" />
            </div>
          </div>
        </div>

        <!-- Quick Questions -->
        <div v-if="messages.length <= 1 && !loading" class="cb-quick">
          <button
            v-for="q in quickQuestions"
            :key="q"
            class="cb-quick-chip"
            type="button"
            @click="sendQuick(q)"
          >
            {{ q }}
          </button>
        </div>

        <!-- Input -->
        <footer class="cb-footer">
          <form class="cb-form" @submit.prevent="sendMessage">
            <input
              ref="chatInput"
              v-model="input"
              type="text"
              class="cb-input"
              placeholder="Nhập câu hỏi..."
              @keydown.escape="isOpen = false"
            >
            <button
              type="submit"
              class="cb-send"
              :disabled="!input.trim() || loading"
            >
              <ArrowUp :size="16" />
            </button>
          </form>
          <p class="cb-disclaimer">AI có thể mắc lỗi — vui lòng kiểm tra thông tin quan trọng.</p>
        </footer>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue'
import { Bot, Sparkles, RotateCcw, X, ArrowUp, BookOpen } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const auth = useAuthStore()
const isOpen = ref(false)
const loading = ref(false)
const input = ref('')
const messageBox = ref<HTMLElement | null>(null)
const chatInput = ref<HTMLInputElement | null>(null)

const quickQuestions = [
  'Tìm khóa học phù hợp',
  'Lộ trình học lập trình',
  'Cách sử dụng hệ thống',
]

interface MessageItem {
  role: 'user' | 'assistant'
  text: string
  sources?: Array<{ source_file: string; subject_name: string; relevance_score: number; content_preview: string }>
  has_rag_context?: boolean
}

const messages = reactive<MessageItem[]>([
  { role: 'assistant', text: 'Xin chào! Tôi có thể giúp bạn tìm khóa học, tư vấn lộ trình học tập hoặc giải đáp thắc mắc về hệ thống. Bạn cần hỗ trợ gì?' },
])

function openChat() {
  isOpen.value = true
  nextTick(() => chatInput.value?.focus())
}

function clearChat() {
  messages.splice(0, messages.length)
  messages.push({ role: 'assistant', text: 'Đã xoá hội thoại. Bạn cần hỗ trợ gì?' })
}

function sendQuick(question: string) {
  input.value = question
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isLoggedIn || !auth.token) {
    messages.push({ role: 'assistant', text: 'Vui lòng đăng nhập để sử dụng Trợ lý AI.' })
    input.value = ''
    scrollToBottom()
    return
  }

  messages.push({ role: 'user', text })
  input.value = ''
  loading.value = true
  scrollToBottom()

  try {
    const history = messages
      .slice(1)  // bỏ tin nhắn chào đầu tiên
      .slice(-10) // giữ tối đa 10 tin nhắn gần nhất
      .map(m => ({ role: m.role, content: m.text }))

    // Detect if we are on a course page and fetch course ID
    const route = useRoute()
    const courseId = route.params.id ? Number(route.params.id) : null

    const res = await useApi<any>('/ai/chat', {
      method: 'POST',
      body: { 
        message: text, 
        history,
        course_id: courseId
      },
      token: auth.token,
    })
    
    messages.push({ 
      role: 'assistant', 
      text: res.reply || 'Hệ thống chưa có phản hồi cho yêu cầu này.',
      sources: res.sources,
      has_rag_context: res.has_rag_context
    })
  } catch {
    messages.push({ role: 'assistant', text: 'Lỗi kết nối. Trợ lý AI hiện không khả dụng, vui lòng thử lại sau.' })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function scrollToBottom() {
  nextTick(() => {
    messageBox.value?.scrollTo({ top: messageBox.value.scrollHeight, behavior: 'smooth' })
  })
}

if (import.meta.client) {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen.value) isOpen.value = false
  })
}
</script>

<style scoped>
/* ── Root ── */
.cb-root {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
}

/* ── FAB ── */
.cb-fab {
  position: relative;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: none;
  background: linear-gradient(135deg, var(--green, #1d9e75) 0%, var(--green-deep, #085041) 100%);
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  box-shadow: 0 8px 24px -8px rgba(29, 158, 117, 0.6), 0 2px 8px rgba(0,0,0,0.12);
  transition: transform 160ms ease, box-shadow 160ms ease;
}

.cb-fab:hover {
  transform: translateY(-2px) scale(1.04);
  box-shadow: 0 14px 32px -8px rgba(29, 158, 117, 0.7), 0 4px 12px rgba(0,0,0,0.14);
}

.cb-fab:active {
  transform: scale(0.96);
}

.cb-fab-pulse {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid var(--green, #1d9e75);
}

.cb-fab-pulse::before {
  content: '';
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.45);
  animation: cb-pulse 2s ease-in-out infinite;
}

@keyframes cb-pulse {
  0%, 100% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.7); opacity: 0; }
}

.cb-fab-tooltip {
  position: absolute;
  right: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%);
  background: var(--text, #1f312b);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 10px;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity 160ms ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.cb-fab:hover .cb-fab-tooltip {
  opacity: 1;
}

/* ── Window ── */
.cb-window {
  width: 360px;
  height: 560px;
  display: flex;
  flex-direction: column;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 20px;
  box-shadow: 0 24px 60px -20px rgba(8, 80, 65, 0.18), 0 8px 24px rgba(0,0,0,0.08);
  overflow: hidden;
  transform-origin: bottom right;
}

/* ── Header ── */
.cb-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 100%);
  flex-shrink: 0;
}

.cb-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cb-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(29, 158, 117, 0.25);
  border: 1px solid rgba(29, 158, 117, 0.4);
  display: grid;
  place-items: center;
  color: #5DDFB4;
  flex-shrink: 0;
}

.cb-header-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cb-header-name {
  font-size: 0.875rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
}

.cb-header-status {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  color: rgba(255,255,255,0.75);
  font-weight: 500;
}

.cb-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #5DDFB4;
  flex-shrink: 0;
  animation: cb-blink 2.5s ease-in-out infinite;
}

@keyframes cb-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.cb-header-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.cb-icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: none;
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.65);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: background 140ms ease, color 140ms ease;
}

.cb-icon-btn:hover {
  background: rgba(255,255,255,0.15);
  color: #fff;
}

.cb-icon-btn--close:hover {
  background: rgba(226, 75, 74, 0.25);
  color: #f87171;
}

/* ── Messages ── */
.cb-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scroll-behavior: smooth;
  background: var(--bg, #eff2f0);
}

.cb-messages::-webkit-scrollbar {
  width: 4px;
}

.cb-messages::-webkit-scrollbar-track {
  background: transparent;
}

.cb-messages::-webkit-scrollbar-thumb {
  background: var(--line-strong, rgba(31,49,43,0.16));
  border-radius: 4px;
}

.cb-row {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

.cb-row--user {
  flex-direction: row-reverse;
}

.cb-bot-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #c8e6da;
  border: 1.5px solid rgba(8, 80, 65, 0.25);
  display: grid;
  place-items: center;
  color: var(--green-deep, #085041);
  flex-shrink: 0;
}

.cb-bubble {
  max-width: 82%;
  padding: 10px 14px;
  border-radius: 18px;
  font-size: 0.875rem;
  line-height: 1.6;
}

.cb-bubble--user {
  background: linear-gradient(135deg, var(--green, #1d9e75) 0%, var(--green-deep, #085041) 100%);
  color: #fff;
  border-radius: 18px 18px 4px 18px;
  box-shadow: 0 4px 12px -4px rgba(29, 158, 117, 0.5);
}

.cb-bubble--bot {
  background: var(--surface-strong, #fff);
  color: var(--text, #1f312b);
  border: 1px solid var(--line-strong, rgba(31,49,43,0.16));
  border-radius: 4px 18px 18px 18px;
  box-shadow: 0 1px 4px rgba(31,49,43,0.07);
}

.cb-bubble-text {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

/* ── Typing ── */
.cb-bubble--typing {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 14px 18px;
}

.cb-dot {
  display: block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--green, #1d9e75);
  animation: cb-bounce 1s ease-in-out infinite;
}

@keyframes cb-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
  40% { transform: translateY(-6px); opacity: 1; }
}

/* ── Quick Questions ── */
.cb-quick {
  display: flex;
  gap: 7px;
  padding: 8px 16px 12px;
  overflow-x: auto;
  flex-shrink: 0;
  background: var(--bg, #eff2f0);
  border-top: 1px solid var(--line, #dde5e1);
}

.cb-quick::-webkit-scrollbar { display: none; }

.cb-quick-chip {
  flex-shrink: 0;
  padding: 7px 14px;
  border-radius: 999px;
  border: 1.5px solid rgba(8, 80, 65, 0.3);
  background: #fff;
  color: var(--green-deep, #085041);
  font-size: 0.78rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(31,49,43,0.08);
  transition: background 140ms ease, border-color 140ms ease, transform 140ms ease, box-shadow 140ms ease;
}

.cb-quick-chip:hover {
  background: var(--green-soft, #e1f5ee);
  border-color: var(--green, #1d9e75);
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(29,158,117,0.18);
}

/* ── Footer ── */
.cb-footer {
  padding: 12px 14px 14px;
  border-top: 1px solid var(--line, #dde5e1);
  flex-shrink: 0;
  background: var(--surface-strong, #fff);
}

.cb-form {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--bg, #eff2f0);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 14px;
  padding: 4px 4px 4px 14px;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.cb-form:focus-within {
  border-color: rgba(29, 158, 117, 0.5);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.1);
}

.cb-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 0.875rem;
  color: var(--text, #1f312b);
  outline: none;
  padding: 8px 0;
  font-family: inherit;
}

.cb-input::placeholder {
  color: var(--muted, #4a6059);
}

.cb-send {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, var(--green, #1d9e75) 0%, var(--green-deep, #085041) 100%);
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 140ms ease, transform 140ms ease;
  box-shadow: 0 4px 10px -4px rgba(29, 158, 117, 0.6);
}

.cb-send:hover:not(:disabled) {
  opacity: 0.88;
  transform: scale(1.05);
}

.cb-send:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.cb-disclaimer {
  margin: 8px 0 0;
  text-align: center;
  font-size: 0.7rem;
  color: var(--muted, #4a6059);
}

/* ── Transitions ── */
.cb-fade-up-enter-active,
.cb-fade-up-leave-active {
  transition: opacity 260ms ease, transform 260ms ease;
}

.cb-fade-up-enter-from,
.cb-fade-up-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.9);
}

.cb-slide-up-enter-active {
  transition: opacity 280ms ease, transform 280ms cubic-bezier(0.34, 1.46, 0.64, 1);
}

.cb-slide-up-leave-active {
  transition: opacity 200ms ease, transform 200ms ease;
}

.cb-slide-up-enter-from,
.cb-slide-up-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.96);
}

/* ── Dark Mode ── */
[data-theme="dark"] .cb-window {
  background: #0f1f18;
  border-color: rgba(255,255,255,0.08);
}

[data-theme="dark"] .cb-messages {
  background: #0a1910;
}

[data-theme="dark"] .cb-bubble--bot {
  background: #132a1f;
  border-color: rgba(255,255,255,0.12);
  color: #d4e8df;
  box-shadow: none;
}

[data-theme="dark"] .cb-bot-avatar {
  background: #1d4535;
  border-color: rgba(93,223,180,0.3);
}

[data-theme="dark"] .cb-dot {
  background: #5ddfb4;
}

[data-theme="dark"] .cb-footer {
  background: #0f1f18;
  border-color: rgba(255,255,255,0.08);
}

[data-theme="dark"] .cb-form {
  background: rgba(255,255,255,0.05);
  border-color: rgba(255,255,255,0.1);
}

[data-theme="dark"] .cb-input {
  color: #e2ede9;
}

[data-theme="dark"] .cb-messages::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.1);
}

[data-theme="dark"] .cb-quick {
  background: #0a1910;
  border-color: rgba(255,255,255,0.08);
}

[data-theme="dark"] .cb-quick-chip {
  color: #5ddfb4;
  border-color: rgba(93,223,180,0.35);
  background: #132a1f;
  box-shadow: none;
}

[data-theme="dark"] .cb-quick-chip:hover {
  background: #1d4535;
  border-color: #5ddfb4;
}

[data-theme="dark"] .cb-fab-tooltip {
  background: #e2ede9;
  color: #0f1f18;
}

/* ── RAG sources style ── */
.cb-sources-wrap {
  margin-top: 8px;
  padding-top: 6px;
  border-top: 1px dashed rgba(0, 0, 0, 0.08);
  font-size: 0.74rem;
  color: var(--muted, #6b7280);
  display: grid;
  gap: 4px;
}
.cb-sources-title {
  font-weight: 700;
  color: var(--text, #111);
  font-size: 0.72rem;
}
.cb-sources-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.cb-source-item {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(0, 0, 0, 0.04);
  padding: 2px 6px;
  border-radius: 4px;
  cursor: help;
}
.cb-source-file-name {
  font-weight: 500;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cb-source-score {
  font-size: 0.65rem;
  color: var(--green, #1d9e75);
}

[data-theme="dark"] .cb-sources-wrap {
  border-top-color: rgba(255, 255, 255, 0.08);
  color: #a3b8b0;
}
[data-theme="dark"] .cb-sources-title {
  color: #e2ede9;
}
[data-theme="dark"] .cb-source-item {
  background: rgba(255, 255, 255, 0.05);
}
[data-theme="dark"] .cb-source-score {
  color: #5ddfb4;
}
</style>
