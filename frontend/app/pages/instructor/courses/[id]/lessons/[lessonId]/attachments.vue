<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ middleware: 'instructor', layout: 'instructor' })

const route = useRoute()
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const courseId = Number(route.params.id)
const lessonId = Number(route.params.lessonId)

const loading = ref(true)
const uploading = ref(false)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const lesson = ref<any>(null)
const attachments = ref<any[]>([])

const ICON_MAP: Record<string, string> = {
  pdf: 'picture_as_pdf',
  doc: 'description', docx: 'description',
  xls: 'table_chart', xlsx: 'table_chart',
  ppt: 'slideshow', pptx: 'slideshow',
  zip: 'folder_zip', rar: 'folder_zip',
  mp4: 'movie', mp3: 'music_note',
}

function fileIcon(name: string) {
  const ext = name?.split('.').pop()?.toLowerCase() || ''
  return ICON_MAP[ext] || 'attach_file'
}

function formatDate(val?: string) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('vi-VN')
}

async function loadData() {
  loading.value = true
  try {
    const [lessonRes, attRes] = await Promise.all([
      useApi<any>(`/courses/${courseId}/lessons/${lessonId}`, { headers: authHeaders() }),
      useApi<any>(`/courses/${courseId}/lessons/${lessonId}/attachments`, { headers: authHeaders() }),
    ])
    lesson.value = lessonRes
    attachments.value = attRes.attachments || []
  }
  catch { attachments.value = [] }
  finally { loading.value = false }
}

async function handleFileChange(e: Event) {
  uploadError.value = ''
  const target = e.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return
  const file = target.files[0]
  if (file.size > 50 * 1024 * 1024) {
    uploadError.value = 'File vượt quá giới hạn 50MB.'
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  const formData = new FormData()
  formData.append('file', file)
  uploading.value = true
  try {
    const config = useRuntimeConfig()
    const response = await fetch(`${config.public.apiBase}/courses/${courseId}/lessons/${lessonId}/attachments`, {
      method: 'POST',
      body: formData,
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
    })
    if (!response.ok) throw new Error('Upload failed')
    const data = await response.json()
    attachments.value.push(data.attachment)
  }
  catch { uploadError.value = 'Có lỗi khi tải lên. Vui lòng thử lại.' }
  finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function downloadFile(file: any) {
  try {
    const res = await useApi<{ url: string }>(`/courses/${courseId}/lessons/${lessonId}/attachments/${file.id}/download`, { headers: authHeaders() })
    window.open(res.url, '_blank')
  }
  catch { alert('Không thể tải file lúc này.') }
}

async function deleteFile(file: any) {
  if (!confirm(`Xoá file "${file.original_name}"? Không thể hoàn tác.`)) return
  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}/attachments/${file.id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    attachments.value = attachments.value.filter(a => a.id !== file.id)
  }
  catch { alert('Không thể xóa file.') }
}

onMounted(loadData)
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="section-kicker" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 4px;">
          ← Quay lại Curriculum
        </NuxtLink>
        <h2>Tài liệu đính kèm</h2>
        <p v-if="lesson">Bài học: <strong>{{ lesson.title }}</strong></p>
      </div>
      <label class="crud-primary-btn" style="cursor: pointer;">
        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">upload</span>
        Tải lên tài liệu
        <input ref="fileInput" type="file" style="display: none;" :disabled="uploading" @change="handleFileChange">
      </label>
    </header>

    <div v-if="uploadError" class="crud-alert is-error" style="margin-bottom: 16px;">{{ uploadError }}</div>

    <!-- Drop zone -->
    <div class="upload-zone dashboard-card" :class="{ 'is-uploading': uploading }" @click="fileInput?.click()">
      <template v-if="uploading">
        <span class="material-symbols-outlined upload-icon spinning">sync</span>
        <p style="font-weight: 600; margin: 8px 0 4px;">Đang tải lên...</p>
      </template>
      <template v-else>
        <span class="material-symbols-outlined upload-icon">cloud_upload</span>
        <p style="font-weight: 600; margin: 8px 0 4px;">Kéo thả file vào đây hoặc click để chọn</p>
        <p style="font-size: 0.8rem; color: var(--muted);">Hỗ trợ PDF, DOCX, PPTX, XLSX, ZIP (tối đa 50MB)</p>
      </template>
    </div>

    <!-- Attachment list -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar" style="margin-bottom: 16px;">
        <div>
          <p class="section-kicker">Bài học này</p>
          <h3>Tài liệu đã tải lên ({{ attachments.length }})</h3>
        </div>
      </div>

      <div v-if="loading" class="crud-empty">Đang tải...</div>
      <div v-else-if="attachments.length === 0" class="crud-empty">
        Chưa có tài liệu đính kèm nào. Tải lên để học viên có thể tải về.
      </div>

      <div v-else class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Tài liệu</th>
              <th>Dung lượng</th>
              <th>Ngày tải lên</th>
              <th style="text-align: right;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="file in attachments" :key="file.id">
              <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="file-icon-wrap">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--green);">{{ fileIcon(file.original_name) }}</span>
                  </div>
                  <span style="font-weight: 600; font-size: 0.875rem;">{{ file.original_name }}</span>
                </div>
              </td>
              <td style="color: var(--muted); font-size: 0.85rem;">{{ file.file_size }}</td>
              <td style="color: var(--muted); font-size: 0.85rem;">{{ formatDate(file.created_at) }}</td>
              <td>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                  <button type="button" class="crud-secondary-btn" style="padding: 6px 12px; font-size: 0.78rem;" @click="downloadFile(file)">
                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle; margin-right: 2px;">download</span>
                    Tải về
                  </button>
                  <button type="button" class="crud-secondary-btn" style="padding: 6px 10px; color: #ef4444; border-color: rgba(239,68,68,.3);" @click="deleteFile(file)">
                    <span class="material-symbols-outlined" style="font-size: 14px;">delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<style scoped>
.upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 140px;
  cursor: pointer;
  border: 2px dashed var(--line);
  transition: all 0.2s;
  margin-bottom: 20px;
  text-align: center;
}
.upload-zone:hover { border-color: var(--green); background: rgba(var(--green-rgb), 0.02); }
.upload-zone.is-uploading { border-color: var(--green); cursor: default; }

.upload-icon {
  font-size: 36px;
  color: var(--green);
  opacity: 0.7;
}
.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.file-icon-wrap {
  width: 36px; height: 36px;
  border-radius: 8px;
  background: rgba(var(--green-rgb), 0.08);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
</style>
