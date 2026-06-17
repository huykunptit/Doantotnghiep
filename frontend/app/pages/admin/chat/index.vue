<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface Message {
  role: 'user' | 'assistant'
  content: string
  time: string
}

const messages = ref<Message[]>([])
const input = ref('')
const loading = ref(false)
const messagesEl = ref<HTMLElement | null>(null)
const courseId = ref<number | null>(null)
const courses = ref<any[]>([])
const coursesLoading = ref(true)

function now() {
  return new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
}

async function loadCourses() {
  coursesLoading.value = true
  try {
    const res = await useApi<any>('/admin/courses?per_page=50', { headers: authHeaders() })
    courses.value = res.data || []
  }
  catch {}
  finally { coursesLoading.value = false }
}

async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return
  input.value = ''
  messages.value.push({ role: 'user', content: text, time: now() })
  await scrollDown()
  loading.value = true
  try {
    const res = await useApi<any>('/ai/chat', {
      method: 'POST',
      headers: authHeaders(),
      body: { message: text, course_id: courseId.value || undefined },
    })
    messages.value.push({
      role: 'assistant',
      content: res.reply || res.message || 'Xin lỗi, tôi chưa có câu trả lời cho yêu cầu này.',
      time: now(),
    })
  }
  catch {
    messages.value.push({
      role: 'assistant',
      content: 'Không thể kết nối tới dịch vụ AI. Vui lòng thử lại sau.',
      time: now(),
    })
  }
  finally {
    loading.value = false
    await scrollDown()
  }
}

async function scrollDown() {
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

function clearChat() {
  messages.value = []
}

const SUGGESTIONS = [
  'Cho tôi xem tổng quan các khoá học đang hoạt động',
  'Khoá học nào có lượt đăng ký nhiều nhất?',
  'Có thể gợi ý khoá học về lập trình web không?',
  'Hệ thống hỗ trợ những danh mục học nào?',
]

onMounted(loadCourses)
</script>

<template>
  <AdminWorkspaceShell
    title="Trợ lý AI"
    description="Hỏi đáp về khoá học, học viên và hệ thống thông qua trí tuệ nhân tạo. Có thể chọn một khoá học cụ thể để nhận phân tích sâu hơn."
    :breadcrumb="['Trang chủ', 'Hỗ trợ', 'Trợ lý AI']"
  >
    <div class="chat-layout">
      <!-- Sidebar -->
      <aside class="chat-aside">
        <div class="dashboard-card chat-aside-inner">
          <div class="aside-section">
            <p class="section-kicker">Ngữ cảnh</p>
            <h4>Chọn khoá học</h4>
            <p style="font-size: 0.8rem; color: var(--muted); margin-top: 4px;">
              Để nhận phân tích chi tiết hơn về một khoá học cụ thể.
            </p>
            <select
              v-model="courseId"
              class="crud-select"
              style="margin-top: 12px; width: 100%;"
              :disabled="coursesLoading"
            >
              <option :value="null">— Không chọn khoá học —</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
          </div>

          <div class="aside-section" style="margin-top: 20px;">
            <p class="section-kicker">Gợi ý</p>
            <h4>Câu hỏi mẫu</h4>
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;">
              <button
                v-for="s in SUGGESTIONS"
                :key="s"
                type="button"
                class="suggestion-btn"
                @click="input = s"
              >
                {{ s }}
              </button>
            </div>
          </div>

          <div class="aside-section" style="margin-top: 20px; border-top: 1px solid var(--line); padding-top: 16px;">
            <button
              type="button"
              class="crud-secondary-btn"
              style="width: 100%;"
              :disabled="messages.length === 0"
              @click="clearChat"
            >
              Xoá lịch sử chat
            </button>
          </div>
        </div>
      </aside>

      <!-- Chat panel -->
      <div class="chat-panel dashboard-card">
        <!-- Messages -->
        <div ref="messagesEl" class="chat-messages">
          <!-- Empty state -->
          <div v-if="messages.length === 0" class="chat-empty">
            <span class="material-symbols-outlined" style="font-size: 56px; opacity: 0.12; display: block; margin-bottom: 16px;">smart_toy</span>
            <h3>Xin chào! Tôi là trợ lý AI của PTIT Sylva.</h3>
            <p>Hãy hỏi tôi về khoá học, thống kê hệ thống, hoặc gợi ý nội dung học tập.</p>
          </div>

          <!-- Message list -->
          <template v-else>
            <div
              v-for="(msg, i) in messages"
              :key="i"
              class="msg-row"
              :class="msg.role === 'user' ? 'is-user' : 'is-assistant'"
            >
              <div v-if="msg.role === 'assistant'" class="msg-avatar is-ai">
                <span class="material-symbols-outlined" style="font-size: 18px;">smart_toy</span>
              </div>
              <div class="msg-bubble">
                <p class="msg-text">{{ msg.content }}</p>
                <span class="msg-time">{{ msg.time }}</span>
              </div>
              <div v-if="msg.role === 'user'" class="msg-avatar is-user">
                <span class="material-symbols-outlined" style="font-size: 18px;">person</span>
              </div>
            </div>
            <div v-if="loading" class="msg-row is-assistant">
              <div class="msg-avatar is-ai">
                <span class="material-symbols-outlined" style="font-size: 18px;">smart_toy</span>
              </div>
              <div class="msg-bubble is-typing">
                <span class="typing-dot" />
                <span class="typing-dot" />
                <span class="typing-dot" />
              </div>
            </div>
          </template>
        </div>

        <!-- Input -->
        <div class="chat-input-bar">
          <div class="chat-input-wrap">
            <textarea
              v-model="input"
              rows="1"
              class="chat-input"
              placeholder="Nhập câu hỏi, ví dụ: Khoá học nào đang phổ biến nhất?"
              :disabled="loading"
              @keydown.enter.exact.prevent="send"
            />
            <button
              type="button"
              class="chat-send-btn"
              :disabled="!input.trim() || loading"
              @click="send"
            >
              <span class="material-symbols-outlined">send</span>
            </button>
          </div>
          <p style="font-size: 0.7rem; color: var(--muted); margin-top: 6px; text-align: center;">
            Enter để gửi · Shift+Enter xuống dòng · Phản hồi được tạo bởi AI, có thể không chính xác hoàn toàn.
          </p>
        </div>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.chat-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .chat-layout { grid-template-columns: 1fr; }
}

