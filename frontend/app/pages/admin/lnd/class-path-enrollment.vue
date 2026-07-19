<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Quản trị L&D' }, { label: 'Phân công lớp hành chính' }]

const kpis = [
  { label: 'Lớp đã gán', value: 74, sub: 'có lộ trình học tập', icon: 'pi pi-users', tone: 'green' },
  { label: 'Học viên bao phủ', value: '4,860', sub: 'được gán lộ trình', icon: 'pi pi-check-circle', tone: 'blue' },
  { label: 'Lớp chưa gán', value: 18, sub: 'cần xử lý', icon: 'pi pi-exclamation-triangle', tone: 'rose' },
  { label: 'Tự động đồng bộ', value: 9, sub: 'rule đang bật', icon: 'pi pi-sync', tone: 'violet' }
]

const actions = [
  { label: 'Gán lộ trình cho lớp', description: 'Chọn lớp hành chính và learning path', icon: 'pi pi-link', tone: 'green' },
  { label: 'Thiết lập rule', description: 'Tự động gán theo khóa/ngành', icon: 'pi pi-cog', tone: 'blue' },
  { label: 'Đồng bộ lại', description: 'Cập nhật danh sách học viên', icon: 'pi pi-sync', tone: 'amber' },
  { label: 'Xuất danh sách', description: 'Tải mapping lớp-lộ trình', icon: 'pi pi-download', tone: 'violet' }
]

const records = [
  { id: 1, title: 'D21CQCN01-B → CNTT cơ sở D21', subtitle: 'Gán tự động theo ngành CNTT', owner: 'Phòng Đào tạo', count: 68, status: 'Đã đồng bộ', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'D22CQAT01-N → An toàn thông tin', subtitle: 'Lộ trình học phần bắt buộc', owner: 'Khoa ATTT', count: 72, status: 'Đã đồng bộ', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'D23CQCN05-B → Chưa gán', subtitle: 'Lớp mới nhập từ SIS', owner: 'Chưa có', count: 64, status: 'Cần gán', statusTone: 'rose', updated: '2 ngày trước' },
  { id: 4, title: 'D20CQVT02-B → Lộ trình tốt nghiệp', subtitle: 'Theo dõi điều kiện hoàn thành', owner: 'Khoa DTVT', count: 58, status: 'Đang rà soát', statusTone: 'amber', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Phân công lớp hành chính"
    subtitle="Gán lộ trình học tập cho lớp hành chính, khóa tuyển sinh hoặc chương trình đào tạo."
    kicker="Học vụ & Đào tạo"
    hero-title="Mapping lớp hành chính với lộ trình đào tạo"
    hero-description="Quản lý việc phân bổ learning path cho lớp hành chính, đồng bộ học viên, và kiểm tra các lớp chưa có chương trình học phù hợp."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm lớp, lộ trình, khóa, ngành..."
    primary-action-label="Gán lộ trình"
    primary-action-icon="pi pi-link"
  />
</template>