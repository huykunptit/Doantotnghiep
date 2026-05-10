<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'
import AcademicSiblingNav from '~/components/admin/AcademicSiblingNav.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Lớp học phần']"
    title="Quản lý Lớp học phần"
    description="Mở lớp học phần (course × kỳ × cohort × giảng viên) cho các môn chính quy."
  >
    <AcademicSiblingNav active="class-sections" />
    <AcademicResourceManager :allow-resource-switch="false" initial-resource="class-sections" />
  </AdminWorkspaceShell>
</template>
