<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import MediaUpload from '~/components/common/MediaUpload.vue'
import CertificateTemplateEditor from '~/components/certificate/CertificateTemplateEditor.vue'
import { useExport } from '~/composables/useExport'
import type { FieldConfig } from '~/components/certificate/CertificateTemplateEditor.vue'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface CertificateTemplate {
  id: number
  name: string
  background_image_url: string | null
  fields_config: FieldConfig[] | null
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

// ── state ──────────────────────────────────────────────────────────────────
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
const issuedPerPage = ref(15)

// create form
const isCreating = ref(false)
const formName = ref('')
const formBackgroundUrl = ref<string | null>(null)
const formBackgroundPath = ref<string | null>(null)

// editor modal
const editingTemplate = ref<CertificateTemplate | null>(null)
const editorFields = ref<FieldConfig[]>([])
const editorSaving = ref(false)

// ── helpers ─────────────────────────────────────────────────────────────────
const DEFAULT_FIELDS: FieldConfig[] = [
  { key: 'student_name', label: 'Tên học viên', x: 50, y: 42, font_size: 36, font_family: 'Georgia, serif', color: '#1a1a1a', font_weight: 'bold', text_align: 'center', visible: true },
  { key: 'course_title', label: 'Tên khoá học', x: 50, y: 55, font_size: 18, font_family: 'Arial, sans-serif', color: '#444444', font_weight: 'normal', text_align: 'center', visible: true },
  { key: 'issued_date',  label: 'Ngày cấp',    x: 50, y: 68, font_size: 13, font_family: 'Arial, sans-serif', color: '#666666', font_weight: 'normal', text_align: 'center', visible: true },
  { key: 'credential_id', label: 'Mã xác nhận', x: 50, y: 78, font_size: 11, font_family: '"Courier New", monospace', color: '#888888', font_weight: 'normal', text_align: 'center', visible: true },
]

// ── API calls ────────────────────────────────────────────────────────────────
async function fetchTemplates() {
  loading.value = true
  try {
    const res = await useApi<CertificateTemplate[]>('/admin/certificates', { headers: authHeaders() })
    templates.value = Array.isArray(res) ? res : (res as any).data || []
  } catch { error.value = 'Không thể tải danh sách mẫu chứng chỉ.' }
  finally { loading.value = false }
}

async function fetchIssued(page = 1) {
  issuedLoading.value = true
  try {
    const params = new URLSearchParams({ per_page: String(issuedPerPage.value), page: String(page) })
    if (searchIssued.value) params.set('search', searchIssued.value)
    const res = await useApi<any>(`/my-certificates?${params}`, { headers: authHeaders() })
    issued.value = res.data || []
    issuedPage.value = res.current_page || 1
    issuedLastPage.value = res.last_page || 1
    issuedTotal.value = res.total || 0
  } catch { issued.value = [] }
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
    setTimeout(() => { success.value = '' }, 3000)
  } catch { error.value = 'Có lỗi xảy ra khi tạo mẫu chứng chỉ.' }
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
    setTimeout(() => { success.value = '' }, 3000)
  } catch { error.value = 'Không thể xoá mẫu chứng chỉ.' }
}

// ── editor ───────────────────────────────────────────────────────────────────
function openEditor(t: CertificateTemplate) {
  editingTemplate.value = t
  editorFields.value = (t.fields_config && t.fields_config.length > 0)
    ? t.fields_config.map(f => ({ ...f }))
    : DEFAULT_FIELDS.map(f => ({ ...f }))
}

function closeEditor() {
  editingTemplate.value = null
  editorFields.value = []
}

async function saveEditorFields() {
  if (!editingTemplate.value) return
  editorSaving.value = true
  try {
    const updated = await useApi<CertificateTemplate>(`/admin/certificates/${editingTemplate.value.id}/fields`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { fields_config: editorFields.value },
    })
    const idx = templates.value.findIndex(t => t.id === editingTemplate.value!.id)
    if (idx !== -1) templates.value[idx] = (updated as any).template ?? updated
    success.value = 'Đã lưu cấu hình trường dữ liệu.'
    setTimeout(() => { success.value = '' }, 3000)
    closeEditor()
  } catch { error.value = 'Không thể lưu cấu hình.' }
  finally { editorSaving.value = false }
}

// ── misc ─────────────────────────────────────────────────────────────────────
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

