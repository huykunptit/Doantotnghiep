<script setup lang="ts">
definePageMeta({ layout: false })

const cards = [
  { label: 'Khóa đang dạy', value: 8, icon: 'pi pi-book', tone: 'text-blue-700 bg-blue-50 border-blue-100' },
  { label: 'Học viên', value: 426, icon: 'pi pi-users', tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { label: 'Bài cần chấm', value: 24, icon: 'pi pi-file-check', tone: 'text-amber-700 bg-amber-50 border-amber-100' },
  { label: 'Câu hỏi mới', value: 12, icon: 'pi pi-comments', tone: 'text-violet-700 bg-violet-50 border-violet-100' }
]

const tasks = [
  { title: 'Chấm bài tập cuối chương', meta: 'Lập trình Web nâng cao • 18 bài nộp', status: 'Cần xử lý' },
  { title: 'Trả lời Q&A học viên', meta: 'Cơ sở dữ liệu ứng dụng • 7 câu hỏi', status: 'Mới' },
  { title: 'Cập nhật bài học video', meta: 'An toàn thông tin cơ bản • bài 04', status: 'Đang soạn' },
  { title: 'Xem báo cáo tiến độ lớp', meta: 'D21CQCN01-B • tuần hiện tại', status: 'Theo dõi' }
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-6 font-['Be_Vietnam_Pro']">
    <div class="mx-auto flex max-w-7xl flex-col gap-6">
      <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Instructor workspace</p>
          <h1 class="mt-2 text-2xl font-bold text-slate-900">Dashboard giảng viên</h1>
          <p class="mt-1 text-sm text-slate-500">Theo dõi khóa học, học viên, bài nộp và tương tác học tập.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <NuxtLink to="/" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50">
            <i class="pi pi-home" /> Trang chủ
          </NuxtLink>
          <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-sm font-bold text-white hover:bg-[#178563]" type="button">
            <i class="pi pi-plus" /> Tạo bài học
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
          <h2 class="text-base font-bold text-slate-900">Việc cần làm</h2>
          <div class="mt-4 divide-y divide-slate-100">
            <div v-for="task in tasks" :key="task.title" class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="font-semibold text-slate-800">{{ task.title }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ task.meta }}</p>
              </div>
              <span class="inline-flex h-7 items-center rounded-full border border-emerald-100 bg-emerald-50 px-3 text-xs font-bold text-emerald-700">{{ task.status }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-base font-bold text-slate-900">Thông báo gần đây</h2>
          <div class="mt-4 space-y-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">3 học viên vừa hoàn thành bài kiểm tra.</div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">Khóa mới đang chờ admin kiểm duyệt.</div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">Có 2 phản hồi mới trong diễn đàn lớp.</div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>