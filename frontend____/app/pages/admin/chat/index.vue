<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'

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
  <div class="flex flex-col h-[calc(100vh-100px)]">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-6 py-4 border-b border-[var(--line)] bg-white shrink-0">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Hỗ trợ</p>
        <h1 class="text-xl font-bold tracking-tight text-[var(--text)]">Trợ lý AI</h1>
      </div>
    </div>

    <!-- Main Content Panel -->
    <div class="flex-1 flex flex-col md:flex-row gap-5 p-5 min-h-0 overflow-hidden">
      <!-- Sidebar -->
      <aside class="w-full md:w-72 flex flex-col gap-4 shrink-0 overflow-y-auto md:max-h-full">
        <!-- Ngữ cảnh -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-widest text-[var(--muted)]">Ngữ cảnh</p>
            <h4 class="text-sm font-bold text-[var(--text)] mt-0.5">Chọn khoá học</h4>
            <p class="text-xs text-[var(--muted)] mt-1">Để nhận phân tích chi tiết hơn về một khoá học cụ thể.</p>
          </div>
          <select
            v-model="courseId"
            class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full"
            :disabled="coursesLoading"
          >
            <option :value="null">— Không chọn khoá học —</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
          </select>
        </div>

        <!-- Gợi ý -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-widest text-[var(--muted)]">Gợi ý</p>
            <h4 class="text-sm font-bold text-[var(--text)] mt-0.5">Câu hỏi mẫu</h4>
          </div>
          <div class="flex flex-col gap-2">
            <button
              v-for="s in SUGGESTIONS"
              :key="s"
              type="button"
              class="w-full text-left p-3 text-xs font-semibold bg-[rgba(29,158,117,0.06)] border border-[rgba(29,158,117,0.12)] hover:border-[#1d9e75] rounded-xl text-[var(--text)] hover:bg-[rgba(29,158,117,0.12)] transition-colors leading-relaxed"
              @click="input = s"
            >
              {{ s }}
            </button>
          </div>
        </div>

        <!-- Action -->
        <button
          type="button"
          class="w-full inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
          :disabled="messages.length === 0"
          @click="clearChat"
        >
          Xoá lịch sử chat
        </button>
      </aside>

      <!-- Chat panel -->
      <div class="flex-1 bg-white border border-[var(--line)] rounded-2xl shadow-sm overflow-hidden flex flex-col h-full min-h-0">
        <!-- Messages -->
        <div ref="messagesEl" class="flex-1 overflow-y-auto p-5 flex flex-col gap-4">
          <!-- Empty state -->
          <div v-if="messages.length === 0" class="m-auto text-center max-w-sm flex flex-col items-center gap-3 text-[var(--muted)]">
            <span class="material-symbols-outlined text-5xl opacity-30">smart_toy</span>
            <h3 class="text-base font-bold text-[var(--text)]">Xin chào! Tôi là trợ lý AI của PTIT Sylva.</h3>
            <p class="text-xs leading-relaxed">Hãy hỏi tôi về khoá học, thống kê hệ thống, hoặc gợi ý nội dung học tập.</p>
          </div>

          <!-- Message list -->
          <template v-else>
            <div
              v-for="(msg, i) in messages"
              :key="i"
              class="flex gap-3 max-w-[85%] items-end"
              :class="msg.role === 'user' ? 'self-end flex-row-reverse' : 'self-start'"
            >
              <div 
                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                :class="msg.role === 'assistant' ? 'bg-[rgba(29,158,117,0.1)] text-[#085041]' : 'bg-[rgba(0,0,0,0.06)] text-[var(--muted)]'"
              >
                <i class="pi" :class="msg.role === 'assistant' ? 'pi-android' : 'pi-user'" />
              </div>
              <div 
                class="p-3 rounded-2xl max-w-full shadow-sm"
                :class="msg.role === 'assistant' 
                  ? 'bg-[var(--surface)] text-[var(--text)] rounded-bl-none border border-[var(--line)]' 
                  : 'bg-[#1d9e75] text-white rounded-br-none'"
              >
                <p class="text-xs leading-relaxed whitespace-pre-wrap">{{ msg.content }}</p>
                <span class="block text-[9px] opacity-60 mt-1 text-right">{{ msg.time }}</span>
              </div>
            </div>
            <div v-if="loading" class="flex gap-3 max-w-[85%] items-end self-start">
              <div class="w-8 h-8 rounded-full flex items-center justify-center bg-[rgba(29,158,117,0.1)] text-[#085041] shrink-0">
                <i class="pi pi-android" />
              </div>
              <div class="p-4 rounded-2xl bg-[var(--surface)] text-[var(--text)] rounded-bl-none border border-[var(--line)] flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-[var(--muted)] animate-bounce" style="animation-delay: 0.1s" />
                <span class="w-2 h-2 rounded-full bg-[var(--muted)] animate-bounce" style="animation-delay: 0.2s" />
                <span class="w-2 h-2 rounded-full bg-[var(--muted)] animate-bounce" style="animation-delay: 0.3s" />
              </div>
            </div>
          </template>
        </div>

        <!-- Input -->
        <div class="p-4 border-t border-[var(--line)] bg-[var(--surface)] shrink-0">
          <div class="flex items-end gap-3 bg-white border border-[var(--line)] rounded-2xl p-2 focus-within:border-[#1d9e75] focus-within:ring-2 focus-within:ring-[rgba(29,158,117,0.15)] transition-all">
            <textarea
              v-model="input"
              rows="1"
              class="flex-1 border-none bg-transparent outline-none resize-none text-sm text-[var(--text)] placeholder:text-[var(--muted)] max-h-28 py-1.5 px-2.5 leading-relaxed"
              placeholder="Nhập câu hỏi, ví dụ: Khoá học nào đang phổ biến nhất?"
              :disabled="loading"
              @keydown.enter.exact.prevent="send"
            />
            <button
              type="button"
              class="w-9 h-9 rounded-xl bg-[#1d9e75] hover:bg-[#17876a] text-white flex items-center justify-center transition-colors shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!input.trim() || loading"
              @click="send"
            >
              <i class="pi pi-send text-sm" />
            </button>
          </div>
          <p class="text-[10px] text-[var(--muted)] mt-2 text-center">
            Enter để gửi · Shift+Enter xuống dòng · Phản hồi được tạo bởi AI, có thể không chính xác hoàn toàn.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
