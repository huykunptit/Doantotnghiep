<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const loadingFiles = ref(false)
const enrollments = ref<any[]>([])
const selectedCourseId = ref<number | string | null>(null)
const attachments = ref<Record<string, any[]>>({})
const expanded = ref<Set<string | number>>(new Set())
const search = ref('')

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  try {
    const res = await useApi<any[]>('/user/enrollments', { headers: h })
    enrollments.value = res || []
    if (enrollments.value.length) {
      selectedCourseId.value = enrollments.value[0].course?.id || enrollments.value[0].course_id || enrollments.value[0].id
    }
  } catch {
    // error fallback
  } finally {
    loading.value = false
  }
})

watch(selectedCourseId, async (id) => {
  if (!id || attachments.value[id]) return
  loadingFiles.value = true
  const h = { Authorization: `Bearer ${auth.token}` }
  try {
    const res = await useApi<any>(`/courses/${id}/lessons?include_attachments=1`, { headers: h })
    const lessons: any[] = Array.isArray(res) ? res : (res?.data || res?.lessons || [])
    attachments.value[id] = lessons.map(l => ({
      lessonId: l.id,
      lessonTitle: l.title || l.name,
      files: l.attachments || [],
    })).filter(g => g.files.length > 0)
  } catch {
    // fallback
  } finally {
    loadingFiles.value = false
  }
})

const selectedEnrollment = computed(() =>
  enrollments.value.find(e => (e.course?.id || e.course_id || e.id) == selectedCourseId.value)
)

const courseAttachments = computed(() =>
  selectedCourseId.value ? (attachments.value[selectedCourseId.value] || []) : []
)

const filteredGroups = computed(() => {
  if (!search.value) return courseAttachments.value
  const q = search.value.toLowerCase()
  return courseAttachments.value
    .map(g => ({
      ...g,
      files: g.files.filter((f: any) => (f.name || f.filename || f.title || '').toLowerCase().includes(q)),
    }))
    .filter(g => g.files.length > 0)
})

function toggleLesson(id: string | number) {
  if (expanded.value.has(id)) expanded.value.delete(id)
  else expanded.value.add(id)
  expanded.value = new Set(expanded.value)
}

function fileIcon(file: any) {
  const name = (file.name || file.filename || file.original_name || '').toLowerCase()
  if (name.endsWith('.pdf')) return 'picture_as_pdf'
  if (name.match(/\.(mp4|mov|avi|mkv|webm)$/)) return 'play_circle'
  if (name.match(/\.(zip|rar|7z)$/)) return 'archive'
  if (name.match(/\.(docx?|odt)$/)) return 'description'
  if (name.match(/\.(xlsx?|csv)$/)) return 'table_chart'
  if (name.match(/\.(pptx?|ppt)$/)) return 'present_to_all'
  return 'attachment'
}

function fileIconClass(file: any) {
  const name = (file.name || file.filename || file.original_name || '').toLowerCase()
  if (name.endsWith('.pdf')) return 'bg-red-50 text-red-600'
  if (name.match(/\.(mp4|mov|avi|mkv|webm)$/)) return 'bg-sky-50 text-sky-600'
  if (name.match(/\.(zip|rar|7z)$/)) return 'bg-amber-50 text-amber-600'
  if (name.match(/\.(docx?|odt)$/)) return 'bg-blue-50 text-blue-600'
  if (name.match(/\.(xlsx?|csv)$/)) return 'bg-emerald-50 text-emerald-600'
  return 'bg-slate-50 text-slate-600'
}

function fileExt(file: any) {
  const name = file.name || file.filename || file.original_name || ''
  return name.split('.').pop()?.toUpperCase() || 'FILE'
}

