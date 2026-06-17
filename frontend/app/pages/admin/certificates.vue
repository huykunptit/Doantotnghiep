<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, Award, ZoomIn } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import MediaUpload from '~/components/common/MediaUpload.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface CertificateTemplate {
  id: number
  name: string
  background_image_url: string | null
  created_at: string
}

interface IssuedCert {
  id: number
  credential_id: string
  user: { id: number; name: string; email: string }
  course: { id: number; title: string }
  template: { id: number; name: string }
  issued_at: string
}

const templates = ref<CertificateTemplate[]>([])
const issued = ref<IssuedCert[]>([])
const loading = ref(true)
const issuedLoading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const tab = ref<'templates' | 'issued'>('templates')
const previewTemplate = ref<CertificateTemplate | null>(null)
const searchIssued = ref('')
const issuedPage = ref(1)
const issuedLastPage = ref(1)
const issuedTotal = ref(0)

const isCreating = ref(false)
const formName = ref('')
const formBackgroundUrl = ref<string | null>(null)
const formBackgroundPath = ref<string | null>(null)

async function fetchTemplates() {
  loading.value = true
  try {
    const res = await useApi<CertificateTemplate[]>('/admin/certificates', { headers: authHeaders() })
    templates.value = Array.isArray(res) ? res : (res as any).data || []
  }
  catch { error.value = 'Không thể tải danh sách mẫu chứng chỉ.' }
  finally { loading.value = false }
}

async function fetchIssued(page = 1) {
  issuedLoading.value = true
  try {
    const params = new URLSearchParams({ per_page: '15', page: String(page) })
    if (searchIssued.value) params.set('search', searchIssued.value)
    const res = await useApi<any>(`/my-certificates?${params}`, { headers: authHeaders() })
    issued.value = res.data || []
    issuedPage.value = res.current_page || 1
    issuedLastPage.value = res.last_page || 1
    issuedTotal.value = res.total || 0
  }
  catch { issued.value = [] }
  finally { issuedLoading.value = false }
}

async function createTemplate() {
  if (!formName.value || !formBackgroundPath.value) return
  saving.value = true
  error.value = ''
  try {
    await useApi('/admin/certificates', {
      method: 'POST',
      headers: authHeaders(),
      body: { name: formName.value, background_image_url: formBackgroundPath.value },
    })
    isCreating.value = false
    formName.value = ''
    formBackgroundUrl.value = null
    formBackgroundPath.value = null
    await fetchTemplates()
    success.value = 'Tạo mẫu chứng chỉ thành công.'
  }
  catch { error.value = 'Có lỗi xảy ra khi tạo mẫu chứng chỉ.' }
  finally { saving.value = false }
}

function onBackgroundUploaded(payload: { url: string; path: string }) {
  formBackgroundUrl.value = payload.url
  formBackgroundPath.value = payload.path
}

async function deleteTemplate(id: number) {
  if (!confirm('Bạn có chắc muốn xoá mẫu chứng chỉ này?')) return
  try {
    await useApi(`/admin/certificates/${id}`, { method: 'DELETE', headers: authHeaders() })
    await fetchTemplates()
    success.value = 'Đã xoá mẫu chứng chỉ.'
  }
  catch { error.value = 'Không thể xoá mẫu chứng chỉ.' }
}

function copyCredentialLink(credentialId: string) {
  const url = `${window.location.origin}/certificates/verify/${credentialId}`
  navigator.clipboard.writeText(url).then(() => {
    success.value = 'Đã sao chép link xác minh!'
    setTimeout(() => { success.value = '' }, 2000)
  })
}

function formatDate(d?: string) {
  return d ? new Date(d).toLocaleDateString('vi-VN') : '—'
}

const templateCount = computed(() => templates.value.length)

const { exportToCSV } = useExport()

function exportIssuedData() {
  const cols = [
    { key: 'credential_id', label: 'Mã xác minh' },
    { key: 'user_name', label: 'Học viên', format: (_: any, row: IssuedCert) => row.user?.name || '--' },
    { key: 'user_email', label: 'Email', format: (_: any, row: IssuedCert) => row.user?.email || '--' },
    { key: 'course_title', label: 'Khóa học', format: (_: any, row: IssuedCert) => row.course?.title || '--' },
    { key: 'template_name', label: 'Mẫu chứng chỉ', format: (_: any, row: IssuedCert) => row.template?.name || '--' },
    { key: 'issued_at', label: 'Ngày cấp', format: (val: any) => formatDate(val) }
  ]
  exportToCSV(issued.value, cols, 'danh_sach_chung_chi_da_cap')
}

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, issuedPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(issuedLastPage.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) {
    if (i >= 1) range.push(i)
  }
  return range
})

