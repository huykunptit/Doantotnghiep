<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Báo cáo' }, { label: 'Nhật ký hoạt động' }]

const kpis = [
  { label: 'Sự kiện hôm nay', value: 8420, sub: 'login, học tập, CRUD', icon: 'pi pi-history', tone: 'blue' },
  { label: 'Đăng nhập thành công', value: 2380, sub: '24 giờ gần nhất', icon: 'pi pi-sign-in', tone: 'green' },
  { label: 'Lỗi thao tác', value: 37, sub: 'cần rà soát', icon: 'pi pi-times-circle', tone: 'rose' },
  { label: 'Thay đổi quyền', value: 11, sub: 'được audit', icon: 'pi pi-shield', tone: 'violet' }
]

const actions = [
  { label: 'Lọc theo user', icon: 'pi pi-user', tone: 'blue' },
  { label: 'Lọc theo module', icon: 'pi pi-filter', tone: 'green' },
  { label: 'Xuất log', icon: 'pi pi-download', tone: 'slate' },
  { label: 'Cảnh báo bảo mật', icon: 'pi pi-shield', tone: 'rose' }
]

const records = [
  { id: 1, title: 'Nguyễn Văn An hoàn thành bài học', subtitle: 'Khóa Kỹ năng số cơ bản', owner: 'student', count: 1, status: 'Learning', statusTone: 'green', updated: '5 phút trước' },
  { id: 2, title: 'Trần Minh tạo ngân hàng câu hỏi', subtitle: 'Module khảo thí', owner: 'instructor', count: 30, status: 'Create', statusTone: 'blue', updated: '18 phút trước' },
  { id: 3, title: 'Admin cập nhật quyền giảng viên', subtitle: 'Role: course_reviewer', owner: 'admin', count: 1, status: 'Permission', statusTone: 'violet', updated: '42 phút trước' },
  { id: 4, title: 'Đăng nhập thất bại nhiều lần', subtitle: 'IP 192.168.1.12', owner: 'system', count: 3, status: 'Security', statusTone: 'rose', updated: '1 giờ trước' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Nhật ký hoạt động"
    subtitle="Audit trail cho đăng nhập, học tập, CRUD dữ liệu, phân quyền và cảnh báo bảo mật."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm người dùng, module, hành động, IP..."
    primary-action-label="Tạo bộ lọc log"
    primary-action-icon="pi pi-history"
  />
</template>