<template>
  <div class="aichat-wrap">

    <!-- ── Card Header ── -->
    <div class="aichat-header">
      <div class="aichat-header-left">
        <div class="aichat-avatar-ring">
          <Bot :size="20" />
          <span class="aichat-status-dot" />
        </div>
        <div>
          <h1 class="aichat-title">Trợ lý AI Sylva</h1>
          <p class="aichat-subtitle">Đang hoạt động · Trả lời dựa trên tài liệu PTIT</p>
        </div>
      </div>
      <button class="aichat-clear-btn" title="Xóa hội thoại" @click="clearChat">
        <RotateCcw :size="14" />
        Xóa hội thoại
      </button>
    </div>

    <!-- ── Messages ── -->
    <div ref="msgBox" class="aichat-messages">

      <!-- Empty / welcome state -->
      <div v-if="messages.length === 0" class="aichat-welcome">
        <div class="aichat-welcome-icon">
          <Sparkles :size="28" />
        </div>
        <h2 class="aichat-welcome-title">Xin chào! Tôi có thể giúp gì cho bạn?</h2>
        <p class="aichat-welcome-desc">
          Hỏi tôi về bài học, tài liệu PTIT, lộ trình học tập hoặc bất kỳ điều gì về hệ thống Sylva LMS.
        </p>
        <div class="aichat-chips">
          <button
            v-for="q in suggestions"
            :key="q"
            class="aichat-chip"
            @click="sendQuick(q)"
          >
            {{ q }}
          </button>
        </div>
      </div>

      <!-- Message list -->
      <template v-else>
        <div
          v-for="(msg, idx) in messages"
          :key="idx"
          class="aichat-row"
          :class="msg.role === 'user' ? 'is-user' : 'is-bot'"
        >
          <!-- Bot avatar -->
          <div v-if="msg.role === 'assistant'" class="aichat-row-avatar">
            <Bot :size="14" />
          </div>

          <div class="aichat-row-body">
            <!-- Bubble -->
            <div class="aichat-bubble" :class="msg.role === 'user' ? 'aichat-bubble--user' : 'aichat-bubble--bot'">
              <p class="aichat-bubble-text">{{ msg.text }}</p>

              <!-- RAG sources -->
              <div v-if="msg.sources && msg.sources.length > 0" class="aichat-sources">
                <div class="aichat-sources-label">
                  <BookOpen :size="11" />
                  <span>Tài liệu tham khảo:</span>
                </div>
                <div class="aichat-sources-list">
                  <span
                    v-for="(src, si) in msg.sources"
                    :key="si"
                    class="aichat-source-tag"
                    :title="src.content_preview"
                  >
                    [{{ si + 1 }}] {{ src.source_file }}
                    <em class="aichat-source-pct">{{ Math.round(src.relevance_score) }}%</em>
                  </span>
                </div>
              </div>
            </div>
            <!-- Time -->
            <span class="aichat-time">{{ msg.time }}</span>
          </div>

          <!-- User avatar -->
          <div v-if="msg.role === 'user'" class="aichat-row-avatar aichat-row-avatar--user">
            {{ userInitial }}
          </div>
        </div>

        <!-- Typing indicator -->
        <div v-if="loading" class="aichat-row is-bot">
          <div class="aichat-row-avatar"><Bot :size="14" /></div>
          <div class="aichat-bubble aichat-bubble--bot aichat-bubble--typing">
            <span class="aichat-dot" style="animation-delay:0ms" />
            <span class="aichat-dot" style="animation-delay:140ms" />
            <span class="aichat-dot" style="animation-delay:280ms" />
          </div>
        </div>
      </template>
    </div>

    <!-- ── Quick chips (shown when chat started) ── -->
    <div v-if="messages.length > 0 && messages.length < 4 && !loading" class="aichat-quick-row">
      <button
        v-for="q in suggestions.slice(0, 3)"
        :key="q"
        class="aichat-chip aichat-chip--sm"
        @click="sendQuick(q)"
      >
        {{ q }}
      </button>
    </div>

    <!-- ── Input Bar ── -->
    <div class="aichat-input-bar">
      <form class="aichat-form" @submit.prevent="sendMessage">
        <textarea
          ref="inputRef"
          v-model="input"
          class="aichat-textarea"
          placeholder="Nhập câu hỏi... (Enter để gửi, Shift+Enter xuống dòng)"
          rows="1"
          @keydown.enter.exact.prevent="sendMessage"
          @input="autoResize"
        />
        <button
          type="submit"
          class="aichat-send"
          :disabled="!input.trim() || loading"
          aria-label="Gửi"
        >
          <ArrowUp :size="18" />
        </button>
      </form>
      <p class="aichat-disclaimer">AI có thể mắc lỗi — vui lòng kiểm tra thông tin quan trọng.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue'
