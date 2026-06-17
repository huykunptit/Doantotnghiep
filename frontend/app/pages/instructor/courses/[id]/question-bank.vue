<script setup lang="ts">
const route = useRoute()
const courseId = route.params.id

definePageMeta({
  layout: 'instructor',
  middleware: 'instructor',
})
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên</p>
        <h2>Ngân hàng câu hỏi</h2>
        <p>Quản lý bộ câu hỏi cho khoá học #{{ courseId }}</p>
      </div>
      <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="crud-secondary-btn">
        ← Quay lại Curriculum
      </NuxtLink>
    </header>

    <QuestionBankManager :course-id="Number(courseId)" />
  </section>
</template>
