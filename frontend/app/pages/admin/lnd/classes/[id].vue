<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string, email?: string }
interface ClassDetail {
  id: number
  code: string
  name: string
  status: string
  capacity: number
  students_count: number
  expected_graduation_year?: number | null
  description?: string | null
  program?: NamedRef | null
  unit?: NamedRef | null
  cohort?: NamedRef | null
  major?: NamedRef | null
  advisor?: NamedRef | null
  curriculum?: NamedRef | null
}

interface ClassStudent {
  id: number
  name: string
  email: string
  avatar?: string | null
  student_code?: string | null
  study_status?: string | null
  gender?: string | null
  phone?: string | null
  date_of_birth?: string | null
}

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const classId = computed(() => Number(route.params.id))

const loading = ref(true)
const cls = ref<ClassDetail | null>(null)
const students = ref<ClassStudent[]>([])
const studentSearch = ref('')

const statusDialogOpen = ref(false)
const statusTarget = ref<ClassStudent | null>(null)
const statusValue = ref<string | null>(null)
const statusSaving = ref(false)
const removingId = ref<number | null>(null)

const studyStatusOptions = computed(() => [
  { label: t('admin.users.status.dang_hoc'), value: 'dang_hoc' },
  { label: t('admin.users.status.bao_luu'), value: 'bao_luu' },
  { label: t('admin.users.status.tot_nghiep'), value: 'tot_nghiep' },
  { label: t('admin.users.status.thoi_hoc'), value: 'thoi_hoc' },
  { label: t('admin.users.status.dinh_chi'), value: 'dinh_chi' },
  { label: t('admin.users.status.chua_dong_hoc_phi'), value: 'chua_dong_hoc_phi' },
])

const filteredStudents = computed(() => {
  const q = studentSearch.value.trim().toLowerCase()
  if (!q) return students.value
  return students.value.filter(s =>
    s.name?.toLowerCase().includes(q)
    || s.email?.toLowerCase().includes(q)
    || s.student_code?.toLowerCase().includes(q),
  )
})

function statusTone(status?: string | null) {
  if (!status) return 'tone-neutral'
  const map: Record<string, string> = {
    dang_hoc: 'tone-active',
    bao_luu: 'tone-deferred',
    tot_nghiep: 'tone-graduated',
    thoi_hoc: 'tone-dropped',
    dinh_chi: 'tone-suspended',
    chua_dong_hoc_phi: 'tone-unpaid',
  }
  return map[status] || 'tone-neutral'
}

function named(ref?: NamedRef | null) {
  if (!ref) return '—'
  return ref.code ? `${ref.code} — ${ref.name || ''}` : (ref.name || '—')
}

function initials(name?: string) {
  return (name || '?')
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0])
    .join('')
    .toUpperCase()
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ class: ClassDetail, students: ClassStudent[] }>(
      `/admin/academic/administrative-classes/${classId.value}`,
    )
    cls.value = res.class
    students.value = res.students || []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.detail.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function openStatusDialog(student: ClassStudent) {
  statusTarget.value = student
  statusValue.value = student.study_status || 'dang_hoc'
  statusDialogOpen.value = true
}

async function saveStatus() {
  if (!statusTarget.value || !statusValue.value) return
  statusSaving.value = true
  try {
    await useApi(`/admin/users/${statusTarget.value.id}`, {
      method: 'PUT',
      body: { study_status: statusValue.value },
    })
    const row = students.value.find(s => s.id === statusTarget.value!.id)
    if (row) row.study_status = statusValue.value
    toast.add({ severity: 'success', summary: t('admin.classes.detail.statusSaved'), life: 2200 })
    statusDialogOpen.value = false
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.detail.statusSaveError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    statusSaving.value = false
  }
}