onMounted(() => {
  fetchTemplates()
  fetchIssued()
})
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Khóa học</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Quản lý chứng chỉ</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Tạo và quản lý mẫu phôi chứng chỉ. Xem danh sách chứng chỉ đã cấp cho học viên.</p>
    </div>
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

    <!-- ─── Tab: Templates ─────────────────────────────────────────────────── -->
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
              folder="certificates/templates"
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
              <img v-if="t.background_image_url" :src="t.background_image_url" :alt="t.name" class="cert-img">
              <div v-else class="cert-no-img">
                <i class="pi pi-verified" style="font-size:2.5rem" />
              </div>
              <div class="cert-hover-overlay">
                <i class="pi pi-search-plus" style="font-size:1.75rem" />
                <span>Xem trước</span>
              </div>
            </div>
            <div class="cert-info">
              <strong style="font-size: 0.9rem;">{{ t.name }}</strong>
              <p style="font-size: 0.75rem; color: var(--muted); margin: 4px 0 0;">
                Tạo: {{ formatDate(t.created_at) }}
              </p>
              <div style="display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap;">
                <button type="button" class="action-btn is-view" @click="previewTemplate = t">Preview</button>
                <button type="button" class="action-btn is-edit" @click="openEditor(t)">
                  <i class="pi pi-cog" style="font-size:0.8125rem" />Thiết kế
                </button>
                <button type="button" class="action-btn is-danger" @click="deleteTemplate(t.id)">Xoá</button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>

    <!-- ─── Tab: Issued ────────────────────────────────────────────────────── -->
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
              <i class="pi pi-download" style="font-size:1.125rem" />
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
                <th>Học viên</th><th>Khoá học</th><th>Mẫu chứng chỉ</th>
                <th>Mã xác minh</th><th>Ngày cấp</th><th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cert in issued" :key="cert.id">
                <td>
                  <div class="crud-profile">
                    <div class="crud-avatar crud-avatar-fallback">{{ cert.user?.name?.slice(0,2).toUpperCase() || 'HV' }}</div>
                    <div><strong>{{ cert.user?.name || '—' }}</strong><p>{{ cert.user?.email || '—' }}</p></div>
                  </div>
                </td>
                <td><strong style="font-size:0.875rem">{{ cert.course?.title || '—' }}</strong></td>
                <td><span class="crud-badge role-instructor">{{ cert.template?.name || '—' }}</span></td>
                <td><code style="font-size:0.75rem;background:rgba(17,17,17,.05);padding:2px 8px;border-radius:6px">{{ cert.credential_id }}</code></td>
                <td style="font-size:0.82rem;color:var(--muted)">{{ formatDate(cert.issued_at) }}</td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <NuxtLink :to="`/certificates/verify/${cert.credential_id}`" target="_blank" class="action-btn is-view">Xem</NuxtLink>
                    <button type="button" class="action-btn is-edit" @click="copyCredentialLink(cert.credential_id)">Copy link</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTableFooter
          :current="issuedPage" :last="issuedLastPage" :total="issuedTotal" :per-page="issuedPerPage"
          @page="fetchIssued" @update:per-page="issuedPerPage = $event; fetchIssued(1)"
        />
      </section>
    </template>

    <!-- ─── Preview modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="previewTemplate" class="crud-modal-backdrop" @click.self="previewTemplate = null">
          <div class="crud-modal crud-modal-wide">
            <!-- Header -->
            <div class="crud-modal-head is-neutral">
              <div>
                <p class="section-kicker">Xem trước phôi</p>
                <h3>{{ previewTemplate.name }}</h3>
              </div>
              <button class="topbar-ghost" type="button" @click="previewTemplate = null">✕</button>
            </div>

            <!-- Body -->
            <div style="padding: 24px;">
              <div class="cert-preview-frame">
                <img v-if="previewTemplate.background_image_url" :src="previewTemplate.background_image_url" :alt="previewTemplate.name" class="cert-preview-bg">
                <div v-else class="cert-preview-bg cert-preview-blank"><i class="pi pi-verified" style="font-size:4.0rem" /></div>
                <!-- Render fields_config or fallback overlay -->
                <template v-if="previewTemplate.fields_config?.length">
                  <div
                    v-for="field in previewTemplate.fields_config"
                    v-show="field.visible"
                    :key="field.key"
                    class="cert-preview-field"
                    :style="{
                      left: field.x + '%',
                      top: field.y + '%',
                      fontSize: field.font_size + 'px',
                      fontFamily: field.font_family,
                      color: field.color,
                      fontWeight: field.font_weight,
                      textAlign: field.text_align,
                      transform: field.text_align === 'center' ? 'translateX(-50%)' : field.text_align === 'right' ? 'translateX(-100%)' : 'none',
                    }"
                  >
                    {{ { student_name: 'Nguyễn Văn Mẫu', course_title: previewTemplate.name, issued_date: '20 tháng 06 năm 2026', credential_id: 'SYLVA-SAMPLE-000001' }[field.key] ?? field.label }}
                  </div>
                </template>
                <div v-else class="cert-preview-overlay">
                  <div class="cert-preview-inner">
                    <p class="prev-cert-label">CHỨNG CHỈ HOÀN THÀNH</p>
                    <p class="prev-cert-name">Nguyễn Văn Mẫu</p>
                    <p class="prev-cert-course">{{ previewTemplate.name }}</p>
                    <p class="prev-cert-date">Hà Nội, ngày 20 tháng 06 năm 2026</p>
                    <code class="prev-cert-cred">SYLVA-SAMPLE-000001</code>
                  </div>
                </div>
              </div>
              <p style="text-align:center;font-size:0.78rem;color:var(--muted);margin-top:16px;margin-bottom:0">
                Nội dung thực tế sẽ được điền tự động khi cấp cho học viên.
              </p>
            </div>

            <!-- Footer -->
            <div class="crud-modal-foot">
              <button class="crud-secondary-btn" type="button" @click="previewTemplate = null">Đóng</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ─── Editor modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="editingTemplate" class="crud-modal-backdrop" @click.self="closeEditor">
          <div class="crud-modal crud-modal-wide">
            <!-- Header -->
            <div class="crud-modal-head">
              <div>
                <p class="section-kicker">Thiết kế trường dữ liệu</p>
                <h3>{{ editingTemplate.name }}</h3>
              </div>
              <button class="topbar-ghost" type="button" @click="closeEditor">✕</button>
            </div>

            <!-- Body -->
            <div style="padding: 24px; max-height: 70vh; overflow-y: auto;">
              <CertificateTemplateEditor
                v-model="editorFields"
                :background-url="editingTemplate.background_image_url"
                :saving="editorSaving"
                @save="saveEditorFields"
              />
            </div>

            <!-- Footer -->
            <div class="crud-modal-foot">
              <button class="crud-secondary-btn" type="button" @click="closeEditor">Đóng</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.tab-bar { display:flex;gap:4px;background:rgba(17,17,17,0.04);padding:4px;border-radius:14px;width:fit-content; }
