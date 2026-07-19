<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tổ chức đào tạo' },
  { label: 'Khoa' }
]

const kpis = [
  { label: 'Tổng số khoa', value: 12, sub: 'đơn vị đào tạo chính', icon: 'pi pi-building', tone: 'green' },
  { label: 'Ngành trực thuộc', value: 38, sub: 'đang đào tạo', icon: 'pi pi-sitemap', tone: 'blue' },
  { label: 'Giảng viên', value: 420, sub: 'thuộc các khoa', icon: 'pi pi-users', tone: 'violet' },
  { label: 'Đang rà soát', value: 2, sub: 'cần cập nhật thông tin', icon: 'pi pi-exclamation-triangle', tone: 'amber' }
]

const actions = [
  { label: 'Thêm khoa', description: 'Tạo khoa mới', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Import khoa', description: 'Nhập từ file', icon: 'pi pi-upload', tone: 'blue' },
  { label: 'Sắp xếp thứ tự', description: 'Điều chỉnh hiển thị', icon: 'pi pi-sort-alt', tone: 'violet' }
]

const records = [
  { id: 1, title: 'Khoa Công nghệ thông tin', subtitle: 'Mã khoa: CNTT', owner: 'PGS. Nguyễn Minh', count: 8, status: 'Đang hoạt động', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Khoa An toàn thông tin', subtitle: 'Mã khoa: ATTT', owner: 'TS. Trần Hoàng', count: 5, status: 'Đang hoạt động', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'Khoa Điện tử Viễn thông', subtitle: 'Mã khoa: DTVT', owner: 'PGS. Lê Quang', count: 7, status: 'Đang hoạt động', statusTone: 'green', updated: '2 ngày trước' },
  { id: 4, title: 'Khoa Quản trị kinh doanh', subtitle: 'Mã khoa: QTKD', owner: 'TS. Phạm Hương', count: 4, status: 'Cần rà soát', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Quản lý khoa"
    subtitle="Quản lý các khoa/đơn vị đào tạo cấp cao trong sơ đồ tổ chức."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm khoa, mã khoa, trưởng khoa..."
    primary-action-label="Thêm khoa"
    primary-action-icon="pi pi-plus"
  />
</template>