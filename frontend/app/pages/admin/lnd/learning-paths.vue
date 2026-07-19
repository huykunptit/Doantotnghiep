<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Quản trị L&D' }, { label: 'Lộ trình học tập' }]

const kpis = [
  { label: 'Lộ trình', value: 32, sub: 'đang triển khai', icon: 'pi pi-sitemap', tone: 'green' },
  { label: 'Khóa học trong lộ trình', value: 214, sub: 'module đào tạo', icon: 'pi pi-book', tone: 'blue' },
  { label: 'Học viên tham gia', value: '5,280', sub: 'theo chương trình', icon: 'pi pi-users', tone: 'violet' },
  { label: 'Cần cập nhật', value: 7, sub: 'lộ trình thay đổi học phần', icon: 'pi pi-refresh', tone: 'amber' }
]

const actions = [
  { label: 'Tạo lộ trình', description: 'Thiết kế chuỗi khóa học bắt buộc/tự chọn', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán khóa học', description: 'Thêm học phần vào lộ trình', icon: 'pi pi-book', tone: 'blue' },
  { label: 'Phân bổ học viên', description: 'Gán lộ trình theo lớp/ngành', icon: 'pi pi-users', tone: 'violet' },
  { label: 'Báo cáo tiến độ', description: 'Theo dõi hoàn thành theo cohort', icon: 'pi pi-chart-bar', tone: 'amber' }
]

const records = [
  { id: 1, title: 'Lộ trình CNTT cơ sở D21', subtitle: '8 học phần • bắt buộc', owner: 'Khoa CNTT', count: 860, status: 'Đang triển khai', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Fullstack Web Developer', subtitle: '6 khóa học • project cuối khóa', owner: 'Trung tâm L&D', count: 520, status: 'Đang tuyển sinh', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'AI Foundation Track', subtitle: '5 module • chuẩn đầu ra AI', owner: 'Bộ môn AI', count: 340, status: 'Cần cập nhật', statusTone: 'amber', updated: '2 ngày trước' },
  { id: 4, title: 'Onboarding giảng viên mới', subtitle: 'Quy trình nghiệp vụ LMS', owner: 'Phòng Đào tạo', count: 45, status: 'Nội bộ', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Lộ trình học tập"
    subtitle="Thiết kế và vận hành các lộ trình đào tạo theo chương trình, lớp hoặc mục tiêu năng lực."
    kicker="Học vụ & Đào tạo"
    hero-title="Xây dựng lộ trình đào tạo có cấu trúc"
    hero-description="Tạo chuỗi khóa học, quy định điều kiện hoàn thành, phân bổ học viên và theo dõi tiến độ theo từng chương trình đào tạo."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm lộ trình, chương trình, đơn vị phụ trách..."
    primary-action-label="Tạo lộ trình"
    primary-action-icon="pi pi-sitemap"
  />
</template>