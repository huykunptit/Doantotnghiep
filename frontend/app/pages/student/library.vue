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
  const [r0] = await Promise.allSettled([
    useApi<any[]>('/user/enrollments', { headers: h }),
  ])
  if (r0.status === 'fulfilled') {
    enrollments.value = r0.value || []
    if (enrollments.value.length) {
      selectedCourseId.value = enrollments.value[0].course?.id || enrollments.value[0].course_id || enrollments.value[0].id
    }
  }
  loading.value = false
})

watch(selectedCourseId, async (id) => {
  if (!id || attachments.value[id]) return
  loadingFiles.value = true
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0] = await Promise.allSettled([
    useApi<any>(`/courses/${id}/lessons?include_attachments=1`, { headers: h }),
  ])
  if (r0.status === 'fulfilled') {
    const d = r0.value
    const lessons: any[] = Array.isArray(d) ? d : (d?.data || d?.lessons || [])
    attachments.value[id] = lessons.map(l => ({
      lessonId: l.id,
      lessonTitle: l.title || l.name,
      files: l.attachments || [],
    })).filter(g => g.files.length > 0)
  }
  loadingFiles.value = false
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
  if (name.endsWith('.pdf')) return 'file-text'
  if (name.match(/\.(mp4|mov|avi|mkv|webm)$/)) return 'play-circle'
  if (name.match(/\.(zip|rar|7z)$/)) return 'archive'
  if (name.match(/\.(docx?|odt)$/)) return 'file'
  if (name.match(/\.(xlsx?|csv)$/)) return 'table'
  if (name.match(/\.(pptx?|ppt)$/)) return 'presentation'
  return 'paperclip'
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
  <div class="lb-page">
    <!-- Header -->
    <div>
      <p class="section-kicker">Tài nguyên</p>
      <h1 class="lb-title">Thư viện tài liệu</h1>
    </div>

    <div v-if="loading" class="lb-layout">
      <div class="lb-sidebar-skeleton">
        <span v-for="i in 5" :key="i" class="sd-shimmer" style="height:52px;border-radius:10px;display:block;margin-bottom:8px"></span>
      </div>
      <div class="dashboard-card lb-content-skeleton" style="flex:1;padding:20px;display:flex;flex-direction:column;gap:8px">
        <span v-for="i in 8" :key="i" class="sd-shimmer" style="height:44px;border-radius:8px;display:block"></span>
      </div>
    </div>

    <div v-else-if="enrollments.length" class="lb-layout">
      <!-- Sidebar: course list -->
      <div class="lb-sidebar">
        <p class="lb-sidebar-label">Khóa học</p>
        <button
          v-for="e in enrollments"
          :key="e.id"
          class="lb-course-btn"
          :class="{active: selectedCourseId == (e.course?.id || e.course_id || e.id)}"
          @click="selectedCourseId = e.course?.id || e.course_id || e.id"
        >
          <div class="lb-course-thumb">
            <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title">
            <SylvaIcon v-else name="book-open" :size="12" />
          </div>
          <p class="lb-course-name">{{ e.course?.title || e.title || 'Khóa học' }}</p>
        </button>
      </div>

      <!-- Content area -->
      <div class="lb-content">
        <!-- Toolbar -->
        <div class="lb-toolbar">
          <div>
            <h2 class="lb-content-title">{{ selectedEnrollment?.course?.title || 'Tài liệu' }}</h2>
            <p class="lb-content-sub">{{ loadingFiles ? 'Đang tải...' : `${totalFiles} tệp đính kèm` }}</p>
          </div>
          <div class="lb-search-wrap">
            <SylvaIcon name="search" :size="14" class="lb-search-icon" />
            <input v-model="search" type="search" placeholder="Tìm tài liệu..." class="lb-search" />
          </div>
        </div>

        <!-- Loading files -->
        <div v-if="loadingFiles" style="display:flex;flex-direction:column;gap:8px">
          <span v-for="i in 6" :key="i" class="sd-shimmer" style="height:44px;border-radius:8px;display:block"></span>
        </div>

        <!-- File groups -->
        <div v-else-if="filteredGroups.length" class="lb-groups">
          <div v-for="group in filteredGroups" :key="group.lessonId" class="lb-group">
            <button class="lb-group-head" @click="toggleLesson(group.lessonId)" :aria-expanded="expanded.has(group.lessonId)">
              <div class="lb-group-icon"><SylvaIcon name="folder" :size="15" /></div>
              <span class="lb-group-title">{{ group.lessonTitle }}</span>
              <span class="lb-group-count">{{ group.files.length }} tệp</span>
              <SylvaIcon :name="expanded.has(group.lessonId) ? 'chevron-up' : 'chevron-down'" :size="14" class="lb-chevron" />
            </button>
            <Transition name="lb-slide">
              <div v-if="expanded.has(group.lessonId)" class="lb-file-list">
                <div v-for="file in group.files" :key="file.id" class="lb-file-row">
                  <div class="lb-file-icon">
                    <SylvaIcon :name="fileIcon(file)" :size="15" />
                  </div>
                  <div class="lb-file-info">
                    <p class="lb-file-name">{{ file.name || file.filename || file.original_name || file.title }}</p>
                    <div class="lb-file-meta">
                      <span class="lb-file-ext">{{ fileExt(file) }}</span>
                      <span v-if="file.size" class="lb-file-size">{{ formatSize(file.size) }}</span>
                    </div>
                  </div>
                  <a
                    :href="file.url || file.download_url || `/attachments/${file.id}/download`"
                    target="_blank" rel="noopener"
                    class="lb-btn-dl"
                    :download="file.name || file.filename"
                  >
                    <SylvaIcon name="download" :size="13" />
                  </a>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <div v-else-if="!loadingFiles" class="sd-empty">
          <SylvaIcon name="folder-open" :size="40" />
          <p>{{ search ? 'Không tìm thấy tài liệu.' : 'Khóa học này chưa có tài liệu đính kèm.' }}</p>
        </div>
      </div>
    </div>

    <div v-else class="sd-empty">
      <SylvaIcon name="library" :size="40" />
      <p>Bạn chưa đăng ký khóa học nào.</p>
      <NuxtLink to="/student/recommendations" class="lb-btn-cta">Khám phá khóa học</NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.lb-page { display: flex; flex-direction: column; gap: 20px; }
.lb-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 0; }

