<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '~/composables/useToast'
import {
  Calendar, Plus, Edit, Trash2, ChevronDown, ChevronUp,
  CalendarDays, Check, Clock, RefreshCw, Star, X, AlertCircle
} from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor'],
  adminSearchPlaceholder: 'Tìm năm học, học kỳ...',
})

type Id = number

interface AcademicYear {
  id: Id
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  status: string
  terms?: Term[]
}

interface Term {
  id: Id
  academic_year_id: Id
  name: string
  code: string
  start_date: string
  end_date: string
  enrollment_start_at?: string | null
  enrollment_end_at?: string | null
  exam_start_at?: string | null
  exam_end_at?: string | null
  is_current: boolean
  status: string
  is_supplementary?: boolean
}

const token = useAuthTokenCookie()
const toast = useToast()
function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const loading = ref(false)
const saving = ref(false)
const academicYears = ref<AcademicYear[]>([])
const expandedYearIds = ref<Set<Id>>(new Set())
const termsMap = ref<Record<Id, Term[]>>({})
const loadingTerms = ref<Set<Id>>(new Set())

// Year modal
const yearModalOpen = ref(false)
const yearModalMode = ref<'create' | 'edit'>('create')
const selectedYear = ref<AcademicYear | null>(null)
const yearForm = ref({ name: '', start_date: '', end_date: '', status: 'active' })

// Term modal
const termModalOpen = ref(false)
const termModalMode = ref<'create' | 'edit'>('create')
const selectedTerm = ref<Term | null>(null)
const activeYearId = ref<Id | null>(null)
const termForm = ref({
  name: '', code: '', start_date: '', end_date: '',
  enrollment_start_at: '', enrollment_end_at: '',
  exam_start_at: '', exam_end_at: '',
  is_current: false, status: 'upcoming', is_supplementary: false,
})

// Delete confirms
const showDeleteYear = ref(false)
const showDeleteTerm = ref(false)
const deleteYearId = ref<Id | null>(null)
const deleteTermId = ref<Id | null>(null)
const deleteTermYearId = ref<Id | null>(null)

onMounted(loadAcademicYears)

async function loadAcademicYears() {
  loading.value = true
  try {
    const res = await useApi<{ data: AcademicYear[] }>(
      '/admin/academic/academic-years?per_page=50', { headers: headers() }
    )
    academicYears.value = res.data
    // Auto-expand năm hiện tại
    const current = res.data.find(y => y.is_current)
    if (current) {
      expandedYearIds.value.add(current.id)
      await loadTermsForYear(current.id)
    }
  } catch (e) {
    toast.error('Không thể tải danh sách năm học.')
  } finally {
    loading.value = false
  }
}

async function loadTermsForYear(yearId: Id) {
  if (loadingTerms.value.has(yearId)) return
  loadingTerms.value.add(yearId)
  try {
    const res = await useApi<{ data: Term[] }>(
      `/admin/academic/terms?academic_year_id=${yearId}&per_page=20`, { headers: headers() }
    )
    termsMap.value[yearId] = res.data
  } catch (e) {
    toast.error('Không thể tải danh sách học kỳ.')
  } finally {
    loadingTerms.value.delete(yearId)
  }
}

async function toggleYear(yearId: Id) {
  if (expandedYearIds.value.has(yearId)) {
    expandedYearIds.value.delete(yearId)
  } else {
    expandedYearIds.value.add(yearId)
    if (!termsMap.value[yearId]) {
      await loadTermsForYear(yearId)
    }
  }
}

// ── Year CRUD ──────────────────────────────────────────────
function openCreateYear() {
  yearModalMode.value = 'create'
  selectedYear.value = null
  yearForm.value = { name: '', start_date: '', end_date: '', status: 'active' }
  yearModalOpen.value = true
}

function openEditYear(year: AcademicYear) {
  yearModalMode.value = 'edit'
  selectedYear.value = year
  yearForm.value = { name: year.name, start_date: year.start_date, end_date: year.end_date, status: year.status }
  yearModalOpen.value = true
}

