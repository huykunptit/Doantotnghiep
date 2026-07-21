<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Người dùng & Tổ chức' }, { label: 'Sơ đồ tổ chức' }]

const kpis = [
  { label: 'Đơn vị đào tạo', value: 12, sub: 'khoa, viện, trung tâm', icon: 'pi pi-building', tone: 'green' },
  { label: 'Chương trình', value: 38, sub: 'đang áp dụng', icon: 'pi pi-sitemap', tone: 'blue' },
  { label: 'Lớp hành chính', value: 126, sub: 'theo khóa tuyển sinh', icon: 'pi pi-users', tone: 'amber' },
  { label: 'Chức danh', value: 18, sub: 'vị trí học vụ', icon: 'pi pi-id-card', tone: 'violet' }
]

const actions = [
  { label: 'Thêm đơn vị', description: 'Tạo khoa, bộ môn, trung tâm', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Thêm lớp hành chính', description: 'Gán khóa, ngành, chương trình', icon: 'pi pi-users', tone: 'blue' },
  { label: 'Quản lý chức danh', description: 'Thiết lập vị trí cán bộ', icon: 'pi pi-id-card', tone: 'violet' },
  { label: 'Import cơ cấu', description: 'Nhập dữ liệu từ Excel/CSV', icon: 'pi pi-upload', tone: 'amber' }
]

const records = [
  { id: 1, title: 'Khoa Công nghệ thông tin', subtitle: 'CNTT • 12 bộ môn trực thuộc', owner: 'PGS. Nguyễn Minh', count: 2450, status: 'Đang hoạt động', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Khoa Điện tử Viễn thông', subtitle: 'DTVT • 8 chương trình đào tạo', owner: 'TS. Trần Hoàng', count: 1320, status: 'Đang hoạt động', statusTone: 'green', updated: '1 ngày trước' },
  { id: 3, title: 'Chương trình Kỹ thuật phần mềm', subtitle: 'SE • Đại học chính quy', owner: 'Bộ môn CNPM', count: 860, status: 'Đang tuyển sinh', statusTone: 'blue', updated: '2 ngày trước' },
  { id: 4, title: 'Lớp D21CQCN01-B', subtitle: 'Khóa D21 • Ngành CNTT', owner: 'Cố vấn học tập A', count: 68, status: 'Đang học', statusTone: 'amber', updated: '3 ngày trước' },
  { id: 5, title: 'Phòng Đào tạo', subtitle: 'Đơn vị quản lý học vụ', owner: 'Trưởng phòng ĐT', count: 24, status: 'Đang hoạt động', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Sơ đồ tổ chức"
    subtitle="Quản lý cơ cấu đơn vị, chương trình, khóa, ngành và lớp hành chính."
    kicker="Người dùng & Tổ chức"
    hero-title="Cấu trúc học vụ và tổ chức đào tạo"
    hero-description="Thiết lập cây đơn vị, chương trình đào tạo, khóa tuyển sinh, lớp hành chính và vị trí chức danh phục vụ phân quyền học vụ."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm đơn vị, chương trình, lớp hành chính..."
    primary-action-label="Thêm cấu trúc"
    primary-action-icon="pi pi-plus"
  />
</template>