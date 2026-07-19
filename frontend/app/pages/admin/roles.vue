<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Người dùng & Tổ chức' }, { label: 'Phân quyền Giảng viên' }]

const kpis = [
  { label: 'Vai trò hệ thống', value: 7, sub: 'nhóm quyền đang dùng', icon: 'pi pi-shield', tone: 'green' },
  { label: 'Giảng viên được phân quyền', value: 180, sub: 'tài khoản đang hoạt động', icon: 'pi pi-user', tone: 'blue' },
  { label: 'Quyền nội dung', value: 24, sub: 'module/course permissions', icon: 'pi pi-lock', tone: 'amber' },
  { label: 'Cần rà soát', value: 5, sub: 'phân quyền bất thường', icon: 'pi pi-exclamation-triangle', tone: 'rose' }
]

const actions = [
  { label: 'Tạo vai trò', description: 'Tạo role và bộ quyền mới', icon: 'pi pi-plus', tone: 'green' },
  { label: 'Gán giảng viên', description: 'Phân công quyền theo lớp/khóa học', icon: 'pi pi-user-plus', tone: 'blue' },
  { label: 'Sao chép quyền', description: 'Nhân bản cấu hình role', icon: 'pi pi-copy', tone: 'violet' },
  { label: 'Audit quyền', description: 'Kiểm tra lịch sử phân quyền', icon: 'pi pi-history', tone: 'amber' }
]

const records = [
  { id: 1, title: 'Giảng viên phụ trách khóa học', subtitle: 'Tạo nội dung, chấm bài, xem tiến độ', owner: 'Academic Admin', count: 126, status: 'Đang áp dụng', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'Giảng viên trợ giảng', subtitle: 'Hỗ trợ lớp, trả lời Q&A, xem bài nộp', owner: 'Course Owner', count: 48, status: 'Đang áp dụng', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'Người duyệt nội dung', subtitle: 'Kiểm duyệt khóa học trước xuất bản', owner: 'Admin', count: 12, status: 'Cần rà soát', statusTone: 'amber', updated: '2 ngày trước' },
  { id: 4, title: 'Giám sát kỳ thi', subtitle: 'Xem camera, log, cảnh báo thi', owner: 'Exam Manager', count: 18, status: 'Đang áp dụng', statusTone: 'violet', updated: 'Tuần này' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Phân quyền giảng viên"
    subtitle="Thiết lập vai trò, quyền truy cập và phạm vi phụ trách cho giảng viên."
    kicker="Người dùng & Tổ chức"
    hero-title="Quản lý quyền truy cập đào tạo"
    hero-description="Kiểm soát quyền tạo nội dung, duyệt khóa học, quản lý lớp, chấm điểm, giám sát thi và truy cập báo cáo theo từng nhóm người dùng."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm vai trò, quyền, giảng viên..."
    primary-action-label="Tạo vai trò"
    primary-action-icon="pi pi-shield"
  />
</template>