function formatSize(bytes?: number) {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

const totalFiles = computed(() =>
  courseAttachments.value.reduce((s, g) => s + g.files.length, 0)
)
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Tài nguyên</p>
      <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Thư viện tài liệu</h1>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6 animate-pulse">
      <div class="h-64 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl" />
      <div class="h-96 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl" />
    </div>

    <div v-else-if="enrollments.length" class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6 items-start">
      <!-- Sidebar: course list -->
      <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex flex-col gap-2">
        <p class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider px-2 mb-1">Khóa học</p>
        <button
          v-for="e in enrollments"
          :key="e.id"
          class="flex items-center gap-2.5 w-full p-2.5 rounded-xl text-left border transition-all"
          :class="selectedCourseId == (e.course?.id || e.course_id || e.id) ? 'bg-emerald-50/70 border-emerald-200 text-[#085041]' : 'border-transparent hover:bg-[var(--surface)] text-[var(--text)]'"
          @click="selectedCourseId = e.course?.id || e.course_id || e.id"
        >
          <div class="w-8 h-8 rounded-lg bg-[var(--surface)] flex items-center justify-center overflow-hidden flex-shrink-0 border border-[var(--line)]">
            <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title" class="w-full h-full object-cover">
            <span v-else class="material-symbols-outlined text-sm">book</span>
          </div>
          <p class="text-xs font-bold truncate flex-1">{{ e.course?.title || e.title || 'Khóa học' }}</p>
        </button>
      </div>

      <!-- Content area -->
      <div class="flex flex-col gap-6">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 class="text-sm font-bold text-[var(--text)] leading-snug">{{ selectedEnrollment?.course?.title || 'Tài liệu' }}</h2>
            <p class="text-[10px] text-[var(--muted)] font-semibold mt-1">{{ loadingFiles ? 'Đang tải...' : `${totalFiles} tệp đính kèm` }}</p>
          </div>
          <div class="relative w-full sm:w-64">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm leading-none">search</span>
            <input v-model="search" type="search" placeholder="Tìm tài liệu..." class="w-full h-9 pl-9 pr-4 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
          </div>
        </div>

        <!-- Loading files -->
        <div v-if="loadingFiles" class="flex flex-col gap-3 animate-pulse">
          <div v-for="i in 4" :key="i" class="h-12 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
        </div>

        <!-- File groups -->
        <div v-else-if="filteredGroups.length" class="flex flex-col gap-3">
          <div v-for="group in filteredGroups" :key="group.lessonId" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
            <button class="flex items-center gap-3 px-5 py-3.5 hover:bg-[var(--surface)] transition-colors text-left" @click="toggleLesson(group.lessonId)">
              <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-sm">folder</span>
              </div>
              <span class="text-xs font-bold text-[var(--text)] flex-1 truncate">{{ group.lessonTitle }}</span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-500 border border-slate-200">{{ group.files.length }} tệp</span>
              <span class="material-symbols-outlined text-lg text-[var(--muted)]">{{ expanded.has(group.lessonId) ? 'expand_less' : 'expand_more' }}</span>
            </button>
            
            <div v-show="expanded.has(group.lessonId)" class="border-t border-[var(--line)] flex flex-col">
              <div v-for="file in group.files" :key="file.id" class="flex items-center gap-3 px-5 py-3 hover:bg-[var(--surface)] border-b border-[var(--line)] last:border-b-0 transition-colors">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :class="fileIconClass(file)">
                  <span class="material-symbols-outlined text-base">{{ fileIcon(file) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold text-[var(--text)] truncate">{{ file.name || file.filename || file.original_name || file.title }}</p>
                  <div class="flex items-center gap-2 mt-1">
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-[8px] font-bold text-slate-500 border border-slate-200">{{ fileExt(file) }}</span>
                    <span v-if="file.size" class="text-[9px] font-semibold text-[var(--muted)]">{{ formatSize(file.size) }}</span>
                  </div>
                </div>
                <a
                  :href="file.url || file.download_url || `/attachments/${file.id}/download`"
                  target="_blank" rel="noopener"
                  class="w-8 h-8 rounded-lg border border-[var(--line)] hover:bg-[var(--surface)] text-[var(--muted)] hover:text-[#1d9e75] flex items-center justify-center transition-colors"
                  :download="file.name || file.filename"
                >
                  <span class="material-symbols-outlined text-sm">download</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="!loadingFiles" class="flex flex-col items-center gap-3 text-center py-12 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
          <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">folder_open</span>
          <p class="text-xs font-semibold text-[var(--muted)]">{{ search ? 'Không tìm thấy tài liệu.' : 'Khóa học này chưa có tài liệu đính kèm.' }}</p>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-col items-center gap-3 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">library_books</span>
      <p class="text-sm font-semibold text-[var(--muted)]">Bạn chưa đăng ký khóa học nào.</p>
      <NuxtLink to="/student/recommendations" class="h-9 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#157959] text-white text-xs font-bold flex items-center transition-colors mt-2">Khám phá khóa học</NuxtLink>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
