<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <div class="rounded-[2rem] border border-surface-dim/50 bg-surface-lowest p-6 shadow-sm md:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl space-y-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-outline">Studio giang day</p>
            <div class="space-y-2">
              <h1 class="text-3xl font-bold tracking-tight text-on-surface md:text-[2.4rem]">Quan ly khoa hoc khong bi roi nhip</h1>
              <p class="text-sm leading-6 text-on-surface-variant md:text-base">
                Theo doi trang thai duyet, cap nhat curriculum va di chuyen nhanh den hoc vien hoac doanh thu tu mot workspace gon gang.
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <NuxtLink
              to="/instructor/question-bank"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-5 py-3 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low hover:shadow-md transition-all cursor-pointer"
            >
              <span class="material-symbols-outlined text-[18px]">database</span>
              Ngan hang cau hoi
            </NuxtLink>
            <NuxtLink
              to="/courses/create"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
            >
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              Tao khoa hoc
            </NuxtLink>
          </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-surface-dim/40 bg-surface px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Tong khoa hoc</p>
            <p class="mt-3 text-3xl font-bold text-on-surface">{{ courses.length }}</p>
            <p class="mt-1 text-sm text-on-surface-variant">Tat ca khoa hoc dang thuoc quyen quan ly cua ban.</p>
          </div>
          <div class="rounded-2xl border border-surface-dim/40 bg-surface px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Tong bai giang</p>
            <p class="mt-3 text-3xl font-bold text-on-surface">{{ totalLessons }}</p>
            <p class="mt-1 text-sm text-on-surface-variant">Bao gom bai video, quiz, assignment va cac buoi hoc song.</p>
          </div>
          <div class="rounded-2xl border border-surface-dim/40 bg-surface px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Tong hoc vien</p>
            <p class="mt-3 text-3xl font-bold text-on-surface">{{ totalEnrollments }}</p>
            <p class="mt-1 text-sm text-on-surface-variant">Luot ghi danh hien co tren toan bo danh sach khoa hoc.</p>
          </div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-surface-dim/50 bg-surface-lowest p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex flex-1 flex-col gap-4 md:flex-row">
            <label class="relative flex-1">
              <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">search</span>
              <input
                v-model="search"
                type="text"
                placeholder="Tim ten khoa hoc, mo ta hoac tu khoa..."
                class="h-12 w-full rounded-2xl border border-surface-dim/50 bg-surface px-12 pr-4 text-sm text-on-surface shadow-sm outline-none transition focus:border-primary/40 focus:ring-4 focus:ring-primary/10"
              >
            </label>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                :class="[
                  'inline-flex min-h-12 items-center rounded-2xl border px-4 text-sm font-bold transition-all',
                  selectedStatus === option.value
                    ? 'border-primary/25 bg-primary text-white shadow-lg shadow-primary/20'
                    : 'border-surface-dim/50 bg-surface text-on-surface hover:-translate-y-0.5 hover:bg-surface-low',
                ]"
                @click="selectedStatus = option.value"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <div class="rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3 text-sm text-on-surface-variant">
            Dang hien thi <strong class="text-on-surface">{{ filteredCourses.length }}</strong> / {{ courses.length }} khoa hoc
          </div>
        </div>
      </div>

      <div v-if="loading" class="grid gap-5">
        <div v-for="item in 4" :key="item" class="h-56 rounded-[2rem] bg-surface-high animate-pulse" />
      </div>

      <UiEmptyState
        v-else-if="filteredCourses.length === 0"
        title="Khong tim thay khoa hoc phu hop"
        description="Thu doi bo loc hoac tao mot khoa hoc moi de bat dau xay dung curriculum."
        class="rounded-[2rem] border border-surface-dim/50 bg-surface-lowest py-20"
      >
        <template #icon>
          <span class="material-symbols-outlined text-5xl opacity-50">school</span>
        </template>
        <NuxtLink
          to="/courses/create"
          class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
        >
          <span class="material-symbols-outlined text-[18px]">add_circle</span>
          Tao khoa hoc
        </NuxtLink>
      </UiEmptyState>

      <div v-else class="grid gap-5">
        <article
          v-for="course in filteredCourses"
          :key="course.id"
          class="overflow-hidden rounded-[2rem] border border-surface-dim/50 bg-surface-lowest shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
        >
          <div class="grid gap-0 xl:grid-cols-[280px_minmax(0,1fr)_280px]">
            <NuxtLink
              :to="`/instructor/courses/${course.id}/curriculum`"
              class="group relative block min-h-[220px] overflow-hidden border-b border-surface-dim/30 xl:min-h-full xl:border-b-0 xl:border-r xl:border-surface-dim/30"
            >
              <img
                v-if="course.thumbnail"
                :src="course.thumbnail"
                :alt="course.title"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
              >
              <div v-else class="flex h-full min-h-[220px] items-center justify-center bg-surface-high text-4xl">📘</div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
              <div class="absolute left-5 top-5">
                <StatusBadge :value="statusText(course.status)" />
              </div>
              <div class="absolute inset-x-0 bottom-0 p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-white/70">Curriculum workspace</p>
                <p class="mt-2 line-clamp-2 text-xl font-bold leading-tight">{{ course.title }}</p>
              </div>
            </NuxtLink>

            <div class="flex min-w-0 flex-col gap-5 p-6 md:p-7">
              <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                    <span class="material-symbols-outlined text-[16px]">library_books</span>
                    {{ course.lessons_count || 0 }} bai giang
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-secondary/10 px-3 py-1 text-xs font-bold text-secondary">
                    <span class="material-symbols-outlined text-[16px]">groups</span>
                    {{ course.enrollments_count || 0 }} hoc vien
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-surface px-3 py-1 text-xs font-bold text-on-surface-variant">
                    <span class="material-symbols-outlined text-[16px]">sell</span>
                    {{ formatPrice(course.price || 0) }}
                  </span>
                </div>

                <div>
                  <NuxtLink
                    :to="`/instructor/courses/${course.id}/curriculum`"
                    class="text-2xl font-bold tracking-tight text-on-surface transition-colors hover:text-primary"
                  >
                    {{ course.title }}
                  </NuxtLink>
                  <p class="mt-3 max-w-3xl text-sm leading-6 text-on-surface-variant">
                    {{ excerpt(course.description) }}
                  </p>
                </div>
              </div>

              <p
                v-if="course.status === 'rejected' && course.reject_reason"
                class="rounded-2xl border border-error/20 bg-error-container/60 px-4 py-3 text-sm font-medium text-error"
              >
                Tu choi duyet: {{ course.reject_reason }}
              </p>

              <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3">
                  <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Tien do</p>
                  <p class="mt-2 text-lg font-bold text-on-surface">{{ progressLabel(course) }}</p>
                  <p class="mt-1 text-sm text-on-surface-variant">San sang dieu chinh noi dung va tai nguyen.</p>
                </div>
                <div class="rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3">
                  <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Preview</p>
                  <p class="mt-2 text-lg font-bold text-on-surface">{{ course.lessons_count > 0 ? 'Da co bai xem thu' : 'Chua co bai xem thu' }}</p>
                  <p class="mt-1 text-sm text-on-surface-variant">Nen dat bai mo dau la preview de tang chuyen doi.</p>
                </div>
                <div class="rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3">
                  <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Nhanh</p>
                  <p class="mt-2 text-lg font-bold text-on-surface">{{ course.status === 'published' ? 'Dang ban' : 'Dang chuan bi' }}</p>
                  <p class="mt-1 text-sm text-on-surface-variant">Di chuyen sang hoc vien hoac doanh thu trong mot cham.</p>
                </div>
              </div>
            </div>

            <div class="flex flex-col justify-between gap-4 border-t border-surface-dim/30 bg-surface/70 p-5 md:p-6 xl:border-l xl:border-t-0 xl:border-surface-dim/30">
              <div class="space-y-3">
                <NuxtLink
                  :to="`/instructor/courses/${course.id}/curriculum`"
                  class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">view_list</span>
                  Quan ly curriculum
                </NuxtLink>
                <NuxtLink
                  :to="`/courses/${course.id}/edit`"
                  class="flex w-full items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-6 py-3 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low hover:shadow-md transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">edit_square</span>
                  Sua va cau hinh
                </NuxtLink>
                <NuxtLink
                  :to="`/courses/${course.id}`"
                  class="flex w-full items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-5 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">visibility</span>
                  Xem trang khoa hoc
                </NuxtLink>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <NuxtLink
                  :to="`/instructor/courses/${course.id}/students`"
                  class="flex w-full items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-4 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">group</span>
                  Hoc vien
                </NuxtLink>
                <NuxtLink
                  :to="`/instructor/courses/${course.id}/revenue`"
                  class="flex w-full items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-4 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">payments</span>
                  Doanh thu
                </NuxtLink>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')
