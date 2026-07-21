<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Báo cáo' }, { label: 'Tiến độ học tập' }]

const kpis = [
  { label: 'Học viên đang học', value: 1020, sub: 'có hoạt động trong 30 ngày', icon: 'pi pi-users', tone: 'green' },
  { label: 'Hoàn thành đúng hạn', value: '68%', sub: '+4% so với kỳ trước', icon: 'pi pi-check-circle', tone: 'blue' },
  { label: 'Chậm tiến độ', value: 146, sub: 'cần nhắc nhở', icon: 'pi pi-exclamation-triangle', tone: 'amber' },
  { label: 'Không hoạt động', value: 52, sub: 'trên 14 ngày', icon: 'pi pi-user-minus', tone: 'rose' }
]

const actions = [
  { label: 'Gửi nhắc học', icon: 'pi pi-send', tone: 'green' },
  { label: 'Lọc theo lớp', icon: 'pi pi-filter', tone: 'blue' },
  { label: 'Xuất tiến độ', icon: 'pi pi-download', tone: 'slate' },
  { label: 'Cấu hình cảnh báo', icon: 'pi pi-cog', tone: 'violet' }
]

const records = [
  { id: 1, title: 'D21CQCN01-B', subtitle: '82% hoàn thành trung bình', owner: 'Khoa CNTT', count: 126, status: 'Tốt', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'D22CQAT01-N', subtitle: '74% hoàn thành trung bình', owner: 'Khoa ATTT', count: 98, status: 'Ổn định', statusTone: 'blue', updated: 'Hôm nay' },
  { id: 3, title: 'D20CQVT02-B', subtitle: '56% hoàn thành trung bình', owner: 'Khoa VT', count: 84, status: 'Chậm tiến độ', statusTone: 'amber', updated: 'Hôm qua' },
  { id: 4, title: 'Nhóm học viên không hoạt động', subtitle: 'Không đăng nhập trên 14 ngày', owner: 'System', count: 52, status: 'Cảnh báo', statusTone: 'rose', updated: '10 phút trước' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Báo cáo tiến độ học tập"
    subtitle="Theo dõi tiến độ học viên theo lớp, khoa, khóa học và phát hiện rủi ro học tập."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm lớp, khoa, nhóm học viên..."
    primary-action-label="Tạo cảnh báo tiến độ"
    primary-action-icon="pi pi-exclamation-triangle"
  />
</template>