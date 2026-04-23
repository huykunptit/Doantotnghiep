<template>
  <div class="fixed bottom-6 right-6 z-50">
    <!-- Floating Button -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 scale-75"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-75"
    >
      <button
        v-if="!isOpen"
        @click="openChat"
        class="group relative w-14 h-14 rounded-2xl cta-gradient text-white shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center"
      >
        <span class="text-2xl">🤖</span>
        <!-- Pulse dot -->
        <span class="absolute -top-0.5 -right-0.5 flex h-3.5 w-3.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary opacity-60"></span>
          <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-secondary border-2 border-white"></span>
        </span>
        <!-- Tooltip -->
        <div class="absolute right-[calc(100%+12px)] top-1/2 -translate-y-1/2 bg-on-surface text-white px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-md">
          Hỏi AI trợ giúp
          <div class="absolute right-[-4px] top-1/2 -translate-y-1/2 w-2 h-2 bg-on-surface rotate-45"></div>
        </div>
      </button>
    </Transition>

    <!-- Chat Window -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 scale-90 translate-y-4"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 scale-100 translate-y-0"
      leave-to-class="opacity-0 scale-90 translate-y-4"
    >
      <div
        v-if="isOpen"
        class="w-[360px] sm:w-[400px] h-[520px] flex flex-col rounded-2xl bg-surface-lowest border border-surface-dim shadow-2xl overflow-hidden origin-bottom-right"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3.5 cta-gradient text-white shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-lg backdrop-blur-sm">
              🤖
            </div>
            <div>
              <p class="font-bold text-sm leading-tight">EduPress AI</p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                <span class="text-[10px] font-medium text-white/80">Đang trực tuyến</span>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button @click="clearChat" class="w-8 h-8 rounded-lg hover:bg-white/15 flex items-center justify-center transition-colors" title="Xoá lịch sử">
              <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
            </button>
            <button @click="isOpen = false" class="w-8 h-8 rounded-lg hover:bg-white/15 flex items-center justify-center transition-colors" title="Đóng">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messageBox" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-surface-low/30 scroll-smooth">
          <div v-for="(msg, idx) in messages" :key="idx" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start gap-2'">
            <!-- Bot avatar -->
            <div v-if="msg.role === 'assistant'" class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 mt-0.5 text-sm">
              🤖
            </div>
            <div
              class="max-w-[78%] px-3.5 py-2.5 text-[13px] leading-relaxed"
              :class="msg.role === 'user'
                ? 'bg-primary text-white rounded-2xl rounded-br-md shadow-sm'
                : 'bg-surface-lowest text-on-surface rounded-2xl rounded-bl-md border border-surface-dim/40 shadow-sm'"
            >
              <p class="whitespace-pre-wrap">{{ msg.text }}</p>
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="flex gap-2">
            <div class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 text-sm">🤖</div>
            <div class="bg-surface-lowest px-4 py-3 rounded-2xl rounded-bl-md border border-surface-dim/40 shadow-sm flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 bg-outline/60 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
              <span class="w-1.5 h-1.5 bg-outline/60 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
              <span class="w-1.5 h-1.5 bg-outline/60 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
            </div>
          </div>
        </div>

        <!-- Quick Actions (when few messages) -->
        <div v-if="messages.length <= 1 && !loading" class="px-4 pb-2 flex gap-2 flex-wrap shrink-0">
          <button
            v-for="q in quickQuestions"
            :key="q"
            @click="sendQuick(q)"
            class="text-[11px] font-medium px-3 py-1.5 rounded-full border border-primary/20 text-primary bg-primary/5 hover:bg-primary/10 transition-colors"
          >
            {{ q }}
          </button>
        </div>

        <!-- Input -->
        <div class="px-4 py-3 border-t border-surface-dim/30 bg-surface-lowest shrink-0">
          <form @submit.prevent="sendMessage" class="flex items-center gap-2">
            <input
              ref="chatInput"
              v-model="input"
              type="text"
              placeholder="Nhập câu hỏi..."
              class="flex-1 bg-surface-low rounded-xl px-4 py-2.5 text-sm text-on-surface placeholder-outline/60 border-none outline-none focus:ring-2 focus:ring-primary/30 transition-all"
              @keydown.escape="isOpen = false"
            />
            <button
              type="submit"
              :disabled="!input.trim() || loading"
              class="w-10 h-10 rounded-xl cta-gradient text-white flex items-center justify-center hover:shadow-md active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
            >
              <span class="material-symbols-outlined text-[18px]">send</span>
            </button>
          </form>
          <p class="text-[10px] text-outline text-center mt-2">AI có thể đưa ra câu trả lời không chính xác</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue'
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

const messages = reactive<Array<{ role: 'user' | 'assistant'; text: string }>>([
  { role: 'assistant', text: 'Xin chào! Tôi là trợ lý AI của EduPress. Tôi có thể giúp bạn tìm khóa học, giải đáp thắc mắc hoặc tư vấn lộ trình học tập. Hãy hỏi bất cứ điều gì nhé!' },
])

function openChat() {
  isOpen.value = true
  nextTick(() => chatInput.value?.focus())
}

function clearChat() {
  messages.splice(0, messages.length)
  messages.push({ role: 'assistant', text: 'Đã xoá lịch sử. Bạn muốn hỏi gì tiếp?' })
}

function sendQuick(question: string) {
  input.value = question
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isLoggedIn || !auth.token) {
    messages.push({ role: 'assistant', text: 'Vui lòng đăng nhập để sử dụng trợ lý AI nhé.' })
    input.value = ''
    scrollToBottom()
    return
  }

  messages.push({ role: 'user', text })
  input.value = ''
  loading.value = true
  scrollToBottom()

  try {
    const res = await useApi<any>('/ai/chat', {
      method: 'POST',
      body: { message: text },
      token: auth.token,
    })
    messages.push({ role: 'assistant', text: res.reply || 'Tôi chưa có câu trả lời cho câu hỏi này.' })
  } catch {
    messages.push({ role: 'assistant', text: 'Xin lỗi, hệ thống AI đang bận. Vui lòng thử lại sau ít phút nhé!' })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messageBox.value) {
      messageBox.value.scrollTo({ top: messageBox.value.scrollHeight, behavior: 'smooth' })
    }
  })
}

// Close on Escape key
if (import.meta.client) {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen.value) isOpen.value = false
  })
}
</script>
