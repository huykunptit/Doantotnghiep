<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tổ chức đào tạo' },
  { label: 'Ngành' }
]

const kpis = [
  { label: 'Tổng ngành', value: 38, sub: 'thuộc các khoa', icon: 'pi pi-sitemap', tone: 'green' },
  { label: 'Đang tuyển sinh', value: 31, sub: 'ngành đang mở', icon: 'pi pi-check-circle', tone: 'blue' },
  { label: 'Chuyên ngành', value: 96, sub: 'nhánh đào tạo', icon: 'pi pi-share-alt', tone: 'violet' },
  { label: 'Tạm dừng', value: 3, sub: 'không tuyển sinh', icon: 'pi pi-pause-circle', tone: 'amber' }
]

const actions = [
  { label: 'Thêm ngành', description: 'Tạo ngành đào tạo mới', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán vào khoa', description: 'Thiết lập khoa quản lý', icon: 'pi pi-link', tone: 'blue' },
  { label: 'Import ngành', description: 'Nhập danh sách ngành', icon: 'pi pi-upload', tone: 'violet' }
]

const records = [
  { id: 1, title: 'Công nghệ thông tin', subtitle: 'Mã ngành: 7480201 • Khoa CNTT', owner: 'Khoa CNTT', count: 5, status: 'Đang tuyển sinh', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'An toàn thông tin', subtitle: 'Mã ngành: 7480202 • Khoa ATTT', owner: 'Khoa ATTT', count: 4, status: 'Đang tuyển sinh', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'Kỹ thuật điện tử viễn thông', subtitle: 'Mã ngành: 7520207 • Khoa DTVT', owner: 'Khoa DTVT', count: 6, status: 'Đang tuyển sinh', statusTone: 'green', updated: '2 ngày trước' },
  { id: 4, title: 'Hệ thống thông tin quản lý', subtitle: 'Mã ngành: 7340405 • Khoa QTKD', owner: 'Khoa QTKD', count: 2, status: 'Tạm dừng', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Quản lý ngành"
    subtitle="Quản lý ngành đào tạo trực thuộc khoa và làm nền cho chuyên ngành/chương trình đào tạo."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm ngành, mã ngành, khoa quản lý..."
    primary-action-label="Thêm ngành"
    primary-action-icon="pi pi-plus"
  />
</template>