<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

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
  <div class="chat-page">
    <header class="page-header"><div><h1>Trợ lý AI</h1><p>Tra cứu khóa học và phân tích dữ liệu hệ thống bằng hội thoại.</p></div><Button label="Xóa hội thoại" icon="pi pi-trash" severity="secondary" outlined :disabled="!messages.length" @click="clearChat" /></header>
    <div class="chat-layout">
      <aside class="sidebar">
        <Card>
          <template #title>Ngữ cảnh khóa học</template>
          <template #subtitle>Chọn một khóa học để nhận câu trả lời cụ thể hơn.</template>
          <template #content><Select v-model="courseId" :options="courses" option-label="title" option-value="id" placeholder="Không chọn khóa học" show-clear filter fluid :loading="coursesLoading" /></template>
        </Card>
        <Card>
          <template #title>Câu hỏi gợi ý</template>
          <template #content><div class="suggestions"><Button v-for="suggestion in SUGGESTIONS" :key="suggestion" :label="suggestion" severity="secondary" text @click="input = suggestion" /></div></template>
        </Card>
      </aside>

      <Card class="chat-card">
        <template #content>
          <div ref="messagesEl" class="messages">
            <div v-if="!messages.length" class="empty-state"><i class="pi pi-sparkles" /><h2>Xin chào! Tôi là trợ lý AI của PTIT Sylva.</h2><p>Hãy hỏi về khóa học, thống kê hệ thống hoặc nội dung học tập.</p></div>
            <template v-else>
              <div v-for="(message, index) in messages" :key="index" class="message-row" :class="{ user: message.role === 'user' }">
                <span class="avatar"><i class="pi" :class="message.role === 'assistant' ? 'pi-sparkles' : 'pi-user'" /></span>
                <div class="bubble"><p>{{ message.content }}</p><small>{{ message.time }}</small></div>
              </div>
              <div v-if="loading" class="message-row"><span class="avatar"><i class="pi pi-sparkles" /></span><div class="bubble typing"><i class="pi pi-spin pi-spinner" /> Đang trả lời...</div></div>
            </template>
          </div>
          <div class="composer">
            <Textarea v-model="input" auto-resize rows="2" placeholder="Nhập câu hỏi..." :disabled="loading" @keydown.enter.exact.prevent="send" />
            <Button icon="pi pi-send" aria-label="Gửi" :disabled="!input.trim() || loading" :loading="loading" @click="send" />
          </div>
          <small class="hint">Enter để gửi · Shift+Enter xuống dòng · Nội dung AI có thể chưa hoàn toàn chính xác.</small>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.chat-page{display:flex;min-height:calc(100vh - 8rem);flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.page-header h1{margin:0;color:var(--p-text-color);font-size:1.5rem;font-weight:700}.page-header p{margin:.3rem 0 0;color:var(--p-text-muted-color);font-size:.875rem}.chat-layout{display:grid;min-height:0;flex:1;grid-template-columns:18rem minmax(0,1fr);gap:1.25rem}.sidebar{display:flex;flex-direction:column;gap:1rem}.suggestions{display:flex;flex-direction:column;align-items:stretch;gap:.25rem}.suggestions :deep(.p-button){justify-content:flex-start;text-align:left;white-space:normal;font-size:.78rem}.chat-card{min-height:0}.chat-card :deep(.p-card-body),.chat-card :deep(.p-card-content){height:100%}.chat-card :deep(.p-card-content){display:flex;flex-direction:column;padding:0}.messages{display:flex;min-height:22rem;flex:1;flex-direction:column;gap:1rem;overflow-y:auto;padding:1.25rem}.empty-state{display:grid;max-width:28rem;margin:auto;place-items:center;text-align:center;color:var(--p-text-muted-color)}.empty-state i{font-size:2.5rem;color:var(--p-primary-color)}.empty-state h2{margin:1rem 0 .4rem;color:var(--p-text-color);font-size:1rem}.empty-state p{margin:0;font-size:.82rem}.message-row{display:flex;max-width:80%;align-items:flex-end;gap:.65rem}.message-row.user{align-self:flex-end;flex-direction:row-reverse}.avatar{display:grid;width:2rem;height:2rem;flex:0 0 auto;place-items:center;border-radius:50%;background:var(--p-primary-100);color:var(--p-primary-700)}.bubble{padding:.75rem 1rem;border:1px solid var(--p-content-border-color);border-radius:1rem 1rem 1rem .25rem;background:var(--p-surface-100);color:var(--p-text-color)}.user .bubble{border-color:var(--p-primary-color);border-radius:1rem 1rem .25rem 1rem;background:var(--p-primary-color);color:var(--p-primary-contrast-color)}.bubble p{margin:0;white-space:pre-wrap;font-size:.84rem;line-height:1.55}.bubble small{display:block;margin-top:.35rem;text-align:right;opacity:.65}.typing{font-size:.8rem}.composer{display:flex;align-items:flex-end;gap:.75rem;padding:1rem;border-top:1px solid var(--p-content-border-color);background:var(--p-surface-50)}.composer :deep(.p-textarea){min-height:2.75rem;max-height:8rem;flex:1;resize:none}.hint{display:block;padding:0 1rem 1rem;text-align:center;color:var(--p-text-muted-color)}
@media(max-width:800px){.chat-page{min-height:auto}.page-header{align-items:flex-start;flex-direction:column}.chat-layout{grid-template-columns:1fr}.message-row{max-width:92%}}
</style>
