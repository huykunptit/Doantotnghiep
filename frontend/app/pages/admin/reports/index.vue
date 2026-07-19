<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Báo cáo' }, { label: 'Tổng quan' }]

const kpis = [
  { label: 'Người dùng mới', value: 342, sub: '30 ngày gần nhất', icon: 'pi pi-user-plus', tone: 'green' },
  { label: 'Hoàn thành khóa học', value: '74.8%', sub: '+5.2% so kỳ trước', icon: 'pi pi-chart-line', tone: 'blue' },
  { label: 'Bài thi đã nộp', value: 1286, sub: 'cần chấm: 36', icon: 'pi pi-file-check', tone: 'violet' },
  { label: 'Cảnh báo mở', value: 19, sub: 'ưu tiên xử lý', icon: 'pi pi-exclamation-triangle', tone: 'rose' }
]

const actions = [
  { label: 'Xuất Excel', icon: 'pi pi-file-excel', tone: 'green' },
  { label: 'Xuất PDF', icon: 'pi pi-file-pdf', tone: 'rose' },
  { label: 'Lập lịch gửi báo cáo', icon: 'pi pi-calendar-plus', tone: 'blue' },
  { label: 'Bộ lọc nâng cao', icon: 'pi pi-filter', tone: 'slate' }
]

const records = [
  { id: 1, title: 'Báo cáo khóa học', subtitle: 'Số lượng học viên, tiến độ, tỷ lệ hoàn thành', owner: 'Academic', count: 128, status: 'Sẵn sàng', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Báo cáo tiến độ học tập', subtitle: 'Theo khoa, lớp, khóa học và từng học viên', owner: 'Training', count: 1020, status: 'Sẵn sàng', statusTone: 'blue', updated: 'Hôm nay' },
  { id: 3, title: 'Nhật ký hoạt động', subtitle: 'Đăng nhập, thao tác CRUD, thay đổi quyền', owner: 'System', count: 8420, status: 'Realtime', statusTone: 'violet', updated: 'Vừa cập nhật' },
  { id: 4, title: 'Cảnh báo học tập', subtitle: 'Học chậm tiến độ, lâu không đăng nhập, rủi ro thi', owner: 'System', count: 19, status: 'Cần xử lý', statusTone: 'rose', updated: '10 phút trước' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Tổng quan báo cáo"
    subtitle="Theo dõi số liệu vận hành LMS, học tập, khóa học, khảo thí và cảnh báo."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm báo cáo, chỉ số, người phụ trách..."
    primary-action-label="Tạo báo cáo"
    primary-action-icon="pi pi-chart-bar"
  />
</template>