async function saveYear() {
  if (!yearForm.value.name.trim() || !yearForm.value.start_date || !yearForm.value.end_date) {
    toast.error('Vui lòng điền đầy đủ tên và thời gian năm học.')
    return
  }
  saving.value = true
  try {
    if (selectedYear.value) {
      await useApi(`/admin/academic/academic-years/${selectedYear.value.id}`, {
        method: 'PUT', headers: headers(), body: yearForm.value
      })
      toast.success('Đã cập nhật năm học.')
    } else {
      await useApi('/admin/academic/academic-years', {
        method: 'POST', headers: headers(), body: { ...yearForm.value, institution_id: 1 }
      })
      toast.success('Đã tạo năm học mới.')
    }
    yearModalOpen.value = false
    await loadAcademicYears()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi lưu năm học.')
  } finally {
    saving.value = false
  }
}

async function deleteYear() {
  if (!deleteYearId.value) return
  loading.value = true
  try {
    await useApi(`/admin/academic/academic-years/${deleteYearId.value}`, {
      method: 'DELETE', headers: headers()
    })
    toast.success('Đã xóa năm học.')
    showDeleteYear.value = false
    await loadAcademicYears()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa năm học.')
  } finally {
    loading.value = false
  }
}

// ── Term CRUD ──────────────────────────────────────────────
function openCreateTerm(yearId: Id, isSupplementary = false) {
  termModalMode.value = 'create'
  selectedTerm.value = null
  activeYearId.value = yearId
  termForm.value = {
    name: '', code: '', start_date: '', end_date: '',
    enrollment_start_at: '', enrollment_end_at: '',
    exam_start_at: '', exam_end_at: '',
    is_current: false, status: 'upcoming', is_supplementary: isSupplementary,
  }
  termModalOpen.value = true
}

function openEditTerm(term: Term, yearId: Id) {
  termModalMode.value = 'edit'
  selectedTerm.value = term
  activeYearId.value = yearId
  termForm.value = {
    name: term.name, code: term.code,
    start_date: term.start_date, end_date: term.end_date,
    enrollment_start_at: term.enrollment_start_at || '',
    enrollment_end_at: term.enrollment_end_at || '',
    exam_start_at: term.exam_start_at || '',
    exam_end_at: term.exam_end_at || '',
    is_current: term.is_current, status: term.status,
    is_supplementary: term.is_supplementary || false,
  }
  termModalOpen.value = true
}

