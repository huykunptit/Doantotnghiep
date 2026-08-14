<script setup lang="ts">
definePageMeta({
  layout: 'student',
  middleware: ['auth', 'student'],
})

const { t } = useI18n()
useHead({ title: () => t('student.account.title') })
</script>

<template>
  <div class="page">
    <CommonAccountProfileForm />
  </div>
</template>
