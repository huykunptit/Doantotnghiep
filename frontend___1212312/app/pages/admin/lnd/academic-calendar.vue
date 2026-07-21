<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Quản trị L&D' }, { label: 'Xem ca học & Lịch' }]

const kpis = [
  { label: 'Ca học hôm nay', value: 42, sub: 'online và offline', icon: 'pi pi-calendar', tone: 'green' },
  { label: 'Phòng/lớp online', value: 18, sub: 'đang hoạt động', icon: 'pi pi-video', tone: 'blue' },
  { label: 'Xung đột lịch', value: 4, sub: 'cần điều chỉnh', icon: 'pi pi-exclamation-triangle', tone: 'rose' },
  { label: 'Học kỳ hiện tại', value: 'HK1', sub: '2025-2026', icon: 'pi pi-clock', tone: 'violet' }
]

const actions = [
  { label: 'Tạo ca học', description: 'Thêm lịch học/lịch thi mới', icon: 'pi pi-calendar-plus', tone: 'green' },
  { label: 'Kiểm tra xung đột', description: 'Phòng học, giảng viên, lớp', icon: 'pi pi-search', tone: 'rose' },
  { label: 'Đồng bộ lịch', description: 'Cập nhật từ hệ thống học vụ', icon: 'pi pi-sync', tone: 'blue' },
  { label: 'Xuất thời khóa biểu', description: 'Tải lịch theo tuần/tháng', icon: 'pi pi-download', tone: 'amber' }
]

const records = [
  { id: 1, title: 'WEB2033-02 • Lập trình Web', subtitle: 'Thứ 3 • 18:00-20:30 • Online', owner: 'TS. Trần Minh', count: 96, status: 'Sắp diễn ra', statusTone: 'blue', updated: 'Hôm nay' },
  { id: 2, title: 'INT1306-01 • Cấu trúc dữ liệu', subtitle: 'Thứ 4 • 07:00-09:30 • A2-301', owner: 'ThS. Nguyễn Văn A', count: 128, status: 'Đã lên lịch', statusTone: 'green', updated: 'Hôm nay' },
  { id: 3, title: 'AI3011-01 • Nhập môn AI', subtitle: 'Thứ 5 • 09:45-12:00 • A3-204', owner: 'Chưa phân công', count: 72, status: 'Xung đột', statusTone: 'rose', updated: '1 ngày trước' },
  { id: 4, title: 'DBI202-03 • Cơ sở dữ liệu', subtitle: 'Thứ 6 • 13:00-15:30 • Lab 5', owner: 'ThS. Lê Hoàng', count: 64, status: 'Đã lên lịch', statusTone: 'amber', updated: '2 ngày trước' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Xem ca học & Lịch"
    subtitle="Theo dõi lịch học, ca học, phòng học và xung đột lịch đào tạo."
    kicker="Học vụ & Đào tạo"
    hero-title="Lịch vận hành đào tạo tập trung"
    hero-description="Quản lý thời khóa biểu, phòng học, ca học online/offline, giảng viên phụ trách và cảnh báo xung đột lịch."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm lớp, môn học, giảng viên, phòng..."
    primary-action-label="Tạo ca học"
    primary-action-icon="pi pi-calendar-plus"
  />
</template>