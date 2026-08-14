<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { textMatches } from '~/utils/search'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CertOption { id: number, name: string }
interface CourseOption {
  id: number
  title: string
  price?: number
  status?: string
  course_mode?: string
  thumbnail?: string | null
}
interface PathCourse {
  id: number
  career_path_id: number
  course_id: number
  sort_order: number
  is_required: boolean
  milestone_label?: string | null
  course?: CourseOption | null
}
interface CareerPathItem {
  id: number
  title: string
  slug: string
  description?: string | null
  target_role?: string | null
  price: number
  status: string
  cover_url?: string | null
  certificate_template_id?: number | null
  certificate_template?: CertOption | null
  path_courses_count?: number
  path_courses?: PathCourse[]
}

interface Paginator<T> { data: T[], total: number }

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const builderLoading = ref(false)
const syncing = ref(false)

const rows = ref<CareerPathItem[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')
const statusFilter = ref<string | null>(null)

const certOptions = ref<{ label: string, value: number }[]>([])
const allCourses = ref<CourseOption[]>([])

const modalOpen = ref(false)
const editing = ref<CareerPathItem | null>(null)
const form = reactive({
  title: '',
  slug: '',
  description: '',
  target_role: '',
  price: 0,
  status: 'draft',
  cover_url: '',
  certificate_template_id: null as number | null,
})

const builderOpen = ref(false)
const activePath = ref<CareerPathItem | null>(null)
const pathCourses = ref<PathCourse[]>([])
const courseSearch = ref('')
const selectedCourseIds = ref<number[]>([])
const pickerRequired = ref(true)
const pickerMilestone = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('admin.careerPaths.statusDraft'), value: 'draft' },
  { label: t('admin.careerPaths.statusPublished'), value: 'published' },
  { label: t('admin.careerPaths.statusArchived'), value: 'archived' },
])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

const mappedCourseIds = computed(() => new Set(pathCourses.value.map(c => c.course_id)))

const availableCourses = computed(() => {
  const q = courseSearch.value
  return allCourses.value.filter((c) => {
    if (mappedCourseIds.value.has(c.id)) return false
    if (c.course_mode === 'core') return false
    if (!q.trim()) return true
    return textMatches(c.title, q)
  })
})

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<CareerPathItem>>('/admin/career-paths', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value || undefined,
        status: statusFilter.value || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.careerPaths.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const [certs, courses] = await Promise.all([
      useApi<CertOption[] | { data: CertOption[] }>('/admin/certificates'),
      useApi<{ data?: CourseOption[] }>('/courses', {
        query: { per_page: 100, status: 'published' },
      }),
    ])
    const certList = Array.isArray(certs) ? certs : (certs.data || [])
    certOptions.value = certList.map(c => ({ label: c.name, value: c.id }))
    allCourses.value = (courses.data || []).filter(c => c.course_mode !== 'core')
  }
  catch {
    /* ignore lookup errors */
  }
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    title: '',
    slug: '',
    description: '',
    target_role: '',
    price: 0,
    status: 'draft',
    cover_url: '',
    certificate_template_id: null,
  })
  modalOpen.value = true
}

function openEdit(item: CareerPathItem) {
  editing.value = item
  Object.assign(form, {
    title: item.title || '',
    slug: item.slug || '',
    description: item.description || '',
    target_role: item.target_role || '',
    price: item.price || 0,
    status: item.status || 'draft',
    cover_url: item.cover_url || '',
    certificate_template_id: item.certificate_template_id || item.certificate_template?.id || null,
  })
  modalOpen.value = true
}

