<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tổ chức đào tạo' },
  { label: 'Chuyên ngành' }
]

const kpis = [
  { label: 'Chuyên ngành', value: 96, sub: 'đang quản lý', icon: 'pi pi-share-alt', tone: 'green' },
  { label: 'Đang mở', value: 82, sub: 'có sinh viên theo học', icon: 'pi pi-check-circle', tone: 'blue' },
  { label: 'Chương trình liên kết', value: 44, sub: 'có CTĐT tương ứng', icon: 'pi pi-book', tone: 'violet' },
  { label: 'Cần cập nhật', value: 6, sub: 'thiếu mã/chủ quản', icon: 'pi pi-exclamation-triangle', tone: 'amber' }
]

const actions = [
  { label: 'Thêm chuyên ngành', description: 'Tạo nhánh đào tạo', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán ngành cha', description: 'Liên kết chuyên ngành với ngành', icon: 'pi pi-link', tone: 'blue' },
  { label: 'Đồng bộ CTĐT', description: 'Liên kết với chương trình đào tạo', icon: 'pi pi-sync', tone: 'violet' }
]

const records = [
  { id: 1, title: 'Kỹ thuật phần mềm', subtitle: 'Ngành Công nghệ thông tin', owner: 'Bộ môn CNPM', count: 860, status: 'Đang mở', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Trí tuệ nhân tạo', subtitle: 'Ngành Công nghệ thông tin', owner: 'Bộ môn AI', count: 420, status: 'Đang mở', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'An toàn hệ thống thông tin', subtitle: 'Ngành An toàn thông tin', owner: 'Bộ môn ATHT', count: 360, status: 'Đang mở', statusTone: 'green', updated: '2 ngày trước' },
  { id: 4, title: 'Mạng máy tính', subtitle: 'Ngành Công nghệ thông tin', owner: 'Bộ môn Mạng', count: 240, status: 'Cần cập nhật', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Quản lý chuyên ngành"
    subtitle="Quản lý chuyên ngành thuộc ngành đào tạo, phục vụ phân lớp và chương trình đào tạo."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm chuyên ngành, ngành cha, bộ môn..."
    primary-action-label="Thêm chuyên ngành"
    primary-action-icon="pi pi-plus"
  />
</template>