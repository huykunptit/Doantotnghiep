<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Báo cáo' }, { label: 'Khóa học' }]

const kpis = [
  { label: 'Tổng khóa học', value: 128, sub: 'đang quản lý', icon: 'pi pi-book', tone: 'blue' },
  { label: 'Đã xuất bản', value: 96, sub: '75% tổng khóa', icon: 'pi pi-check-circle', tone: 'green' },
  { label: 'Chờ duyệt', value: 12, sub: 'cần xử lý', icon: 'pi pi-clock', tone: 'amber' },
  { label: 'Tỷ lệ hoàn thành TB', value: '74.8%', sub: 'toàn hệ thống', icon: 'pi pi-chart-line', tone: 'violet' }
]

const actions = [
  { label: 'Lọc theo khoa', icon: 'pi pi-filter', tone: 'blue' },
  { label: 'Xuất Excel', icon: 'pi pi-file-excel', tone: 'green' },
  { label: 'Top khóa học', icon: 'pi pi-star', tone: 'amber' },
  { label: 'So sánh kỳ', icon: 'pi pi-chart-bar', tone: 'violet' }
]

const records = [
  { id: 1, title: 'Lập trình Web nâng cao', subtitle: '1,240 lượt học • 82% hoàn thành', owner: 'Trần Minh', count: 1240, status: 'Tăng trưởng', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Cơ sở dữ liệu ứng dụng', subtitle: '980 lượt học • 76% hoàn thành', owner: 'Lê Thu', count: 980, status: 'Ổn định', statusTone: 'blue', updated: 'Hôm nay' },
  { id: 3, title: 'An toàn thông tin cơ bản', subtitle: '640 lượt học • 69% hoàn thành', owner: 'Nguyễn Lan', count: 640, status: 'Cần theo dõi', statusTone: 'amber', updated: 'Hôm qua' },
  { id: 4, title: 'Kỹ năng số cơ bản', subtitle: '2,100 lượt học • 88% hoàn thành', owner: 'Academic', count: 2100, status: 'Nổi bật', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Báo cáo khóa học"
    subtitle="Phân tích hiệu quả khóa học theo lượt học, ghi danh, hoàn thành và đánh giá."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm khóa học, giảng viên, chỉ số..."
    primary-action-label="Tạo báo cáo khóa học"
    primary-action-icon="pi pi-book"
  />
</template>