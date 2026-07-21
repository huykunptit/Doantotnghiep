<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Đào tạo & Học vụ' },
  { label: 'Năm học' }
]

const kpis = [
  { label: 'Năm học', value: 6, sub: 'được cấu hình', icon: 'pi pi-calendar', tone: 'green' },
  { label: 'Học kỳ', value: 18, sub: 'bao gồm kỳ phụ', icon: 'pi pi-clock', tone: 'blue' },
  { label: 'Đang mở', value: '2025-2026', sub: 'năm học hiện tại', icon: 'pi pi-check-circle', tone: 'violet' },
  { label: 'Lịch cần rà soát', value: 3, sub: 'mốc thời gian chưa khóa', icon: 'pi pi-exclamation-triangle', tone: 'amber' }
]

const actions = [
  { label: 'Thêm năm học', description: 'Tạo năm học mới', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Tạo học kỳ', description: 'Cấu hình kỳ học', icon: 'pi pi-calendar-plus', tone: 'blue' },
  { label: 'Khóa lịch', description: 'Chốt mốc đào tạo', icon: 'pi pi-lock', tone: 'violet' }
]

const records = [
  { id: 1, title: 'Năm học 2025-2026', subtitle: 'HK1, HK2, Học kỳ phụ', owner: 'Phòng Đào tạo', count: 3, status: 'Đang mở', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Năm học 2024-2025', subtitle: 'Đã hoàn tất tổng kết', owner: 'Phòng Đào tạo', count: 3, status: 'Đã khóa', statusTone: 'blue', updated: '1 tháng trước' },
  { id: 3, title: 'Năm học 2023-2024', subtitle: 'Lưu trữ lịch đào tạo', owner: 'Phòng Đào tạo', count: 3, status: 'Lưu trữ', statusTone: 'slate', updated: 'Năm trước' },
  { id: 4, title: 'Năm học 2026-2027', subtitle: 'Dự kiến khung thời gian', owner: 'Ban học vụ', count: 2, status: 'Bản nháp', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Năm học"
    subtitle="Quản lý năm học, học kỳ, kỳ phụ và các mốc thời gian học vụ."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm năm học, học kỳ, trạng thái..."
    primary-action-label="Thêm năm học"
    primary-action-icon="pi pi-plus"
  />
</template>