.lb-layout { display: flex; gap: 20px; align-items: flex-start; }

/* Sidebar */
.lb-sidebar {
  width: 260px; flex-shrink: 0;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 12px;
  position: sticky; top: 80px;
  max-height: calc(100vh - 140px);
  overflow-y: auto;
}
.lb-sidebar-skeleton { width: 260px; flex-shrink: 0; }
.lb-sidebar-label {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--muted); padding: 0 8px; margin: 0 0 8px;
}
.lb-course-btn {
  display: flex; align-items: center; gap: 8px;
  width: 100%; padding: 8px 10px; border-radius: 8px;
  border: none; background: transparent; cursor: pointer;
  text-align: left; transition: background 150ms;
  margin-bottom: 2px;
}
.lb-course-btn:hover { background: var(--bg); }
.lb-course-btn.active { background: var(--green-soft); }
.lb-course-thumb {
  width: 30px; height: 30px; border-radius: 6px;
  background: var(--bg); display: flex; align-items: center; justify-content: center;
  overflow: hidden; flex-shrink: 0; color: var(--muted);
}
.lb-course-thumb img { width: 100%; height: 100%; object-fit: cover; }
.lb-course-name {
  font-size: 0.8rem; font-weight: 600; color: var(--text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;
}
.lb-course-btn.active .lb-course-name { color: var(--green-deep); }

/* Content */
.lb-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 16px; }
.lb-content-skeleton { flex: 1; }
.lb-toolbar { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.lb-content-title { font-size: 1.05rem; font-weight: 700; color: var(--text); margin: 0 0 3px; }
.lb-content-sub { font-size: 0.78rem; color: var(--muted); margin: 0; }
.lb-search-wrap { position: relative; }
.lb-search-icon { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.lb-search {
  padding: 7px 12px 7px 30px; border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface-strong); color: var(--text); font-size: 0.82rem; outline: none; width: 200px;
}
.lb-search:focus { border-color: var(--green); }

.lb-groups { display: flex; flex-direction: column; gap: 8px; }
.lb-group { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: var(--surface-strong); box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06)); }
.lb-group-head {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 12px 14px;
  background: transparent; border: none; cursor: pointer;
  text-align: left; transition: background 150ms;
}
.lb-group-head:hover { background: var(--bg); }
.lb-group-icon {
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--accent-soft); color: #92400e;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.lb-group-title { flex: 1; font-size: 0.86rem; font-weight: 700; color: var(--text); min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-group-count {
  font-size: 0.7rem; font-weight: 600;
  background: var(--bg); color: var(--muted);
  padding: 2px 8px; border-radius: 20px; border: 1px solid var(--line); white-space: nowrap;
}
.lb-chevron { color: var(--muted); flex-shrink: 0; }