import { Bot, Sparkles, RotateCcw, ArrowUp, BookOpen } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })
useHead({ title: 'Chat với AI | Sylva LMS' })

const auth = useAuthStore()

const userInitial = computed(() => {
  const name = auth.user?.name || 'SV'
  return name.split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase()
})

const loading  = ref(false)
const input    = ref('')
const msgBox   = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)

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
  'Giải thích khái niệm hướng đối tượng (OOP)',
  'Tìm khóa học phù hợp với tôi',
  'Lộ trình học lập trình Web từ đầu',
  'Ôn tập cấu trúc dữ liệu và giải thuật',
  'Các môn học ngành CNTT PTIT',
  'Cách sử dụng hệ thống Sylva LMS',
]

function now() {
  return new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
}

function sendQuick(text: string) {
  input.value = text
  sendMessage()
}

function clearChat() {
  messages.splice(0, messages.length)
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isLoggedIn || !auth.token) {
    messages.push({ role: 'assistant', text: 'Vui lòng đăng nhập để sử dụng Trợ lý AI.', time: now() })
    return
  }

  messages.push({ role: 'user', text, time: now() })
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
      time: now(),
      sources: res.sources,
      has_rag_context: res.has_rag_context,
    })
  }
  catch {
    messages.push({
      role: 'assistant',
      text: 'Lỗi kết nối. Trợ lý AI hiện không khả dụng, vui lòng thử lại sau.',
      time: now(),
    })
  }
  finally {
    loading.value = false
    scrollToBottom()
  }
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
/* ── Wrapper — fills sv-content ── */
.aichat-wrap {
  display: flex;
  flex-direction: column;
  flex: 1;
  height: 100%;
  min-height: 500px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 16px;
  overflow: hidden;
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
  box-shadow: 0 4px 24px -8px rgba(8,80,65,.08);
}

/* ── Header ── */
.aichat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 100%);
  flex-shrink: 0;
}

.aichat-header-left { display: flex; align-items: center; gap: 12px; }

.aichat-avatar-ring {
  position: relative;
  width: 40px; height: 40px;
  border-radius: 50%;
  background: rgba(29,158,117,.2);
  border: 1.5px solid rgba(29,158,117,.4);
  display: grid;
  place-items: center;
  color: #5ddfb4;
  flex-shrink: 0;
}

.aichat-status-dot {
  position: absolute;
  top: 1px; right: 1px;
  width: 10px; height: 10px;
  border-radius: 50%;
  background: #5ddfb4;
  border: 2px solid #071812;
  animation: dot-blink 2.5s ease-in-out infinite;
}

@keyframes dot-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: .4; }
}

.aichat-title {
  font-size: .95rem; font-weight: 800; color: #fff; margin: 0 0 2px;
}

.aichat-subtitle {
  font-size: .7rem; color: rgba(255,255,255,.6); margin: 0;
}

.aichat-clear-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 13px;
  border-radius: 9px;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.07);
  color: rgba(255,255,255,.7);
  font-size: .76rem; font-weight: 600; font-family: inherit;
  cursor: pointer;
  transition: background 140ms, color 140ms;
}
.aichat-clear-btn:hover { background: rgba(255,255,255,.14); color: #fff; }

/* ── Messages ── */
.aichat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px 20px 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--bg, #f5f7f6);
}

.aichat-messages::-webkit-scrollbar { width: 4px; }
.aichat-messages::-webkit-scrollbar-track { background: transparent; }
.aichat-messages::-webkit-scrollbar-thumb {
  background: rgba(31,49,43,.14); border-radius: 4px;
}