async function saveTerm() {
  if (!termForm.value.name.trim() || !termForm.value.code.trim() || !termForm.value.start_date || !termForm.value.end_date) {
    toast.error('Vui lòng điền đầy đủ tên, mã và thời gian học kỳ.')
    return
  }
  saving.value = true
  try {
    const payload = {
      ...termForm.value,
      academic_year_id: activeYearId.value,
      enrollment_start_at: termForm.value.enrollment_start_at || null,
      enrollment_end_at: termForm.value.enrollment_end_at || null,
      exam_start_at: termForm.value.exam_start_at || null,
      exam_end_at: termForm.value.exam_end_at || null,
    }
    if (selectedTerm.value) {
      await useApi(`/admin/academic/terms/${selectedTerm.value.id}`, {
        method: 'PUT', headers: headers(), body: payload
      })
      toast.success('Đã cập nhật học kỳ.')
    } else {
      await useApi('/admin/academic/terms', {
        method: 'POST', headers: headers(), body: payload
      })
      toast.success('Đã tạo học kỳ mới.')
    }
    termModalOpen.value = false
    if (activeYearId.value) await loadTermsForYear(activeYearId.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi lưu học kỳ.')
  } finally {
    saving.value = false
  }
}

async function deleteTerm() {
  if (!deleteTermId.value || !deleteTermYearId.value) return
  loading.value = true
  try {
    await useApi(`/admin/academic/terms/${deleteTermId.value}`, {
      method: 'DELETE', headers: headers()
    })
    toast.success('Đã xóa học kỳ.')
    showDeleteTerm.value = false
    await loadTermsForYear(deleteTermYearId.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa học kỳ.')
  } finally {
    loading.value = false
  }
}

function confirmDeleteYear(year: AcademicYear) {
  deleteYearId.value = year.id
  showDeleteYear.value = true
}

function confirmDeleteTerm(term: Term, yearId: Id) {
  deleteTermId.value = term.id
  deleteTermYearId.value = yearId
  showDeleteTerm.value = true
}

function termStatusLabel(status: string) {
  return { upcoming: 'Sắp diễn ra', ongoing: 'Đang diễn ra', completed: 'Đã kết thúc' }[status] ?? status
}
function termStatusClass(status: string) {
  return { upcoming: 'badge-upcoming', ongoing: 'badge-ongoing', completed: 'badge-completed' }[status] ?? ''
}
function fmtDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <AdminWorkspaceShell
    title="Năm Học & Học Kỳ"
    description="Quản lý lịch học vụ toàn trường theo từng năm học và các kỳ chính, kỳ phụ"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Năm Học & Học Kỳ']"
  >
    <template #actions>
      <button class="crud-primary-btn" @click="openCreateYear">
        <Plus :size="16" /> Tạo năm học mới
      </button>
    </template>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <RefreshCw :size="24" class="spin text-muted" />
      <span>Đang tải dữ liệu...</span>
    </div>

    <!-- Empty -->
    <div v-else-if="academicYears.length === 0" class="empty-state-page dashboard-card">
      <CalendarDays :size="48" class="text-muted" />
      <h3>Chưa có năm học nào</h3>
      <p>Bắt đầu bằng cách tạo năm học đầu tiên cho trường.</p>
      <button class="crud-primary-btn" @click="openCreateYear"><Plus :size="16" /> Tạo năm học</button>
    </div>

    <!-- Academic Years Accordion -->
    <div v-else class="years-list">
      <div
        v-for="year in academicYears"
        :key="year.id"
        class="year-card dashboard-card"
        :class="{ 'is-current': year.is_current }"
      >
        <!-- Year Header Row -->
        <div class="year-header" @click="toggleYear(year.id)">
          <div class="year-header-left">
            <div class="year-icon" :class="{ 'is-current': year.is_current }">
              <Calendar :size="18" />
            </div>
            <div class="year-info">
              <div class="year-title-row">
                <h3 class="year-name">{{ year.name }}</h3>
                <span v-if="year.is_current" class="current-badge"><Star :size="11" /> Năm học hiện tại</span>
              </div>
              <span class="year-range">{{ fmtDate(year.start_date) }} — {{ fmtDate(year.end_date) }}</span>
            </div>
          </div>
          <div class="year-header-right">
            <span class="term-count" v-if="termsMap[year.id]">
              {{ termsMap[year.id].length }} học kỳ
            </span>
            <button class="icon-btn" @click.stop="openEditYear(year)" title="Chỉnh sửa năm học">
              <Edit :size="15" />
            </button>
            <button class="icon-btn text-danger" @click.stop="confirmDeleteYear(year)" title="Xóa năm học">
              <Trash2 :size="15" />
            </button>
            <span class="expand-btn">
              <ChevronDown :size="18" v-if="!expandedYearIds.has(year.id)" />
              <ChevronUp :size="18" v-else />
            </span>
          </div>
        </div>

        <!-- Terms Panel -->
        <div v-if="expandedYearIds.has(year.id)" class="terms-panel">
          <div v-if="loadingTerms.has(year.id)" class="terms-loading">
            <RefreshCw :size="16" class="spin text-muted" /> Đang tải học kỳ...
          </div>
          <div v-else>
            <!-- Terms Table -->
            <div class="terms-table-wrap" v-if="termsMap[year.id]?.length > 0">
              <table class="terms-table">
                <thead>
                  <tr>
                    <th style="width:80px">Loại</th>
                    <th>Tên học kỳ</th>
                    <th style="width:80px">Mã</th>
                    <th>Thời gian học</th>
                    <th>Ghi danh</th>
                    <th>Khảo thí</th>
                    <th style="width:110px">Trạng thái</th>
                    <th style="width:90px">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="term in termsMap[year.id]" :key="term.id" :class="{ 'row-current': term.is_current }">
                    <td>
                      <span class="type-badge" :class="term.is_supplementary ? 'type-sup' : 'type-main'">
                        {{ term.is_supplementary ? 'Phụ' : 'Chính' }}
                      </span>
                    </td>
                    <td>
                      <div class="term-name-cell">
                        <strong>{{ term.name }}</strong>
                        <span v-if="term.is_current" class="current-dot"><Clock :size="11" /> Hiện tại</span>
                      </div>
                    </td>
                    <td><span class="term-code">{{ term.code }}</span></td>
                    <td>
                      <div class="date-range">
                        <span>{{ fmtDate(term.start_date) }}</span>
                        <span class="date-sep">→</span>
                        <span>{{ fmtDate(term.end_date) }}</span>
                      </div>
                    </td>
                    <td>
                      <div v-if="term.enrollment_start_at" class="date-range small">
                        <span>{{ fmtDate(term.enrollment_start_at) }}</span>
                        <span class="date-sep">→</span>
                        <span>{{ fmtDate(term.enrollment_end_at || '') }}</span>
                      </div>
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td>
                      <div v-if="term.exam_start_at" class="date-range small">
                        <span>{{ fmtDate(term.exam_start_at) }}</span>
                        <span class="date-sep">→</span>
                        <span>{{ fmtDate(term.exam_end_at || '') }}</span>
                      </div>
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td>
                      <span class="status-badge" :class="termStatusClass(term.status)">
                        {{ termStatusLabel(term.status) }}
                      </span>
                    </td>
                    <td>
                      <div class="row-actions">
                        <button class="icon-btn text-primary" @click="openEditTerm(term, year.id)" title="Chỉnh sửa">
                          <Edit :size="14" />
                        </button>
                        <button class="icon-btn text-danger" @click="confirmDeleteTerm(term, year.id)" title="Xóa">
                          <Trash2 :size="14" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="no-terms">Năm học này chưa có học kỳ nào.</div>

            <!-- Add Term Actions -->
            <div class="add-term-actions">
              <button class="add-term-btn" @click="openCreateTerm(year.id, false)">
                <Plus :size="14" /> Thêm học kỳ chính
              </button>
              <button class="add-term-btn add-term-btn--sup" @click="openCreateTerm(year.id, true)">
                <Plus :size="14" /> Thêm kỳ phụ
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Year Modal ── -->
    <Teleport to="body">
      <div v-if="yearModalOpen" class="crud-modal-backdrop" @click.self="yearModalOpen = false">
        <div class="crud-modal" style="width:min(100%,520px)">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ yearModalMode === 'create' ? 'Tạo mới' : 'Chỉnh sửa' }}</p>
              <h3>{{ yearModalMode === 'create' ? 'Tạo năm học mới' : `Cập nhật: ${selectedYear?.name}` }}</h3>
            </div>
            <button class="topbar-ghost" @click="yearModalOpen = false">✕</button>
          </div>
          <div class="crud-modal-body">
            <div class="crud-form-grid">
              <label class="crud-field crud-field-full">
                <span>Tên năm học *</span>
                <input v-model="yearForm.name" placeholder="Ví dụ: Năm học 2024-2025" />
              </label>
              <div class="crud-field">
                <span>Ngày bắt đầu *</span>
                <UiDatePicker v-model="yearForm.start_date" />
              </div>
              <div class="crud-field">
                <span>Ngày kết thúc *</span>
                <UiDatePicker v-model="yearForm.end_date" />
              </div>
              <label class="crud-field crud-field-full">
                <span>Trạng thái</span>
                <select v-model="yearForm.status">
                  <option value="active">Hoạt động</option>
                  <option value="inactive">Tạm dừng</option>
                </select>
              </label>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" @click="yearModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" :disabled="saving" @click="saveYear">
              {{ saving ? 'Đang lưu...' : 'Lưu năm học' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Term Modal ── -->
    <Teleport to="body">
      <div v-if="termModalOpen" class="crud-modal-backdrop" @click.self="termModalOpen = false">
        <div class="crud-modal" style="width:min(100%,680px)">
          <div class="crud-modal-head" :class="termForm.is_supplementary ? 'is-supplementary' : ''">
            <div>
              <p class="section-kicker">
                {{ termModalMode === 'create' ? 'Tạo mới' : 'Chỉnh sửa' }} •
                {{ termForm.is_supplementary ? 'Kỳ học phụ' : 'Kỳ học chính' }}
              </p>
              <h3>{{ termModalMode === 'create' ? (termForm.is_supplementary ? 'Tạo kỳ học phụ' : 'Tạo học kỳ chính') : `Cập nhật: ${selectedTerm?.name}` }}</h3>
            </div>
            <button class="topbar-ghost" @click="termModalOpen = false">✕</button>
          </div>
          <div class="crud-modal-body">
            <div class="crud-form-grid">
              <label class="crud-field">
                <span>Tên học kỳ *</span>
                <input v-model="termForm.name" placeholder="Ví dụ: Học kỳ 1 (2024-2025)" />
              </label>
              <label class="crud-field">
                <span>Mã học kỳ *</span>
                <input v-model="termForm.code" placeholder="Ví dụ: HK1-2425" />
              </label>
              <div class="crud-field">
                <span>Ngày bắt đầu kỳ học *</span>
                <UiDatePicker v-model="termForm.start_date" />
              </div>
              <div class="crud-field">
                <span>Ngày kết thúc kỳ học *</span>
                <UiDatePicker v-model="termForm.end_date" />
              </div>

              <div class="form-section-divider crud-field-full">
                <span>Thời gian ghi danh (tùy chọn)</span>
              </div>
              <div class="crud-field">
                <span>Ghi danh từ</span>
                <UiDatePicker v-model="termForm.enrollment_start_at" />
              </div>
              <div class="crud-field">
                <span>Ghi danh đến</span>
                <UiDatePicker v-model="termForm.enrollment_end_at" />
              </div>

              <div class="form-section-divider crud-field-full">
                <span>Thời gian khảo thí (tùy chọn)</span>
              </div>
              <div class="crud-field">
                <span>Thi từ</span>
                <UiDatePicker v-model="termForm.exam_start_at" />
              </div>
              <div class="crud-field">
                <span>Thi đến</span>
                <UiDatePicker v-model="termForm.exam_end_at" />
              </div>

              <label class="crud-field crud-field-full">
                <span>Trạng thái</span>
                <select v-model="termForm.status">
                  <option value="upcoming">Sắp diễn ra</option>
                  <option value="ongoing">Đang diễn ra</option>
                  <option value="completed">Đã kết thúc</option>
                </select>
              </label>
              <label class="crud-field crud-field-full toggle-field">
                <input type="checkbox" v-model="termForm.is_current" />
                <span>Đây là <strong>học kỳ đang hiện hành</strong> của toàn trường</span>
              </label>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" @click="termModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" :disabled="saving" @click="saveTerm">
              {{ saving ? 'Đang lưu...' : 'Lưu học kỳ' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Confirms -->
    <CrudConfirmModal
      :open="showDeleteYear"
      title="Xóa Năm Học"
      description="Bạn có chắc muốn xóa năm học này? Tất cả học kỳ thuộc năm học sẽ bị xóa theo."
      confirm-text="Xóa năm học"
      :loading="loading"
      @close="showDeleteYear = false"
      @confirm="deleteYear"
    />
    <CrudConfirmModal
      :open="showDeleteTerm"
      title="Xóa Học Kỳ"
      description="Bạn có chắc muốn xóa học kỳ này? Dữ liệu ghi danh liên quan sẽ bị ảnh hưởng."
      confirm-text="Xóa học kỳ"
      :loading="loading"
      @close="showDeleteTerm = false"
      @confirm="deleteTerm"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

/* Loading / Empty */
.loading-state {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 48px;
  justify-content: center;
  color: #888;
}
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.empty-state-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  text-align: center;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0,0,0,0.05);
  gap: 12px;
  margin-top: 20px;
}
.empty-state-page h3 { font-size: 1.1rem; font-weight: 700; color: #444; margin: 0; }
.empty-state-page p { color: #888; font-size: 0.9rem; margin: 0; }

/* Years list */
.years-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 20px;
}

.year-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0,0,0,0.05);
  box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  overflow: hidden;
  transition: border-color 200ms ease;
}
.year-card.is-current {
  border-color: rgba(var(--green-rgb, 16,185,129), 0.3);
}

.year-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 20px;
  cursor: pointer;
  user-select: none;
  transition: background 150ms ease;
}
.year-header:hover { background: rgba(0,0,0,0.012); }

