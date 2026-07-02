<script setup lang="ts">
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

const route = useRoute()
const courseId = route.params.id

definePageMeta({
  layout: 'instructor',
  middleware: 'instructor',
})
</script>

<template>
  <InstructorWorkspaceShell
    title="Ngân hàng câu hỏi"
    :description="`Quản lý bộ câu hỏi cho khoá học #${courseId}`"
    :breadcrumb="['Trang chủ', 'Khóa học', 'Ngân hàng câu hỏi']"
  >
    <template #actions>
      <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Quay lại Curriculum
      </NuxtLink>
    </template>

    <QuestionBankManager :course-id="Number(courseId)" />
  </InstructorWorkspaceShell>
</template>