.lb-file-list { border-top: 1px solid var(--line); }
.lb-file-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-bottom: 1px solid var(--line);
  transition: background 120ms;
}
.lb-file-row:last-child { border-bottom: none; }
.lb-file-row:hover { background: var(--bg); }
.lb-file-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: var(--secondary-soft); color: var(--secondary);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.lb-file-info { flex: 1; min-width: 0; }
.lb-file-name { font-size: 0.84rem; font-weight: 600; color: var(--text); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-file-meta { display: flex; align-items: center; gap: 8px; }
.lb-file-ext {
  font-size: 0.66rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;
  background: var(--bg); color: var(--muted); border: 1px solid var(--line);
}
.lb-file-size { font-size: 0.7rem; color: var(--muted); }
.lb-btn-dl {
  width: 30px; height: 30px; border-radius: 7px;
  border: 1px solid var(--line); background: transparent; color: var(--muted);
  display: flex; align-items: center; justify-content: center;
  transition: background 150ms, color 150ms; flex-shrink: 0;
}
.lb-btn-dl:hover { background: var(--green-soft); color: var(--green-deep); border-color: transparent; }

/* Transitions */
.lb-slide-enter-active { transition: all 200ms ease; }
.lb-slide-leave-active { transition: all 150ms ease; }
.lb-slide-enter-from, .lb-slide-leave-to { opacity: 0; transform: translateY(-6px); }

.lb-btn-cta {
  display: inline-flex; align-items: center; margin-top: 8px;
  padding: 7px 16px; border-radius: 8px;
  background: var(--green); color: #fff;
  font-size: 0.82rem; font-weight: 700; text-decoration: none;
}

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .lb-sidebar { background: var(--surface); }
[data-theme="dark"] .lb-group { background: var(--surface); }
[data-theme="dark"] .lb-search { background: var(--surface); }
[data-theme="dark"] .lb-course-btn.active { background: rgba(52,211,153,0.12); }
[data-theme="dark"] .lb-course-btn.active .lb-course-name { color: #6ee7b7; }

@media (max-width: 900px) {
  .lb-layout { flex-direction: column; }
  .lb-sidebar { width: 100%; position: static; max-height: none; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 6px; }
  .lb-sidebar-label { grid-column: 1/-1; }
}
@media (max-width: 640px) {
  .lb-toolbar { flex-direction: column; align-items: flex-start; }
  .lb-search { width: 100%; }
  .lb-search-wrap { width: 100%; }
  .lb-sidebar { grid-template-columns: 1fr 1fr; }
}
</style>