onMounted(() => {
  fetchTemplates()
  fetchIssued()
})
</script>

<template>
  <AdminWorkspaceShell
    title="Quản lý chứng chỉ"
    description="Tạo và quản lý mẫu phôi chứng chỉ. Xem danh sách chứng chỉ đã cấp cho học viên."
    :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Chứng chỉ']"
  >
    <!-- KPI -->
    <section class="dashboard-grid" style="margin-bottom: 24px;">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Mẫu chứng chỉ</p>
        <div class="mini-head"><strong>{{ templateCount }}</strong><span>Loại phôi</span></div>
      </article>
      <article class="dashboard-card mini-card tone-blue">
        <p class="mini-title">Đã cấp</p>
        <div class="mini-head"><strong>{{ issuedTotal }}</strong><span>Chứng chỉ</span></div>
      </article>
    </section>

    <!-- Alerts -->
    <div v-if="error" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>
    <div v-if="success" class="crud-alert is-success" style="margin-bottom: 16px;">{{ success }}</div>

    <!-- Tabs -->
    <div class="tab-bar" style="margin-bottom: 20px;">
      <button type="button" class="tab-btn" :class="{ active: tab === 'templates' }" @click="tab = 'templates'">
        Mẫu phôi chứng chỉ
      </button>
      <button type="button" class="tab-btn" :class="{ active: tab === 'issued' }" @click="tab = 'issued'; fetchIssued()">
        Chứng chỉ đã cấp
      </button>
    </div>

    <!-- Tab: Templates -->
    <template v-if="tab === 'templates'">
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <h3>Danh sách mẫu ({{ templateCount }})</h3>
          <button type="button" class="crud-primary-btn" @click="isCreating = !isCreating">
            {{ isCreating ? 'Huỷ' : '+ Thêm mẫu mới' }}
          </button>
        </div>

        <!-- Create form -->
        <div v-if="isCreating" class="crud-form-grid" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--line);">
          <label class="crud-field crud-field-full">
            <span>Tên mẫu chứng chỉ <span style="color:#ef4444">*</span></span>
            <input v-model="formName" type="text" placeholder="Ví dụ: Chứng chỉ Hoàn thành Khoá học Lập trình">
          </label>
          <div class="crud-field crud-field-full">
            <span class="crud-field-label">Phôi chứng chỉ (hình ảnh) <span style="color:#ef4444">*</span></span>
            <MediaUpload
              v-model="formBackgroundUrl"
              folder="settings"
              variant="banner"
              label="Phôi chứng chỉ"
              hint="JPG/PNG, tỉ lệ ngang 1600×1131. Tối đa 5MB."
              placeholder-initial="CT"
              @uploaded="onBackgroundUploaded"
              @error="(msg) => error = msg"
            />
          </div>
          <div class="crud-field-full" style="display: flex; gap: 10px;">
            <button class="crud-primary-btn" :disabled="saving || !formName || !formBackgroundPath" @click="createTemplate">
              {{ saving ? 'Đang lưu...' : 'Tạo mẫu' }}
            </button>
            <button class="crud-secondary-btn" @click="isCreating = false">Huỷ</button>
          </div>
        </div>

        <!-- Templates grid -->
        <div v-if="loading" class="crud-empty">Đang tải...</div>
        <div v-else-if="templates.length === 0" class="crud-empty">Chưa có mẫu chứng chỉ nào.</div>
        <div v-else class="certs-grid">
          <div v-for="t in templates" :key="t.id" class="cert-card">
            <div class="cert-preview" @click="previewTemplate = t">
              <img
                v-if="t.background_image_url"
                :src="t.background_image_url"
                :alt="t.name"
                class="cert-img"
              >
              <div v-else class="cert-no-img">
                <Award :size="40" :stroke-width="1.75" style="opacity: 0.3;" />
              </div>
              <div class="cert-hover-overlay">
                <ZoomIn :size="28" :stroke-width="1.75" />
                <span>Xem trước</span>
              </div>
            </div>
            <div class="cert-info">
              <strong style="font-size: 0.9rem;">{{ t.name }}</strong>
              <p style="font-size: 0.75rem; color: var(--muted); margin: 4px 0 0;">
                Tạo: {{ formatDate(t.created_at) }}
              </p>
              <div style="display: flex; gap: 6px; margin-top: 12px;">
                <button type="button" class="action-btn is-view" @click="previewTemplate = t">Preview</button>
                <button type="button" class="action-btn is-danger" @click="deleteTemplate(t.id)">Xoá</button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>

    <!-- Tab: Issued certs -->
    <template v-else-if="tab === 'issued'">
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <input
              v-model="searchIssued"
              type="text"
              class="crud-search"
              placeholder="Tìm học viên, khoá học..."
              @keyup.enter="fetchIssued(1)"
            >
          </div>
          <div class="crud-toolbar-right">
            <button class="crud-export-btn" type="button" @click="exportIssuedData">
              <Download :size="18" :stroke-width="1.75" />
              Xuất Excel
            </button>
          </div>
        </div>

        <div v-if="issuedLoading" class="crud-empty">Đang tải...</div>
        <div v-else-if="issued.length === 0" class="crud-empty">Chưa có chứng chỉ nào được cấp.</div>

        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Học viên</th>
                <th>Khoá học</th>
                <th>Mẫu chứng chỉ</th>
                <th>Mã xác minh</th>
                <th>Ngày cấp</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cert in issued" :key="cert.id">
                <td>
                  <div class="crud-profile">
                    <div class="crud-avatar crud-avatar-fallback">
                      {{ cert.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}
                    </div>
                    <div>
                      <strong>{{ cert.user?.name || '—' }}</strong>
                      <p>{{ cert.user?.email || '—' }}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <strong style="font-size: 0.875rem;">{{ cert.course?.title || '—' }}</strong>
                </td>
                <td>
                  <span class="crud-badge role-instructor">{{ cert.template?.name || '—' }}</span>
                </td>
                <td>
                  <code style="font-size: 0.75rem; background: rgba(17,17,17,.05); padding: 2px 8px; border-radius: 6px;">
                    {{ cert.credential_id }}
                  </code>
                </td>
                <td style="font-size: 0.82rem; color: var(--muted);">{{ formatDate(cert.issued_at) }}</td>
                <td>
                  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <NuxtLink
                      :to="`/certificates/verify/${cert.credential_id}`"
                      target="_blank"
                      class="action-btn is-view"
                    >
                      Xem
                    </NuxtLink>
                    <button
                      type="button"
                      class="action-btn is-edit"
                      @click="copyCredentialLink(cert.credential_id)"
                    >
                      Copy link
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="issuedLastPage > 1" class="crud-pagination">
          <p>Hiển thị trang {{ issuedPage }} / {{ issuedLastPage }} (Tổng số {{ issuedTotal }} chứng chỉ)</p>
          <div class="crud-pagination-actions">
            <button class="pagination-num-btn" type="button" :disabled="issuedPage <= 1" @click="fetchIssued(issuedPage - 1)">
              Trước
            </button>
            <div class="pagination-numbers">
              <button
                v-for="p in visiblePages"
                :key="p"
                class="pagination-num-btn"
                :class="{ 'is-active': p === issuedPage }"
                type="button"
                @click="fetchIssued(p)"
              >
                {{ p }}
              </button>
            </div>
            <button class="pagination-num-btn" type="button" :disabled="issuedPage >= issuedLastPage" @click="fetchIssued(issuedPage + 1)">
              Sau
            </button>
          </div>
        </div>
      </section>
    </template>

    <!-- Preview modal -->
    <div v-if="previewTemplate" class="modal-overlay" @click.self="previewTemplate = null">
      <div class="preview-modal dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3 style="margin: 0;">{{ previewTemplate.name }}</h3>
          <button type="button" class="crud-secondary-btn" @click="previewTemplate = null">Đóng</button>
        </div>
        <img
          v-if="previewTemplate.background_image_url"
          :src="previewTemplate.background_image_url"
          :alt="previewTemplate.name"
          style="width: 100%; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15);"
        >
        <p v-else style="text-align: center; color: var(--muted); padding: 40px 0;">Chưa có ảnh phôi.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.tab-bar {
  display: flex;
  gap: 4px;
  background: rgba(17,17,17,0.04);
  padding: 4px;
  border-radius: 14px;
  width: fit-content;
}
.tab-btn {
  padding: 8px 20px;
  border: none;
  background: transparent;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn.active {
  background: #fff;
  color: var(--text);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.certs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  margin-top: 8px;
}
.cert-card {
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.cert-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.cert-preview {
  position: relative;
  aspect-ratio: 16 / 11;
  background: rgba(17,17,17,0.04);
  cursor: pointer;
  overflow: hidden;
}
.cert-img { width: 100%; height: 100%; object-fit: cover; }
.cert-no-img { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
.cert-hover-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  opacity: 0;
  transition: opacity 0.2s;
}
.cert-preview:hover .cert-hover-overlay { opacity: 1; }
.cert-info { padding: 14px 16px; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.preview-modal {
  max-width: 900px;
  width: 100%;
  padding: 24px;
  max-height: 90vh;
  overflow-y: auto;
}
</style>
