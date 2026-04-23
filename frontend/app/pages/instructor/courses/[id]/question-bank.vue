<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Ngân hàng câu hỏi" :description="`Quản lý bộ câu hỏi cho khóa học #${courseId}`">
        <template #actions>
          <UiButton to="/instructor/courses" variant="secondary">Quay lại</UiButton>
        </template>
      </AppPageHeader>

      <UiCard>
        <QuestionBankManager :course-id="Number(courseId)" />
      </UiCard>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
const route = useRoute()
const courseId = route.params.id

definePageMeta({
  layout: 'instructor',
  middleware: 'auth',
})
</script>
