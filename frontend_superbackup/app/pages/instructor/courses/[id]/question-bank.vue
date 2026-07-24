<script setup lang="ts">
const route = useRoute()
const courseId = route.params.id

definePageMeta({
  layout: 'instructor',
  middleware: 'instructor',
})
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học &bull; Câu hỏi</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Ngân hàng câu hỏi</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý bộ câu hỏi cho khoá học #{{ courseId }}</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Quay lại Curriculum</span>
        </NuxtLink>
      </div>
    </div>

    <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
      <QuestionBankManager :course-id="Number(courseId)" />
    </div>
  </div>
</template>

