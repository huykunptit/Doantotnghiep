<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Quản trị L&D' }, { label: 'Lớp học phần' }]

const kpis = [
  { label: 'Lớp học phần', value: 84, sub: 'đang mở trong kỳ', icon: 'pi pi-building', tone: 'green' },
  { label: 'Học viên ghi danh', value: '3,420', sub: 'trên tất cả lớp', icon: 'pi pi-users', tone: 'blue' },
  { label: 'Lịch học tuần này', value: 156, sub: 'ca học online/offline', icon: 'pi pi-calendar', tone: 'amber' },
  { label: 'Cần phân công', value: 9, sub: 'lớp thiếu giảng viên', icon: 'pi pi-exclamation-triangle', tone: 'rose' }
]

const actions = [
  { label: 'Mở lớp học phần', description: 'Tạo lớp mới theo học kỳ', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán giảng viên', description: 'Phân công phụ trách lớp', icon: 'pi pi-user-plus', tone: 'blue' },
  { label: 'Xếp lịch học', description: 'Thiết lập ca học và phòng học', icon: 'pi pi-calendar-plus', tone: 'amber' },
  { label: 'Import danh sách lớp', description: 'Nhập lớp từ file học vụ', icon: 'pi pi-upload', tone: 'violet' }
]

const records = [
  { id: 1, title: 'INT1306-01 • Cấu trúc dữ liệu', subtitle: 'Học kỳ 1 • 2025-2026', owner: 'ThS. Nguyễn Văn A', count: 128, status: 'Đang học', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'WEB2033-02 • Lập trình Web', subtitle: 'Online • Tối thứ 3/5', owner: 'TS. Trần Minh', count: 96, status: 'Đang học', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'AI3011-01 • Nhập môn AI', subtitle: 'Hybrid • Phòng A2-301', owner: 'Chưa phân công', count: 72, status: 'Thiếu giảng viên', statusTone: 'rose', updated: '2 ngày trước' },
  { id: 4, title: 'DBI202-03 • Cơ sở dữ liệu', subtitle: 'Học kỳ phụ • 2025', owner: 'ThS. Lê Hoàng', count: 64, status: 'Sắp mở', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Lớp học phần"
    subtitle="Quản lý lớp học phần, giảng viên phụ trách, lịch học và sĩ số."
    kicker="Học vụ & Đào tạo"
    hero-title="Điều phối lớp học phần theo học kỳ"
    hero-description="Theo dõi toàn bộ lớp học phần, trạng thái mở lớp, lịch học, sĩ số, giảng viên phụ trách và các lớp cần xử lý."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm mã lớp, môn học, giảng viên..."
    primary-action-label="Mở lớp học phần"
    primary-action-icon="pi pi-plus"
  />
</template>