/* ── Welcome ── */
.aichat-welcome {
  margin: auto; text-align: center; max-width: 500px; padding: 32px 16px;
}

.aichat-welcome-icon {
  width: 64px; height: 64px;
  margin: 0 auto 16px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(29,158,117,.15) 0%, rgba(8,80,65,.08) 100%);
  border: 1px solid rgba(29,158,117,.25);
  display: grid; place-items: center;
  color: var(--green, #1d9e75);
}

.aichat-welcome-title {
  font-size: 1.15rem; font-weight: 800;
  color: var(--text, #1f312b); margin: 0 0 8px;
}

.aichat-welcome-desc {
  font-size: .85rem; color: var(--muted, #4a6059);
  line-height: 1.65; margin: 0 0 24px;
}

/* ── Chips ── */
.aichat-chips {
  display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
}

.aichat-chip {
  padding: 8px 15px; border-radius: 12px;
  border: 1.5px solid rgba(8,80,65,.2);
  background: var(--surface-strong, #fff);
  color: var(--green-deep, #085041);
  font-size: .8rem; font-weight: 600; font-family: inherit;
  cursor: pointer;
  transition: all 150ms ease;
  box-shadow: 0 1px 4px rgba(31,49,43,.07);
  text-align: left;
}

.aichat-chip:hover {
  background: var(--green-soft, #e1f5ee);
  border-color: var(--green, #1d9e75);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(29,158,117,.15);
}

.aichat-chip--sm {
  font-size: .76rem; padding: 5px 12px; border-radius: 999px;
}

/* ── Rows ── */
.aichat-row { display: flex; align-items: flex-end; gap: 8px; }
.aichat-row.is-user { flex-direction: row-reverse; }

.aichat-row-body {
  display: flex; flex-direction: column; gap: 3px; max-width: 72%;
}
.is-user .aichat-row-body { align-items: flex-end; }

.aichat-row-avatar {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: #c8e6da;
  border: 1.5px solid rgba(8,80,65,.2);
  display: grid; place-items: center;
  color: var(--green-deep, #085041);
  flex-shrink: 0;
  font-size: .68rem; font-weight: 800;
}

.aichat-row-avatar--user {
  background: linear-gradient(135deg, var(--green,#1d9e75), var(--green-deep,#085041));
  color: #fff; border-color: transparent;
}

/* ── Bubbles ── */
.aichat-bubble {
  padding: 10px 14px; border-radius: 16px;
  font-size: .875rem; line-height: 1.65; word-break: break-word;
}

.aichat-bubble--user {
  background: linear-gradient(135deg, var(--green,#1d9e75) 0%, var(--green-deep,#085041) 100%);
  color: #fff;
  border-radius: 16px 16px 4px 16px;
  box-shadow: 0 4px 14px -6px rgba(29,158,117,.55);
}

.aichat-bubble--bot {
  background: var(--surface-strong, #fff);
  color: var(--text, #1f312b);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 4px 16px 16px 16px;
  box-shadow: 0 1px 4px rgba(31,49,43,.07);
}

.aichat-bubble-text { margin: 0; white-space: pre-wrap; }

.aichat-bubble--typing {
  display: flex; align-items: center; gap: 5px; padding: 12px 18px;
}

.aichat-dot {
  display: block; width: 6px; height: 6px;
  border-radius: 50%; background: var(--green, #1d9e75);
  animation: dot-bounce 1s ease-in-out infinite;
}

@keyframes dot-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: .5; }
  40%           { transform: translateY(-5px); opacity: 1; }
}

.aichat-time { font-size: .68rem; color: var(--muted, #8aaa9c); padding: 0 3px; }

/* ── RAG Sources ── */
.aichat-sources {
  margin-top: 8px; padding-top: 7px;
  border-top: 1px dashed rgba(0,0,0,.08);
}

.aichat-sources-label {
  display: flex; align-items: center; gap: 4px;
  font-size: .69rem; font-weight: 700;
  color: var(--muted, #4a6059); margin-bottom: 5px;
}

.aichat-sources-list { display: flex; flex-wrap: wrap; gap: 4px; }

.aichat-source-tag {
  display: inline-flex; align-items: center; gap: 3px;
  background: rgba(8,80,65,.05);
  border: 1px solid rgba(8,80,65,.12);
  padding: 2px 7px; border-radius: 999px;
  font-size: .68rem; color: var(--green-deep, #085041);
  font-weight: 500; cursor: help;
  max-width: 180px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}

.aichat-source-pct {
  font-size: .63rem; color: var(--green, #1d9e75);
  font-style: normal; font-weight: 700;
}

/* ── Quick row ── */
.aichat-quick-row {
  display: flex; gap: 7px; flex-wrap: wrap;
  padding: 8px 20px 0;
  background: var(--bg, #f5f7f6);
}

/* ── Input bar ── */
.aichat-input-bar {
  flex-shrink: 0;
  padding: 10px 20px 14px;
  border-top: 1px solid var(--line, #dde5e1);
  background: var(--surface-strong, #fff);
}

.aichat-form {
  display: flex; align-items: flex-end; gap: 8px;
  background: var(--bg, #f5f7f6);
  border: 1.5px solid var(--line, #dde5e1);
  border-radius: 14px;
  padding: 5px 5px 5px 14px;
  transition: border-color 150ms, box-shadow 150ms;
}

.aichat-form:focus-within {
  border-color: rgba(29,158,117,.5);
  box-shadow: 0 0 0 3px rgba(29,158,117,.1);
}

.aichat-textarea {
  flex: 1; border: none; background: transparent;
  font-size: .875rem; color: var(--text, #1f312b);
  outline: none; resize: none; font-family: inherit;
  line-height: 1.55; padding: 7px 0;
  min-height: 34px; max-height: 140px; overflow-y: auto;
}

.aichat-textarea::placeholder { color: var(--muted, #8aaa9c); }

.aichat-send {
  width: 38px; height: 38px; border-radius: 10px; border: none;
  background: linear-gradient(135deg, var(--green,#1d9e75) 0%, var(--green-deep,#085041) 100%);
  color: #fff; display: grid; place-items: center; cursor: pointer; flex-shrink: 0;
  transition: opacity 130ms, transform 130ms;
  box-shadow: 0 4px 10px -4px rgba(29,158,117,.6);
}

.aichat-send:hover:not(:disabled) { opacity: .88; transform: scale(1.06); }

.aichat-send:disabled {
  opacity: .32; cursor: not-allowed; box-shadow: none; transform: none;
}

.aichat-disclaimer {
  text-align: center; font-size: .68rem;
  color: var(--muted, #8aaa9c); margin: 7px 0 0;
}

/* ── Dark mode ── */
[data-theme="dark"] .aichat-wrap { background: #0f1f18; border-color: rgba(255,255,255,.07); }
[data-theme="dark"] .aichat-messages { background: #0a1910; }
[data-theme="dark"] .aichat-bubble--bot { background: #132a1f; border-color: rgba(255,255,255,.1); color: #d4e8df; box-shadow: none; }
[data-theme="dark"] .aichat-row-avatar { background: #1d4535; border-color: rgba(93,223,180,.3); }
[data-theme="dark"] .aichat-welcome-title { color: #d4e8df; }
[data-theme="dark"] .aichat-welcome-desc  { color: #a3b8b0; }
[data-theme="dark"] .aichat-chip { background: #132a1f; border-color: rgba(93,223,180,.2); color: #5ddfb4; box-shadow: none; }
[data-theme="dark"] .aichat-chip:hover { background: #1d4535; border-color: #5ddfb4; }
[data-theme="dark"] .aichat-source-tag { background: rgba(93,223,180,.07); border-color: rgba(93,223,180,.2); color: #5ddfb4; }
[data-theme="dark"] .aichat-quick-row { background: #0a1910; }
[data-theme="dark"] .aichat-input-bar { background: #0f1f18; border-color: rgba(255,255,255,.07); }
[data-theme="dark"] .aichat-form { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.09); }
[data-theme="dark"] .aichat-textarea { color: #e2ede9; }
[data-theme="dark"] .aichat-sources { border-top-color: rgba(255,255,255,.1); }
[data-theme="dark"] .aichat-sources-label { color: #a3b8b0; }
</style>
