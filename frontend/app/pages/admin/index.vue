<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tìm người dùng, khóa học, giao dịch...',
})

const user = useAuthUserCookie()
if (!user.value) await navigateTo('/login', { replace: true })
if (user.value && normalizeRole(user.value.role) !== 'admin') {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
}

const overviewCards = [
  { title: 'Người dùng mới', value: '42', note: '+12 trong 7 ngày', tone: 'green' },
  { title: 'Khóa học chờ duyệt', value: '08', note: 'Cần rà soát nội dung', tone: 'amber' },
  { title: 'Đơn hàng mới', value: '19', note: '6 giao dịch cần đối soát', tone: 'neutral' },
]

const adminSections = [
  { title: 'Quản lý người dùng', description: 'Quản lý tài khoản, vai trò, trạng thái và hồ sơ người dùng.', to: '/admin/users', status: 'Sẵn sàng' },
  { title: 'Quản lý khóa học', description: 'Duyệt khóa học, kiểm tra hình ảnh, nội dung và giảng viên phụ trách.', to: '/admin/courses', status: 'Đang vận hành' },
  { title: 'Quản lý thi', description: 'Theo dõi ngân hàng câu hỏi, quiz và đề thi theo từng khóa học.', to: '/admin/question-bank', status: 'Nâng cấp' },
  { title: 'Quản trị hệ thống', description: 'Theo dõi đơn hàng, cài đặt và các cấu hình vận hành hệ thống.', to: '/admin/settings', status: 'Liên tục' },
]
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Bảng điều khiển']"
    description="Bảng điều khiển quản trị tập trung, sử dụng sidebar cố định và chuẩn giao diện thống nhất cho toàn bộ admin pages."
    title="Tổng quan hệ thống"
  >
    <section class="crud-overview-grid">
      <article v-for="card in overviewCards" :key="card.title" class="dashboard-card mini-card" :class="`tone-${card.tone}`">
        <p class="mini-title">{{ card.title }}</p>
        <div class="mini-head">
          <strong>{{ card.value }}</strong>
          <span>{{ card.note }}</span>
        </div>
      </article>
    </section>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Điều hướng quản trị</p>
          <h3>Các khu vực quản trị chính</h3>
        </div>
      </div>

      <div class="week-one-list">
        <NuxtLink v-for="item in adminSections" :key="item.title" :to="item.to" class="week-one-item">
          <div>
            <strong>{{ item.title }}</strong>
            <p>{{ item.description }}</p>
          </div>
          <span class="crud-badge" :class="item.status === 'Sẵn sàng' ? 'role-instructor' : 'role-student'">{{ item.status }}</span>
        </NuxtLink>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>