async function save() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.careerPaths.titleRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const body = {
      title: form.title.trim(),
      slug: form.slug.trim() || undefined,
      description: form.description || null,
      target_role: form.target_role.trim() || null,
      price: Number(form.price) || 0,
      status: form.status,
      cover_url: form.cover_url || null,
      certificate_template_id: form.certificate_template_id,
    }
    if (editing.value) {
      await useApi(`/admin/career-paths/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.careerPaths.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/career-paths', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.careerPaths.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.careerPaths.saveError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    saving.value = false
  }
}

function remove(item: CareerPathItem) {
  confirm.require({
    message: t('admin.careerPaths.deleteConfirm', { name: item.title }),
    header: t('admin.careerPaths.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await useApi(`/admin/career-paths/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.careerPaths.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.careerPaths.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

async function openBuilder(item: CareerPathItem) {
  activePath.value = item
  builderOpen.value = true
  builderLoading.value = true
  selectedCourseIds.value = []
  courseSearch.value = ''
  try {
    const detail = await useApi<CareerPathItem>(`/admin/career-paths/${item.id}`)
    activePath.value = detail
    pathCourses.value = [...(detail.path_courses || [])].sort((a, b) => a.sort_order - b.sort_order)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.careerPaths.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    builderLoading.value = false
  }
}

function moveCourse(index: number, dir: -1 | 1) {
  const next = index + dir
  if (next < 0 || next >= pathCourses.value.length) return
  const copy = [...pathCourses.value]
  const tmp = copy[index]!
  copy[index] = copy[next]!
  copy[next] = tmp
  pathCourses.value = copy.map((c, i) => ({ ...c, sort_order: i }))
}

async function addSelectedCourses() {
  if (!selectedCourseIds.value.length) return
  const existing = new Set(pathCourses.value.map(c => c.course_id))
  for (const id of selectedCourseIds.value) {
    if (existing.has(id)) continue
    const course = allCourses.value.find(c => c.id === id)
    pathCourses.value.push({
      id: 0,
      career_path_id: activePath.value!.id,
      course_id: id,
      sort_order: pathCourses.value.length,
      is_required: pickerRequired.value,
      milestone_label: pickerMilestone.value || null,
      course: course || { id, title: `#${id}` },
    })
  }
  selectedCourseIds.value = []
  pickerMilestone.value = ''
}

async function saveCourses() {
  if (!activePath.value) return
  syncing.value = true
  try {
    const detail = await useApi<CareerPathItem>(`/admin/career-paths/${activePath.value.id}/courses`, {
      method: 'PUT',
      body: {
        courses: pathCourses.value.map((c, i) => ({
          course_id: c.course_id,
          sort_order: i,
          is_required: c.is_required,
          milestone_label: c.milestone_label || null,
        })),
      },
    })
    activePath.value = detail
    pathCourses.value = [...(detail.path_courses || [])].sort((a, b) => a.sort_order - b.sort_order)
    toast.add({ severity: 'success', summary: t('admin.careerPaths.coursesSaved'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.careerPaths.saveError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    syncing.value = false
  }
}

function removePathCourse(index: number) {
  pathCourses.value = pathCourses.value.filter((_, i) => i !== index).map((c, i) => ({ ...c, sort_order: i }))
}

function statusClass(status: string) {
  if (status === 'published') return 'tone-ok'
  if (status === 'archived') return 'tone-muted'
  return 'tone-warn'
}

onMounted(async () => {
  await Promise.all([load(), loadLookups()])
})
</script>

<template>
  <div class="page">
    <header class="page-head">
      <span class="eyebrow">{{ t('admin.menu.courses') }}</span>
      <h1>{{ t('admin.careerPaths.title') }}</h1>
      <p>{{ t('admin.careerPaths.subtitle') }}</p>
    </header>

    <div class="filters">
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        show-clear
        :placeholder="t('admin.careerPaths.filterStatus')"
        class="w-full"
      />
      <div class="filter-actions">
        <Button :label="t('common.apply')" size="small" @click="() => { page = 1; load() }" />
        <Button :label="t('common.reset')" size="small" severity="secondary" text @click="() => { statusFilter = null; page = 1; load() }" />
      </div>
    </div>

    <div class="panel">
      <div class="toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.careerPaths.searchPh')" @input="onTableSearch" />
        </IconField>
        <Button :label="t('admin.careerPaths.add')" icon="pi pi-plus" size="small" @click="openCreate" />
      </div>

      <DataTable :value="rows" :loading="loading" data-key="id" striped-rows>
        <Column header="STT" style="width:64px">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('admin.careerPaths.colTitle')" style="min-width:220px">
          <template #body="{ data }">
            <strong>{{ data.title }}</strong>
            <div class="muted">{{ data.slug }} · {{ data.target_role || '—' }}</div>
          </template>
        </Column>
        <Column :header="t('admin.careerPaths.colCourses')" style="width:100px">
          <template #body="{ data }">{{ data.path_courses_count || 0 }}</template>
        </Column>
        <Column :header="t('admin.careerPaths.colPrice')" style="width:130px">
          <template #body="{ data }">{{ formatPrice(data.price) }}</template>
        </Column>
        <Column :header="t('admin.careerPaths.colStatus')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="statusClass(data.status)">{{ data.status }}</span>
          </template>
        </Column>
        <Column style="width:260px">
          <template #body="{ data }">
            <div class="row-actions">
              <a
                v-if="data.slug"
                :href="`/paths/${data.slug}`"
                target="_blank"
                rel="noopener"
                :title="t('admin.careerPaths.preview')"
              >
                <Button icon="pi pi-eye" size="small" text severity="secondary" :aria-label="t('admin.careerPaths.preview')" />
              </a>
              <Button icon="pi pi-sitemap" size="small" text :title="t('admin.careerPaths.builder')" @click="openBuilder(data)" />
              <Button icon="pi pi-pencil" size="small" text @click="openEdit(data)" />
              <Button icon="pi pi-trash" size="small" text severity="danger" @click="remove(data)" />
            </div>
          </template>
        </Column>
      </DataTable>

      <Paginator
        :rows="perPage"
        :total-records="total"
        :first="(page - 1) * perPage"
        @page="onPage"
      />
    </div>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.careerPaths.edit') : t('admin.careerPaths.add')"
      class="form-dialog"
      :style="{ width: 'min(560px, 94vw)' }"
    >
      <div class="form-grid">
        <label>
          <span>{{ t('admin.careerPaths.fieldTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label>
          <span>{{ t('admin.careerPaths.fieldSlug') }}</span>
          <InputText v-model="form.slug" class="w-full" :placeholder="t('admin.careerPaths.slugPh')" />
        </label>
        <label class="full">
          <span>{{ t('admin.careerPaths.fieldDescription') }}</span>
          <CommonRichTextEditor v-model="form.description" height="180px" />
        </label>
        <label>
          <span>{{ t('admin.careerPaths.fieldRole') }}</span>
          <InputText v-model="form.target_role" class="w-full" placeholder="fullstack_python" />
        </label>
        <label>
          <span>{{ t('admin.careerPaths.fieldPrice') }}</span>
          <InputNumber v-model="form.price" class="w-full" :min="0" />
        </label>
        <label>
          <span>{{ t('admin.careerPaths.fieldStatus') }}</span>
          <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label>
          <span>{{ t('admin.careerPaths.fieldCert') }}</span>
          <Select
            v-model="form.certificate_template_id"
            :options="certOptions"
            option-label="label"
            option-value="value"
            show-clear
            class="w-full"
            :placeholder="t('admin.careerPaths.certPh')"
          />
        </label>
        <label class="full">
          <span>{{ t('admin.careerPaths.fieldCover') }}</span>
          <InputText v-model="form.cover_url" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" :loading="saving" @click="save" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="builderOpen"
      modal
      :header="t('admin.careerPaths.builderTitle', { name: activePath?.title || '' })"
      class="builder-dialog"
      :style="{ width: 'min(920px, 96vw)' }"
    >
      <div v-if="builderLoading" class="muted">…</div>
      <div v-else class="builder">
        <section>
          <h3>{{ t('admin.careerPaths.pathCourses') }}</h3>
          <div v-if="!pathCourses.length" class="muted">{{ t('admin.careerPaths.noCourses') }}</div>
          <ul class="course-list">
            <li v-for="(item, index) in pathCourses" :key="`${item.course_id}-${index}`">
              <div>
                <strong>{{ item.course?.title || `#${item.course_id}` }}</strong>
                <span class="muted">
                  {{ item.is_required ? t('admin.careerPaths.required') : t('admin.careerPaths.optional') }}
                  <template v-if="item.milestone_label"> · {{ item.milestone_label }}</template>
                </span>
              </div>
              <div class="row-actions">
                <Button icon="pi pi-arrow-up" size="small" text :disabled="index === 0" @click="moveCourse(index, -1)" />
                <Button icon="pi pi-arrow-down" size="small" text :disabled="index === pathCourses.length - 1" @click="moveCourse(index, 1)" />
                <Button icon="pi pi-trash" size="small" text severity="danger" @click="removePathCourse(index)" />
              </div>
            </li>
          </ul>
        </section>

        <section>
          <h3>{{ t('admin.careerPaths.addCourses') }}</h3>
          <InputText v-model="courseSearch" class="w-full mb" :placeholder="t('admin.careerPaths.searchCourses')" />
          <MultiSelect
            v-model="selectedCourseIds"
            :options="availableCourses"
            option-label="title"
            option-value="id"
            display="chip"
            filter
            class="w-full mb"
            :placeholder="t('admin.careerPaths.pickCourses')"
          />
          <div class="picker-row">
            <div class="check">
              <Checkbox v-model="pickerRequired" binary input-id="req" />
              <label for="req">{{ t('admin.careerPaths.required') }}</label>
            </div>
            <InputText v-model="pickerMilestone" :placeholder="t('admin.careerPaths.milestonePh')" />
            <Button :label="t('admin.careerPaths.addSelected')" size="small" :disabled="!selectedCourseIds.length" @click="addSelectedCourses" />
          </div>
        </section>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="builderOpen = false" />
        <Button :label="t('admin.careerPaths.saveCourses')" :loading="syncing" @click="saveCourses" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: grid; gap: 16px; }
.page-head h1 { margin: 4px 0; }
.page-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.eyebrow { color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.filters {
  display: grid; grid-template-columns: minmax(180px, 240px) auto; gap: 10px; align-items: end;
}
.filter-actions { display: flex; gap: 6px; }
.panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.toolbar { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.muted { color: var(--text-muted); font-size: .86rem; font-weight: 500; }
.pill {
  display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: .78rem; font-weight: 700; text-transform: uppercase;
}
.tone-ok { background: color-mix(in srgb, #16a34a 18%, transparent); color: #166534; }
.tone-warn { background: color-mix(in srgb, #ca8a04 18%, transparent); color: #854d0e; }
.tone-muted { background: var(--surface-subtle); color: var(--text-muted); }
.row-actions { display: flex; gap: 2px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid label { display: grid; gap: 6px; font-weight: 600; font-size: .9rem; }
.form-grid .full { grid-column: 1 / -1; }
.builder { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; }
.builder h3 { margin: 0 0 10px; font-size: 1rem; }
.course-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.course-list li {
  display: flex; justify-content: space-between; gap: 8px; align-items: center;
  padding: 10px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle);
}
.course-list span { display: block; }
.mb { margin-bottom: 10px; }
.picker-row { display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; }
.check { display: flex; gap: 8px; align-items: center; font-weight: 600; white-space: nowrap; }
@media (max-width: 800px) {
  .filters, .form-grid, .builder, .picker-row { grid-template-columns: 1fr; }
}
</style>
