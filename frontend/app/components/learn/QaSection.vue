<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore, type CourseQa } from '~/stores/course'

const props = defineProps<{
  courseId: number
  lessonId: number
}>()

const auth = useAuthStore()
const courseStore = useCourseStore()
const qas = ref<CourseQa[]>([])
const loading = ref(true)
const showForm = ref(false)
const submitting = ref(false)

const form = ref({
  subject: '',
  content: '',
})
const formError = ref('')

const activeQaId = ref<number | null>(null)
const replyContent = ref('')
const canAsk = computed(() => auth.isLoggedIn)

async function loadQas() {
  loading.value = true
  try {
    qas.value = await courseStore.fetchQas(props.courseId, props.lessonId)
  } catch (e) {
    console.error('Failed to load Q&A', e)
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  formError.value = ''
  if (!form.value.subject.trim()) {
    formError.value = 'Vui lòng nhập tiêu đề câu hỏi.'
    return
  }
  if (!form.value.content.trim()) {
    formError.value = 'Vui lòng nhập nội dung chi tiết câu hỏi.'
    return
  }
  submitting.value = true
  try {
    const newQa = await courseStore.createQa(props.courseId, {
      ...form.value,
      lesson_id: props.lessonId,
    })
    qas.value.unshift({ ...newQa, replies: [] })
    showForm.value = false
    form.value = { subject: '', content: '' }
  } catch (e: any) {
    formError.value = e?.data?.message || 'Không thể gửi câu hỏi. Vui lòng thử lại.'
  } finally {
    submitting.value = false
  }
}

async function handleReply(qaId: number) {
  if (!replyContent.value.trim()) return
  submitting.value = true
  try {
    const reply = await courseStore.createQaReply(props.courseId, qaId, replyContent.value)
    const qa = qas.value.find(q => q.id === qaId)
    if (qa) {
      if (!qa.replies) qa.replies = []
      qa.replies.push(reply)
    }
    replyContent.value = ''
    activeQaId.value = null
  } catch {
    alert('Không thể gửi phản hồi.')
  } finally {
    submitting.value = false
  }
}

watch(() => props.lessonId, loadQas)
onMounted(loadQas)
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-[2rem] border border-surface-dim/40 bg-surface-lowest p-6 shadow-sm">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Trao đổi với giảng viên</p>
          <h3 class="mt-2 text-xl font-bold text-on-surface">Hỏi đáp Bài học ({{ qas.length }})</h3>
          <p class="mt-1 text-sm text-on-surface-variant">Đặt câu hỏi theo đúng bài học đang xem để giảng viên và học viên khác phản hồi nhanh hơn.</p>
        </div>
        <button
          v-if="canAsk"
          type="button"
          class="inline-flex min-w-[180px] items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
          @click="showForm = !showForm"
        >
          {{ showForm ? 'Hủy đặt câu hỏi' : 'Đặt câu hỏi mới' }}
        </button>
        <NuxtLink
          v-else
          to="/login"
          class="inline-flex min-w-[180px] items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
        >
          Đăng nhập để hỏi
        </NuxtLink>
      </div>

      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform -translate-y-4 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform -translate-y-4 opacity-0"
      >
        <div v-if="showForm && canAsk" class="mt-5 rounded-2xl border border-primary/20 bg-primary/5 p-5">
          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-bold text-on-surface ml-1">Tiêu đề <span class="text-error">*</span></label>
              <input
                v-model="form.subject"
                type="text"
                class="w-full rounded-xl border border-surface-dim bg-surface-lowest px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                placeholder="VD: Chưa hiểu đoạn validate trong ví dụ này"
              >
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-bold text-on-surface ml-1">Chi tiết câu hỏi <span class="text-error">*</span></label>
              <textarea
                v-model="form.content"
                rows="4"
                class="w-full rounded-xl border border-surface-dim bg-surface-lowest px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                placeholder="Mô tả kỹ điều bạn đang vướng, đã thử gì rồi, và mong muốn được hỗ trợ thế nào."
              ></textarea>
            </div>
            <p v-if="formError" class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error">{{ formError }}</p>
            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="submitting"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <span v-if="submitting" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                Gửi câu hỏi
              </button>
            </div>
          </form>
        </div>
      </Transition>

      <div v-if="!canAsk" class="mt-5 rounded-2xl border border-dashed border-surface-dim bg-surface-low/50 px-4 py-4 text-sm text-on-surface-variant">
        Bạn cần đăng nhập và ghi danh khóa học để đặt câu hỏi hoặc phản hồi.
      </div>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-32 bg-surface-low rounded-2xl animate-pulse"></div>
    </div>

    <div v-else-if="qas.length === 0" class="text-center py-12 bg-surface-lowest rounded-2xl border border-dashed border-surface-dim">
      <span class="material-symbols-outlined text-4xl text-outline/30 mb-2">contact_support</span>
      <p class="text-sm font-medium text-outline">Bài học này chưa có thảo luận nào. Hãy bắt đầu ngay!</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="qa in qas" :key="qa.id" class="bg-surface-lowest border border-surface-dim rounded-2xl overflow-hidden shadow-sm transition-all hover:border-primary/20">
        <div class="p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
              {{ qa.user?.name?.charAt(0) || 'U' }}
            </div>
            <div>
              <p class="text-sm font-bold text-on-surface">{{ qa.user?.name }}</p>
              <p class="text-[10px] text-outline">{{ new Date(qa.created_at).toLocaleDateString('vi-VN') }}</p>
            </div>
          </div>
          <h4 class="font-bold text-on-surface mb-2">{{ qa.subject }}</h4>
          <p class="text-sm text-on-surface-variant leading-relaxed">{{ qa.content }}</p>

          <div class="mt-4 flex items-center gap-4">
            <button
              @click="activeQaId = activeQaId === qa.id ? null : qa.id"
              class="text-xs font-bold text-primary hover:underline flex items-center gap-1"
            >
              <span class="material-symbols-outlined text-[14px]">reply</span>
              Phản hồi ({{ qa.replies?.length || 0 }})
            </button>
          </div>
        </div>

        <div v-if="activeQaId === qa.id" class="bg-surface-low/50 border-t border-surface-dim p-5 space-y-4">
          <div v-for="reply in qa.replies" :key="reply.id" class="flex gap-3">
            <div class="w-6 h-6 rounded-full bg-secondary/10 flex items-center justify-center text-secondary font-bold text-[10px] shrink-0">
              {{ reply.user?.name?.charAt(0) || 'U' }}
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold text-on-surface">{{ reply.user?.name }}</span>
                <span class="text-[10px] text-outline">{{ new Date(reply.created_at).toLocaleDateString('vi-VN') }}</span>
              </div>
              <p class="text-xs text-on-surface-variant leading-relaxed bg-surface-lowest p-3 rounded-xl border border-surface-dim/50">{{ reply.content }}</p>
            </div>
          </div>

          <div v-if="canAsk" class="flex gap-2 mt-4 pt-4 border-t border-surface-dim/30">
            <input
              v-model="replyContent"
              type="text"
              placeholder="Viết phản hồi của bạn..."
              class="flex-1 bg-surface-lowest border border-surface-dim rounded-xl px-4 py-2 text-xs focus:ring-1 focus:ring-primary outline-none"
              @keyup.enter="handleReply(qa.id)"
            />
            <button
              type="button"
              :disabled="submitting"
              class="inline-flex items-center justify-center gap-1 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-dark transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
              @click="handleReply(qa.id)"
            >
              <span v-if="submitting" class="material-symbols-outlined animate-spin text-[14px]">progress_activity</span>
              Gửi
            </button>
          </div>
          <div v-else class="mt-4 pt-4 border-t border-surface-dim/30 text-xs text-on-surface-variant">
            Đăng nhập để phản hồi trong thảo luận này.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
