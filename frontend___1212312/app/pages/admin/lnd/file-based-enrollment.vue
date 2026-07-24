<script setup lang="ts">
import AdminFeatureScaffold from '~/components/admin/AdminFeatureScaffold.vue'

definePageMeta({ layout: 'admin' })

const breadcrumbs = [{ label: 'Quản trị L&D' }, { label: 'Ghi danh theo file' }]

const kpis = [
  { label: 'File đã import', value: 48, sub: 'trong học kỳ hiện tại', icon: 'pi pi-file-excel', tone: 'green' },
  { label: 'Ghi danh thành công', value: '12,640', sub: 'bản ghi hợp lệ', icon: 'pi pi-check-circle', tone: 'blue' },
  { label: 'Bản ghi lỗi', value: 132, sub: 'cần rà soát', icon: 'pi pi-exclamation-circle', tone: 'rose' },
  { label: 'Đang xử lý', value: 3, sub: 'job import nền', icon: 'pi pi-spin pi-spinner', tone: 'amber' }
]

const actions = [
  { label: 'Tải file ghi danh', description: 'Upload CSV/XLSX danh sách học viên', icon: 'pi pi-upload', tone: 'green' },
  { label: 'Tải template', description: 'Mẫu file chuẩn hệ thống', icon: 'pi pi-download', tone: 'blue' },
  { label: 'Kiểm tra lỗi', description: 'Xem các dòng không hợp lệ', icon: 'pi pi-search', tone: 'rose' },
  { label: 'Lịch sử import', description: 'Theo dõi trạng thái xử lý', icon: 'pi pi-history', tone: 'violet' }
]

const records = [
  { id: 1, title: 'enrollment_d21_cntt_hk1.xlsx', subtitle: 'Khóa D21 • CNTT • Học kỳ 1', owner: 'Phòng Đào tạo', count: 1240, status: 'Hoàn tất', statusTone: 'green', updated: 'Hôm nay' },
  { id: 2, title: 'lop_web2033_batch_02.csv', subtitle: 'Lớp WEB2033-02', owner: 'Admin LMS', count: 96, status: 'Hoàn tất', statusTone: 'blue', updated: '1 ngày trước' },
  { id: 3, title: 'import_ai3011_pending.xlsx', subtitle: 'AI3011 • Có lỗi mã sinh viên', owner: 'Học vụ Khoa CNTT', count: 72, status: 'Có lỗi', statusTone: 'rose', updated: '2 ngày trước' },
  { id: 4, title: 'enrollment_makeup_classes.xlsx', subtitle: 'Lớp học lại / cải thiện', owner: 'Phòng Đào tạo', count: 318, status: 'Đang xử lý', statusTone: 'amber', updated: 'Vừa xong' }
]
</script>

<template>
  <AdminFeatureScaffold
    title="Ghi danh theo file"
    subtitle="Import danh sách học viên vào lớp học phần hoặc lộ trình học tập bằng file."
    kicker="Học vụ & Đào tạo"
    hero-title="Nhập dữ liệu ghi danh hàng loạt"
    hero-description="Hỗ trợ upload file, validate dữ liệu, theo dõi tiến trình xử lý và xuất báo cáo lỗi cho các đợt ghi danh quy mô lớn."
    :breadcrumbs="breadcrumbs"
    :kpis="kpis"
    :actions="actions"
    :records="records"
    search-placeholder="Tìm file, lớp, người import..."
    primary-action-label="Upload file"
    primary-action-icon="pi pi-upload"
  />
</template>