.tab-btn { padding:8px 20px;border:none;background:transparent;border-radius:10px;font-size:0.875rem;font-weight:600;color:var(--muted);cursor:pointer;transition:all 0.2s; }
.tab-btn.active { background:#fff;color:var(--text);box-shadow:0 2px 8px rgba(0,0,0,0.08); }

.certs-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-top:8px; }
.cert-card { border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:box-shadow 0.2s; }
.cert-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); }
.cert-preview { position:relative;aspect-ratio:16/11;background:rgba(17,17,17,0.04);cursor:pointer;overflow:hidden; }
.cert-img { width:100%;height:100%;object-fit:cover; }
.cert-no-img { width:100%;height:100%;display:flex;align-items:center;justify-content:center; }
.cert-hover-overlay { position:absolute;inset:0;background:rgba(0,0,0,0.4);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#fff;font-size:0.8rem;font-weight:700;opacity:0;transition:opacity 0.2s; }
.cert-preview:hover .cert-hover-overlay { opacity:1; }
.cert-info { padding:14px 16px; }


.cert-preview-frame { position:relative;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.15);aspect-ratio:16/11; }
.cert-preview-bg { width:100%;height:100%;object-fit:cover;display:block; }
.cert-preview-blank { display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f0fdf4,#dcfce7); }

.cert-preview-field {
  position: absolute;
  white-space: nowrap;
  pointer-events: none;
  line-height: 1.2;
}

.cert-preview-overlay { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.18); }
.cert-preview-inner { text-align:center;background:rgba(255,255,255,0.88);backdrop-filter:blur(6px);border-radius:12px;padding:24px 36px;max-width:70%;box-shadow:0 4px 24px rgba(0,0,0,0.12); }
.prev-cert-label { font-size:0.65rem;font-weight:800;letter-spacing:0.22em;text-transform:uppercase;color:var(--green);margin:0 0 10px; }
.prev-cert-name { font-size:1.6rem;font-weight:900;font-family:Georgia,serif;color:#111;margin:0 0 6px;letter-spacing:-0.02em; }
.prev-cert-course { font-size:0.875rem;color:#555;margin:0 0 12px; }
.prev-cert-date { font-size:0.72rem;color:#888;margin:0 0 10px; }
.prev-cert-cred { display:inline-block;font-size:0.7rem;font-family:"Courier New",monospace;background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2);border-radius:6px;padding:3px 12px;color:var(--green-deep); }

[data-theme="dark"] .tab-btn.active { background:rgba(255,255,255,0.1);color:var(--text); }
[data-theme="dark"] .cert-preview-inner { background:rgba(0,0,0,0.8); }
[data-theme="dark"] .prev-cert-name { color:var(--text); }
[data-theme="dark"] .prev-cert-course { color:var(--muted); }
</style>