function askRemove(student: ClassStudent) {
  confirm.require({
    message: t('admin.classes.detail.removeConfirm', { name: student.name, code: cls.value?.code || '' }),
    header: t('admin.classes.detail.removeConfirmTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      removingId.value = student.id
      try {
        await useApi(`/admin/users/${student.id}`, {
          method: 'PUT',
          body: { administrative_class_id: null },
        })
        students.value = students.value.filter(s => s.id !== student.id)
        if (cls.value) cls.value.students_count = Math.max(0, (cls.value.students_count || 1) - 1)
        toast.add({ severity: 'success', summary: t('admin.classes.detail.removeSuccess', { name: student.name }), life: 2500 })
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.classes.detail.removeError'), detail: error?.data?.message, life: 3500 })
      }
      finally {
        removingId.value = null
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page class-detail-page">
    <NuxtLink to="/admin/lnd/classes" class="back-link">
      <i class="pi pi-arrow-left" />
      <span>{{ t('admin.classes.detail.back') }}</span>
    </NuxtLink>

    <div v-if="loading" class="loading-box">
      <ProgressSpinner style="width:36px;height:36px" stroke-width="4" />
      <span>{{ t('common.loading') }}</span>
    </div>

    <template v-else-if="cls">
      <section class="info-panel">
        <div class="info-head">
          <div>
            <code class="class-code">{{ cls.code }}</code>
            <h1>{{ cls.name }}</h1>
          </div>
          <span class="pill" :class="`tone-${cls.status}`">{{ t(`admin.classes.statuses.${cls.status}`) }}</span>
        </div>
        <div class="info-grid">
          <div class="info-item">
            <span>{{ t('admin.classes.cohort') }}</span>
            <strong>{{ named(cls.cohort) }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.program') }}</span>
            <strong>{{ named(cls.program) }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.major') }}</span>
            <strong>{{ named(cls.major) }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.unit') }}</span>
            <strong>{{ named(cls.unit) }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.advisor') }}</span>
            <strong>{{ cls.advisor?.name || t('admin.classes.noAdvisor') }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.curriculum') }}</span>
            <strong>{{ cls.curriculum?.name || t('admin.classes.noCurriculum') }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.students') }}</span>
            <strong>{{ cls.students_count }} / {{ cls.capacity }}</strong>
          </div>
          <div class="info-item">
            <span>{{ t('admin.classes.graduationYear') }}</span>
            <strong>{{ cls.expected_graduation_year || '—' }}</strong>
          </div>
        </div>
      </section>

      <section class="table-panel">
        <div class="table-toolbar">
          <strong>{{ t('admin.classes.detail.studentsTitle') }} — {{ t('admin.classes.detail.studentsCount', { n: students.length }) }}</strong>
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="studentSearch" :placeholder="t('admin.classes.detail.searchStudentPh')" />
          </IconField>
        </div>

        <DataTable :value="filteredStudents" data-key="id">
          <Column :header="t('admin.classes.detail.colStudent')" style="min-width:220px">
            <template #body="{ data }">
              <div class="student-cell">
                <Avatar v-if="data.avatar" :image="data.avatar" shape="circle" />
                <Avatar v-else :label="initials(data.name)" shape="circle" />
                <div>
                  <strong>{{ data.name }}</strong>
                  <small>{{ data.email }}</small>
                </div>
              </div>
            </template>
          </Column>
          <Column field="student_code" :header="t('admin.classes.detail.colCode')" style="width:150px">
            <template #body="{ data }"><code>{{ data.student_code || '—' }}</code></template>
          </Column>
          <Column :header="t('admin.classes.detail.colStatus')" style="width:150px">
            <template #body="{ data }">
              <span v-if="data.study_status" class="pill" :class="statusTone(data.study_status)">
                {{ t(`admin.users.status.${data.study_status}`) }}
              </span>
              <span v-else>—</span>
            </template>
          </Column>
          <Column :header="t('admin.classes.detail.colActions')" style="width:130px">
            <template #body="{ data }">
              <Button
                icon="pi pi-flag"
                text
                rounded
                severity="secondary"
                :aria-label="t('admin.classes.detail.changeStatus')"
                :title="t('admin.classes.detail.changeStatus')"
                @click="openStatusDialog(data)"
              />
              <Button
                icon="pi pi-user-minus"
                text
                rounded
                severity="danger"
                :loading="removingId === data.id"
                :aria-label="t('admin.classes.detail.removeFromClass')"
                :title="t('admin.classes.detail.removeFromClass')"
                @click="askRemove(data)"
              />
            </template>
          </Column>
          <template #empty>
            <CommonEmptyState :description="t('admin.classes.detail.noStudents')" />
          </template>
        </DataTable>
      </section>
    </template>

    <CommonEmptyState v-else :description="t('admin.classes.detail.notFound')" />

    <Dialog
      v-model:visible="statusDialogOpen"
      modal
      :header="t('admin.classes.detail.changeStatusTitle', { name: statusTarget?.name || '' })"
      :style="{ width: 'min(420px, 96vw)' }"
    >
      <label class="field">
        <span>{{ t('admin.users.studyStatus') }}</span>
        <Select
          v-model="statusValue"
          :options="studyStatusOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="statusDialogOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="statusSaving" @click="saveStatus" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.class-detail-page { gap: 14px; }

.back-link {
  display: inline-flex; align-items: center; gap: 8px; width: fit-content;
  color: var(--text-muted); font-size: .85rem; font-weight: 700; text-decoration: none;
}
.back-link:hover { color: var(--brand); }

.loading-box {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  min-height: 240px; color: var(--text-muted);
}

.info-panel, .table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 18px;
}

.info-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.class-code { font-family: ui-monospace, monospace; font-size: .8rem; font-weight: 700; color: var(--brand); }
.info-head h1 { margin: 4px 0 0; font-size: clamp(1.3rem, 2vw, 1.6rem); }

.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px 16px; }
.info-item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.info-item > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.info-item > strong { font-size: .92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

@media (max-width: 900px) {
  .info-grid { grid-template-columns: repeat(2, 1fr); }
}

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 12px; flex-wrap: wrap;
}
.table-toolbar strong { font-size: .92rem; }

.student-cell { display: flex; align-items: center; gap: 10px; }
.student-cell strong { display: block; font-size: .88rem; }
.student-cell small { color: var(--text-muted); font-size: .76rem; }

code { font-family: ui-monospace, monospace; font-size: .82rem; font-weight: 700; color: var(--brand); }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-active { background: #dcfce7; color: #15803d; }
.tone-deferred { background: #fef9c3; color: #a16207; }
.tone-graduated { background: #e0f2fe; color: #0369a1; }
.tone-dropped { background: #ffe4e6; color: #be123c; }
.tone-suspended { background: #fce7f3; color: #be185d; }
.tone-unpaid { background: #ffedd5; color: #c2410c; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }
</style>
