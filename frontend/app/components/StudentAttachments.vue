<template>
  <section class="space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Lesson Files</p>
        <h3 class="mt-1 text-xl font-headline font-bold text-on-surface">Tài liệu đính kèm</h3>
      </div>
      <span class="rounded-full border border-surface-dim/40 bg-surface-low px-3 py-1 text-xs font-bold text-on-surface-variant">
        {{ attachments.length }} file
      </span>
    </div>

    <div v-if="loading" class="grid gap-4">
      <div v-for="item in 3" :key="item" class="h-24 rounded-[1.5rem] bg-surface-high animate-pulse" />
    </div>

    <div v-else-if="attachments.length === 0" class="rounded-[2rem] border border-dashed border-surface-dim/40 bg-surface-low px-6 py-12 text-center">
      <span class="material-symbols-outlined text-5xl text-outline/40">attach_file</span>
      <p class="mt-4 text-base font-semibold text-on-surface">Chưa có tài liệu cho bài học này</p>
      <p class="mt-2 text-sm text-on-surface-variant">Khi giảng viên tải lên slide, file bài tập hoặc biểu mẫu, chúng sẽ xuất hiện ở đây.</p>
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="file in attachments"
        :key="file.id"
        class="flex flex-col gap-4 rounded-[1.6rem] border border-surface-dim/30 bg-surface-lowest p-5 shadow-sm transition hover:border-primary/20 hover:shadow-md sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex min-w-0 items-center gap-4">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <span class="material-symbols-outlined">{{ fileIcon(file.mime_type) }}</span>
          </div>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-on-surface">{{ file.original_name }}</p>
            <p class="mt-1 text-xs text-on-surface-variant">{{ file.mime_type || 'Tài liệu' }} • {{ file.file_size || 'N/A' }}</p>
          </div>
        </div>

        <button
          @click="downloadFile(file)"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-surface-dim/40 bg-surface-low px-5 text-sm font-bold text-on-surface transition hover:border-primary/30 hover:text-primary"
        >
          Tải xuống
        </button>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const props = defineProps<{
  courseId: number
  lessonId: number
}>()

const auth = useAuthStore()
const loading = ref(true)
const attachments = ref<any[]>([])

watch(() => props.lessonId, () => {
  loadAttachments()
})

onMounted(() => {
  loadAttachments()
})

function fileIcon(mimeType?: string) {
  if (!mimeType) return 'description'
  if (mimeType.includes('pdf')) return 'picture_as_pdf'
  if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'table_chart'
  if (mimeType.includes('word') || mimeType.includes('document')) return 'article'
  if (mimeType.includes('presentation')) return 'slideshow'
  if (mimeType.includes('zip')) return 'folder_zip'
  return 'description'
}

async function loadAttachments() {
  loading.value = true
  try {
    const res = await useApi<{ attachments: any[] }>(`/courses/${props.courseId}/lessons/${props.lessonId}/attachments`, { token: auth.token })
    attachments.value = res.attachments || []
  } catch (e: any) {
    if (e?.response?.status !== 404) {
      console.error('Failed to load attachments', e)
    }
  } finally {
    loading.value = false
  }
}

async function downloadFile(file: any) {
  try {
    const res = await useApi<{ url: string }>(`/courses/${props.courseId}/lessons/${props.lessonId}/attachments/${file.id}/download`, { token: auth.token })
    const a = document.createElement('a')
    a.href = res.url
    a.target = '_blank'
    a.download = file.original_name
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  } catch (e) {
    alert('Không thể tải file lúc này.')
  }
}
</script>