.year-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.year-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(0,0,0,0.04);
  color: #888;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.year-icon.is-current {
  background: rgba(var(--green-rgb, 16,185,129), 0.1);
  color: var(--green-deep, #047857);
}

.year-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.year-name {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}
.year-range {
  font-size: 0.8rem;
  color: #888;
  margin-top: 2px;
  display: block;
}

.current-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--green-deep, #047857);
  background: rgba(var(--green-rgb, 16,185,129), 0.1);
  padding: 2px 8px;
  border-radius: 99px;
}

.year-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.term-count {
  font-size: 0.78rem;
  color: #888;
  font-weight: 600;
  background: rgba(0,0,0,0.04);
  padding: 3px 10px;
  border-radius: 8px;
}
.expand-btn { color: #aaa; display: inline-flex; }

.icon-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  color: #aaa;
  display: inline-flex;
  align-items: center;
  transition: all 120ms;
}
.icon-btn:hover { background: rgba(0,0,0,0.05); color: #555; }
.icon-btn.text-primary:hover { color: var(--green-deep, #047857); background: rgba(16,185,129,0.08); }
.icon-btn.text-danger:hover { color: #ef4444; background: #fef2f2; }

/* Terms Panel */
.terms-panel {
  border-top: 1px solid rgba(0,0,0,0.05);
  padding: 16px 20px 20px;
  background: rgba(0,0,0,0.008);
}
.terms-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #888;
  font-size: 0.85rem;
  padding: 16px 0;
}
.no-terms {
  padding: 20px 0;
  color: #aaa;
  font-size: 0.85rem;
  font-style: italic;
}

.terms-table-wrap { overflow-x: auto; }
.terms-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
}
.terms-table th {
  text-align: left;
  padding: 8px 12px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #888;
  border-bottom: 1px solid rgba(0,0,0,0.07);
  white-space: nowrap;
}
.terms-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(0,0,0,0.04);
  vertical-align: middle;
}
.terms-table tr:last-child td { border-bottom: none; }
.terms-table tr.row-current td { background: rgba(var(--green-rgb,16,185,129),0.03); }

.type-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 6px;
}
.type-main { background: rgba(59,130,246,0.08); color: #3b82f6; }
.type-sup { background: rgba(245,158,11,0.08); color: #d97706; }

.term-name-cell { display: flex; flex-direction: column; gap: 2px; }
.current-dot {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.7rem;
  color: var(--green-deep, #047857);
  font-weight: 600;
}
.term-code {
  font-family: monospace;
  font-size: 0.78rem;
  font-weight: 700;
  color: #555;
  background: rgba(0,0,0,0.04);
  padding: 2px 6px;
  border-radius: 4px;
}
.date-range {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.8rem;
  white-space: nowrap;
}
.date-range.small { font-size: 0.75rem; }
.date-sep { color: #bbb; }

.status-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.badge-upcoming { background: rgba(245,158,11,0.08); color: #d97706; }
.badge-ongoing { background: rgba(16,185,129,0.1); color: #10b981; }
.badge-completed { background: rgba(0,0,0,0.05); color: #888; }

.row-actions { display: flex; gap: 4px; }

/* Add Term Actions */
.add-term-actions {
  display: flex;
  gap: 10px;
  margin-top: 14px;
  flex-wrap: wrap;
}
.add-term-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px dashed rgba(var(--green-rgb,16,185,129),0.4);
  background: rgba(var(--green-rgb,16,185,129),0.04);
  color: var(--green-deep, #047857);
  transition: all 150ms ease;
}
.add-term-btn:hover {
  background: var(--green-deep, #047857);
  color: #fff;
  border-style: solid;
  border-color: var(--green-deep, #047857);
}
.add-term-btn--sup {
  border-color: rgba(245,158,11,0.4);
  background: rgba(245,158,11,0.04);
  color: #d97706;
}
.add-term-btn--sup:hover {
  background: #d97706;
  color: #fff;
  border-color: #d97706;
}

/* Form helpers */
.form-section-divider {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #888;
  padding: 4px 0 2px;
  border-bottom: 1px solid rgba(0,0,0,0.07);
  margin-top: 6px;
}
.toggle-field {
  display: flex !important;
  flex-direction: row !important;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}
.toggle-field input[type="checkbox"] { width: 16px; height: 16px; flex-shrink: 0; }

.crud-modal-head.is-supplementary {
  background: linear-gradient(135deg, rgba(245,158,11,0.08) 0%, rgba(255,255,255,0) 100%);
  border-bottom-color: rgba(245,158,11,0.15);
}
</style>