const selectedStatus = ref('all')

const statusOptions = [
  { value: 'all', label: 'Tat ca' },
  { value: 'published', label: 'Da xuat ban' },
  { value: 'pending_review', label: 'Cho duyet' },
  { value: 'draft', label: 'Ban nhap' },
  { value: 'rejected', label: 'Bi tu choi' },
]

const filteredCourses = computed(() => {
  const keyword = search.value.trim().toLowerCase()

  return courses.value.filter((course) => {
    const matchesStatus = selectedStatus.value === 'all' || course.status === selectedStatus.value
    const haystack = `${course.title || ''} ${course.description || ''}`.toLowerCase()
    const matchesKeyword = keyword === '' || haystack.includes(keyword)

    return matchesStatus && matchesKeyword
  })
})

const totalLessons = computed(() => courses.value.reduce((sum, course) => sum + Number(course.lessons_count || 0), 0))
const totalEnrollments = computed(() => courses.value.reduce((sum, course) => sum + Number(course.enrollments_count || 0), 0))

const statusText = (status: string) => {
  const map: Record<string, string> = {
    published: 'Da xuat ban',
    draft: 'Ban nhap',
    pending_review: 'Cho duyet',
    rejected: 'Bi tu choi',
  }

  return map[status] || status
}

const progressLabel = (course: any) => {
  if (course.status === 'published') return 'On dinh va san sang ban'
  if (course.status === 'pending_review') return 'Dang cho kiem duyet'
  if (course.status === 'rejected') return 'Can bo sung noi dung'
  return 'Dang trong giai doan bien soan'
}

const excerpt = (value?: string | null) => {
  const text = String(value || '').replace(/\s+/g, ' ').trim()
  if (!text) return 'Bo sung mo ta ngan gon de giang vien va hoc vien nhin ra gia tri khoa hoc ngay tu danh sach.'
  return text.length > 180 ? `${text.slice(0, 177)}...` : text
}

const formatPrice = (value: number) => {
  if (!value) return 'Mien phi'
  return `${new Intl.NumberFormat('vi-VN').format(value)} d`
}

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>
