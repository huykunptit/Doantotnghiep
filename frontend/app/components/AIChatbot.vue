<template>
  <div class="fixed bottom-6 right-6 z-50">
    <!-- Floating Button -->
    <Transition
      enter-active-class="transition-all ease-out duration-300"
      enter-from-class="opacity-0 translate-y-4 scale-90"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition-all ease-in duration-200"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-4 scale-90"
    >
      <button
        v-if="!isOpen"
        @click="openChat"
        class="group relative w-14 h-14 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-xl border border-gray-200/50 dark:border-gray-700/50 shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center"
      >
        <span class="material-symbols-outlined text-2xl text-gray-700 dark:text-gray-200">blur_on</span>
        
        <!-- Pulse dot -->
        <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-60"></span>
          <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-blue-500 border-2 border-white dark:border-gray-800"></span>
        </span>
        
        <!-- Tooltip -->
        <div class="absolute right-[calc(100%+16px)] top-1/2 -translate-y-1/2 bg-gray-900/90 dark:bg-white/90 backdrop-blur-sm text-white dark:text-gray-900 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-md border border-white/10">
          Trợ lý Hệ thống
        </div>
      </button>
    </Transition>

    <!-- Chat Window -->
    <Transition
      enter-active-class="transition-all ease-out duration-300"
      enter-from-class="opacity-0 scale-95 translate-y-4"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition-all ease-in duration-200"
      leave-from-class="opacity-100 scale-100 translate-y-0"
      leave-to-class="opacity-0 scale-95 translate-y-4"
    >
      <div
        v-if="isOpen"
        class="w-[360px] sm:w-[380px] h-[580px] flex flex-col rounded-2xl bg-white/70 dark:bg-gray-900/70 backdrop-blur-2xl border border-white/40 dark:border-gray-700/50 shadow-[0_12px_40px_rgb(0,0,0,0.12)] dark:shadow-[0_12px_40px_rgb(0,0,0,0.4)] overflow-hidden origin-bottom-right"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-white/40 dark:bg-gray-800/40 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700/50 shrink-0">
          <div class="flex items-center gap-3">
            <!-- macOS style dots -->
            <div class="flex gap-1.5 group mr-1">
              <button @click="isOpen = false" class="w-3 h-3 rounded-full bg-red-400 hover:bg-red-500 transition-colors shadow-sm flex items-center justify-center text-[8px] text-red-900 opacity-80 hover:opacity-100" title="Đóng">
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 text-[10px] font-bold">close</span>
              </button>
              <button @click="clearChat" class="w-3 h-3 rounded-full bg-yellow-400 hover:bg-yellow-500 transition-colors shadow-sm flex items-center justify-center text-[8px] text-yellow-900 opacity-80 hover:opacity-100" title="Xóa lịch sử">
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 text-[10px] font-bold">remove</span>
              </button>
              <button class="w-3 h-3 rounded-full bg-green-400 hover:bg-green-500 transition-colors shadow-sm flex items-center justify-center text-[8px] text-green-900 opacity-80 hover:opacity-100">
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 text-[10px] font-bold">open_in_full</span>
              </button>
            </div>
            
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center shadow-inner border border-white/50 dark:border-gray-500/50">
                <span class="material-symbols-outlined text-[18px] text-gray-700 dark:text-gray-200">memory</span>
              </div>
              <div class="flex flex-col">
                <span class="font-semibold text-[13px] text-gray-800 dark:text-gray-100 leading-tight">Trợ lý Hệ thống</span>
                <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">Đang hoạt động</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messageBox" class="flex-1 overflow-y-auto px-4 py-5 space-y-5 scroll-smooth">
          <div v-for="(msg, idx) in messages" :key="idx" class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
            <div
              class="max-w-[85%] px-4 py-2.5 text-[14px] leading-relaxed shadow-sm"
              :class="msg.role === 'user'
                ? 'bg-blue-500 text-white rounded-2xl rounded-tr-sm'
                : 'bg-white/80 dark:bg-gray-800/80 text-gray-800 dark:text-gray-100 rounded-2xl rounded-tl-sm border border-gray-100 dark:border-gray-700/50 backdrop-blur-sm'"
            >
              <p class="whitespace-pre-wrap">{{ msg.text }}</p>
            </div>
            <span class="text-[10px] text-gray-400 mt-1.5 px-1">{{ msg.role === 'user' ? 'Bạn' : 'Hệ thống' }}</span>
          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="flex flex-col items-start">
            <div class="bg-white/80 dark:bg-gray-800/80 px-4 py-3.5 rounded-2xl rounded-tl-sm border border-gray-100 dark:border-gray-700/50 shadow-sm flex items-center gap-1.5 backdrop-blur-sm">
              <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
              <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
              <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
            </div>
            <span class="text-[10px] text-gray-400 mt-1.5 px-1">Đang xử lý...</span>
          </div>
        </div>

        <!-- Quick Actions -->
        <div v-if="messages.length <= 1 && !loading" class="px-4 pb-3 flex gap-2 overflow-x-auto no-scrollbar shrink-0">
          <button
            v-for="q in quickQuestions"
            :key="q"
            @click="sendQuick(q)"
            class="text-[12px] font-medium px-4 py-1.5 rounded-full border border-gray-200/80 dark:border-gray-700/80 text-gray-600 dark:text-gray-300 bg-white/50 dark:bg-gray-800/50 hover:bg-white dark:hover:bg-gray-700 hover:shadow-sm transition-all whitespace-nowrap backdrop-blur-sm"
          >
            {{ q }}
          </button>
        </div>

        <!-- Input -->
        <div class="p-3 bg-white/40 dark:bg-gray-800/40 backdrop-blur-md border-t border-gray-200/50 dark:border-gray-700/50 shrink-0">
          <form @submit.prevent="sendMessage" class="relative flex items-center gap-2 bg-white/60 dark:bg-gray-900/60 rounded-xl border border-gray-200/80 dark:border-gray-700/80 p-1 pl-3 shadow-inner focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500/30 transition-all">
            <input
              ref="chatInput"
              v-model="input"
              type="text"
              placeholder="Nhập lệnh hoặc câu hỏi..."
              class="flex-1 bg-transparent py-2 text-[14px] text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 border-none outline-none"
              @keydown.escape="isOpen = false"
            />
            <button
              type="submit"
              :disabled="!input.trim() || loading"
              class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center hover:bg-blue-600 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
            >
              <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
            </button>
          </form>
          <div class="text-center mt-2.5">
            <span class="text-[9px] text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">System AI Module • AI có thể sai sót</span>
          </div>
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
  { role: 'assistant', text: 'Hệ thống AI đã sẵn sàng. Tôi có thể giúp bạn tìm khóa học, giải đáp thắc mắc hoặc tư vấn lộ trình học tập. Nhập yêu cầu của bạn bên dưới.' },
])

function openChat() {
  isOpen.value = true
  nextTick(() => chatInput.value?.focus())
}

function clearChat() {
  messages.splice(0, messages.length)
  messages.push({ role: 'assistant', text: 'Đã xoá bộ nhớ đệm. Bạn cần hỗ trợ gì thêm?' })
}

function sendQuick(question: string) {
  input.value = question
  sendMessage()
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || loading.value) return

  if (!auth.isLoggedIn || !auth.token) {
    messages.push({ role: 'assistant', text: 'Vui lòng đăng nhập để sử dụng Trợ lý Hệ thống.' })
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
    messages.push({ role: 'assistant', text: res.reply || 'Hệ thống chưa có phản hồi cho yêu cầu này.' })
  } catch {
    messages.push({ role: 'assistant', text: 'Lỗi kết nối. Module AI hiện không khả dụng, vui lòng thử lại sau.' })
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

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
