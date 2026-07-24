<script setup lang="ts">
definePageMeta({ layout: false })

const cards = [
  { label: 'Khóa đang học', value: 5, icon: 'pi pi-book', tone: 'text-blue-700 bg-blue-50 border-blue-100' },
  { label: 'Tiến độ trung bình', value: '72%', icon: 'pi pi-chart-line', tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { label: 'Bài cần nộp', value: 3, icon: 'pi pi-file-edit', tone: 'text-amber-700 bg-amber-50 border-amber-100' },
  { label: 'Điểm thưởng', value: 1280, icon: 'pi pi-gift', tone: 'text-violet-700 bg-violet-50 border-violet-100' }
]

const courses = [
  { title: 'Lập trình Web nâng cao', progress: 86, meta: 'Còn 2 bài học' },
  { title: 'Cơ sở dữ liệu ứng dụng', progress: 64, meta: '1 bài tập cần nộp' },
  { title: 'Kỹ năng số cơ bản', progress: 92, meta: 'Sắp hoàn thành' },
  { title: 'An toàn thông tin cơ bản', progress: 48, meta: 'Chậm tiến độ' }
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-6 font-['Be_Vietnam_Pro']">
    <div class="mx-auto flex max-w-7xl flex-col gap-6">
      <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Student workspace</p>
          <h1 class="mt-2 text-2xl font-bold text-slate-900">Dashboard sinh viên</h1>
          <p class="mt-1 text-sm text-slate-500">Theo dõi khóa học, tiến độ, bài tập, khảo thí và thông báo học tập.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <NuxtLink to="/" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50">
            <i class="pi pi-home" /> Trang chủ
          </NuxtLink>
          <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-sm font-bold text-white hover:bg-[#178563]" type="button">
            <i class="pi pi-play" /> Tiếp tục học
          </button>
        </div>
      </header>

      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="card in cards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-bold text-slate-900">{{ card.value }}</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border" :class="card.tone">
              <i :class="card.icon" />
            </span>
          </div>
        </article>
      </section>

      <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_0.7fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-base font-bold text-slate-900">Khóa học của tôi</h2>
          <div class="mt-4 space-y-4">
            <div v-for="course in courses" :key="course.title" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="font-semibold text-slate-800">{{ course.title }}</p>
                  <p class="mt-1 text-xs text-slate-400">{{ course.meta }}</p>
                </div>
                <span class="text-sm font-bold text-slate-800">{{ course.progress }}%</span>
              </div>
              <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-[#1d9e75]" :style="{ width: `${course.progress}%` }" />
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-base font-bold text-slate-900">Việc hôm nay</h2>
          <div class="mt-4 space-y-3">
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-700">Nộp bài tập CSDL trước 23:59.</div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700">Làm quiz An toàn thông tin chương 2.</div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-700">Hoàn thành khảo sát cuối khóa để nhận điểm thưởng.</div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>