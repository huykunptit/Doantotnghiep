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
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Chương trình đào tạo']"
    title="Quản lý Chương trình đào tạo"
    description="Quản lý chương trình đào tạo theo khoa, loại chương trình và thời gian đào tạo."
  >
    <AcademicSiblingNav active="programs" />
    <AcademicResourceManager :allow-resource-switch="false" initial-resource="programs" />
  </AdminWorkspaceShell>
</template>
