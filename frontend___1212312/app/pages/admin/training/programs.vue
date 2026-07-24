<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Đào tạo & Học vụ' },
  { label: 'Chương trình đào tạo' }
]

const kpis = [
  { label: 'Chương trình', value: 44, sub: 'đang áp dụng', icon: 'pi pi-book', tone: 'green' },
  { label: 'Theo khóa tuyển sinh', value: 12, sub: 'phiên bản CTĐT', icon: 'pi pi-calendar', tone: 'blue' },
  { label: 'Học phần', value: 680, sub: 'trong khung chương trình', icon: 'pi pi-list', tone: 'violet' },
  { label: 'Cần phê duyệt', value: 5, sub: 'bản nháp mới', icon: 'pi pi-clock', tone: 'amber' }
]

const actions = [
  { label: 'Tạo CTĐT', description: 'Tạo chương trình mới', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Sao chép CTĐT', description: 'Tạo phiên bản từ CTĐT cũ', icon: 'pi pi-copy', tone: 'blue' },
  { label: 'Import học phần', description: 'Nhập khung chương trình', icon: 'pi pi-upload', tone: 'violet' }
]

const records = [
  { id: 1, title: 'CTĐT Kỹ thuật phần mềm 2025', subtitle: 'Chuyên ngành Kỹ thuật phần mềm • Khóa D25', owner: 'Khoa CNTT', count: 58, status: 'Đang áp dụng', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'CTĐT Trí tuệ nhân tạo 2025', subtitle: 'Chuyên ngành AI • Khóa D25', owner: 'Bộ môn AI', count: 62, status: 'Chờ phê duyệt', statusTone: 'amber', updated: '1 ngày trước' },
  { id: 3, title: 'CTĐT An toàn thông tin 2024', subtitle: 'Ngành An toàn thông tin • Khóa D24', owner: 'Khoa ATTT', count: 60, status: 'Đang áp dụng', statusTone: 'blue', updated: '2 ngày trước' },
  { id: 4, title: 'CTĐT Công nghệ thông tin 2023', subtitle: 'Ngành CNTT • Khóa D23', owner: 'Khoa CNTT', count: 55, status: 'Lưu trữ', statusTone: 'slate', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Chương trình đào tạo"
    subtitle="Quản lý khung chương trình, phiên bản theo khóa tuyển sinh và học phần bắt buộc/tự chọn."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm chương trình, khóa, chuyên ngành..."
    primary-action-label="Tạo CTĐT"
    primary-action-icon="pi pi-plus"
  />
</template>