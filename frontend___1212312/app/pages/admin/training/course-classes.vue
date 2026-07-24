<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Đào tạo & Học vụ' },
  { label: 'Lớp học phần' }
]

const kpis = [
  { label: 'Lớp học phần', value: 84, sub: 'đang mở trong kỳ', icon: 'pi pi-building', tone: 'green' },
  { label: 'Lượt ghi danh', value: '3,420', sub: 'sinh viên/học viên', icon: 'pi pi-users', tone: 'blue' },
  { label: 'Khóa học liên kết', value: 76, sub: 'course LMS', icon: 'pi pi-book', tone: 'violet' },
  { label: 'Cần xử lý', value: 9, sub: 'thiếu GV hoặc course', icon: 'pi pi-exclamation-triangle', tone: 'amber' }
]

const actions = [
  { label: 'Mở lớp học phần', description: 'Tạo lớp theo học kỳ', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Ghi danh hàng loạt', description: 'Import sinh viên vào lớp', icon: 'pi pi-upload', tone: 'blue' },
  { label: 'Liên kết khóa học', description: 'Gắn course LMS', icon: 'pi pi-link', tone: 'violet' },
  { label: 'Xếp lịch học', description: 'Tạo ca học/phòng học', icon: 'pi pi-calendar-plus', tone: 'amber' }
]

const records = [
  { id: 1, title: 'INT1306-01 • Cấu trúc dữ liệu', subtitle: 'HK1 2025-2026 • Course: DSA Foundation', owner: 'ThS. Nguyễn Văn A', count: 128, status: 'Đang học', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'WEB2033-02 • Lập trình Web', subtitle: 'HK1 2025-2026 • Course: Web Development', owner: 'TS. Trần Minh', count: 96, status: 'Đang học', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'AI3011-01 • Nhập môn AI', subtitle: 'HK1 2025-2026 • Chưa liên kết course', owner: 'Chưa phân công', count: 72, status: 'Cần xử lý', statusTone: 'amber', updated: '2 ngày trước' },
  { id: 4, title: 'DBI202-03 • Cơ sở dữ liệu', subtitle: 'Học kỳ phụ 2025 • Course: Database Basic', owner: 'ThS. Lê Hoàng', count: 64, status: 'Sắp mở', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Lớp học phần / khóa học ghi danh"
    subtitle="Quản lý lớp học phần theo học kỳ, danh sách ghi danh và liên kết khóa học LMS."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm mã lớp học phần, môn học, giảng viên..."
    primary-action-label="Mở lớp học phần"
    primary-action-icon="pi pi-plus"
  />
</template>