.chat-aside-inner {
  padding: 20px;
}
.aside-section h4 {
  font-size: 0.9rem;
  font-weight: 700;
  margin: 6px 0 0;
}

.suggestion-btn {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  font-size: 0.78rem;
  font-weight: 500;
  background: rgba(var(--green-rgb), 0.04);
  border: 1px solid rgba(var(--green-rgb), 0.1);
  border-radius: 10px;
  cursor: pointer;
  color: var(--text);
  transition: all 0.15s;
  line-height: 1.4;
}
.suggestion-btn:hover {
  background: rgba(var(--green-rgb), 0.1);
  border-color: var(--green);
}

/* Chat panel */
.chat-panel {
  display: flex;
  flex-direction: column;
  height: 640px;
  padding: 0;
  overflow: hidden;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 24px 20px 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.chat-empty {
  margin: auto;
  text-align: center;
  max-width: 360px;
  color: var(--muted);
}
.chat-empty h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
}
.chat-empty p {
  font-size: 0.875rem;
  line-height: 1.6;
}

/* Messages */
.msg-row {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  max-width: 80%;
}
.msg-row.is-user {
  align-self: flex-end;
  flex-direction: row-reverse;
}
.msg-row.is-assistant { align-self: flex-start; }

.msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.msg-avatar.is-ai {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
}
.msg-avatar.is-user {
  background: rgba(17,17,17,0.08);
  color: var(--muted);
}

.msg-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  max-width: 100%;
}
.msg-row.is-assistant .msg-bubble {
  background: rgba(17,17,17,0.04);
  border-radius: 4px 16px 16px 16px;
}
.msg-row.is-user .msg-bubble {
  background: var(--green);
  color: #fff;
  border-radius: 16px 4px 16px 16px;
}
.msg-text {
  font-size: 0.875rem;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}
.msg-time {
  display: block;
  font-size: 0.625rem;
  opacity: 0.6;
  margin-top: 4px;
  text-align: right;
}

/* Typing indicator */
.msg-bubble.is-typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 12px 16px;
}
.typing-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--muted);
  animation: typing-bounce 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
  40% { transform: translateY(-6px); opacity: 1; }
}

/* Input bar */
.chat-input-bar {
  padding: 14px 20px 16px;
  border-top: 1px solid var(--line);
  background: rgba(255,255,255,0.7);
}
.chat-input-wrap {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  background: rgba(17,17,17,0.04);
  border: 1px solid rgba(17,17,17,0.1);
  border-radius: 16px;
  padding: 10px 14px;
}
.chat-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  resize: none;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text);
  max-height: 120px;
  line-height: 1.5;
}
.chat-input::placeholder { color: var(--muted); }
.chat-send-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--green);
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}
.chat-send-btn:hover:not(:disabled) { background: var(--green-deep); }
.chat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.chat-send-btn .material-symbols-outlined { font-size: 18px; }
</style>
