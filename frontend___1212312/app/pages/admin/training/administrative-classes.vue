<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Đào tạo & Học vụ' },
  { label: 'Lớp hành chính' }
]

const kpis = [
  { label: 'Lớp hành chính', value: 126, sub: 'theo khóa/ngành', icon: 'pi pi-users', tone: 'green' },
  { label: 'Sinh viên', value: '7,820', sub: 'đang quản lý', icon: 'pi pi-user', tone: 'blue' },
  { label: 'Cố vấn học tập', value: 88, sub: 'được phân công', icon: 'pi pi-id-card', tone: 'violet' },
  { label: 'Thiếu cố vấn', value: 7, sub: 'cần phân công', icon: 'pi pi-exclamation-triangle', tone: 'amber' }
]

const actions = [
  { label: 'Tạo lớp hành chính', description: 'Mở lớp theo khóa/ngành', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán sinh viên', description: 'Cập nhật danh sách lớp', icon: 'pi pi-user-plus', tone: 'blue' },
  { label: 'Phân công cố vấn', description: 'Gán CVHT cho lớp', icon: 'pi pi-id-card', tone: 'violet' }
]

const records = [
  { id: 1, title: 'D21CQCN01-B', subtitle: 'Khóa D21 • Ngành Công nghệ thông tin', owner: 'ThS. Nguyễn Văn A', count: 68, status: 'Đang học', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'D22CQAT01-N', subtitle: 'Khóa D22 • Ngành An toàn thông tin', owner: 'TS. Trần Minh', count: 72, status: 'Đang học', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'D23CQCN05-B', subtitle: 'Khóa D23 • Ngành Công nghệ thông tin', owner: 'Chưa phân công', count: 64, status: 'Thiếu cố vấn', statusTone: 'amber', updated: '2 ngày trước' },
  { id: 4, title: 'D20CQVT02-B', subtitle: 'Khóa D20 • Ngành Điện tử viễn thông', owner: 'ThS. Lê Hoàng', count: 58, status: 'Sắp tốt nghiệp', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Lớp hành chính"
    subtitle="Quản lý lớp sinh viên theo khóa, ngành, chuyên ngành và cố vấn học tập."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm lớp, khóa, ngành, cố vấn..."
    primary-action-label="Tạo lớp hành chính"
    primary-action-icon="pi pi-plus"
  />
</template>