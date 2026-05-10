<template>
  <div class="account-shell">
    <section class="account-wrap">
      <header class="account-hero">
        <div>
          <p class="account-eyebrow">Tài khoản</p>
          <h1>Hồ sơ cá nhân</h1>
          <p class="account-lead">
            Quản lý thông tin cơ bản, cập nhật hồ sơ và tăng cường bảo mật tài khoản trong một không gian gọn gàng.
          </p>
        </div>
        <div class="account-identity">
          <div class="account-avatar">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user?.name">
            <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <div>
            <strong>{{ auth.user?.name }}</strong>
            <p>{{ auth.user?.email }}</p>
            <span class="account-chip">{{ roleLabel }}</span>
          </div>
        </div>
      </header>

      <section class="account-layout">
        <aside class="account-sidebar">
          <div class="account-card">
            <p class="account-section-label">Tổng quan</p>
            <div class="account-stats">
              <article>
                <strong>12</strong>
                <span>Khóa học</span>
              </article>
              <article>
                <strong>04</strong>
                <span>Chứng chỉ</span>
              </article>
            </div>
          </div>

          <nav class="account-card account-nav">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              :class="['account-nav-item', { 'is-active': activeTab === tab.id }]"
              @click="activeTab = tab.id"
            >
              <span class="material-symbols-outlined">{{ tab.iconStr }}</span>
              <span>{{ tab.label }}</span>
            </button>
            <NuxtLink to="/orders" class="account-nav-item">
              <span class="material-symbols-outlined">receipt_long</span>
              <span>Đơn hàng</span>
            </NuxtLink>
            <NuxtLink to="/student" class="account-nav-item">
              <span class="material-symbols-outlined">dashboard</span>
              <span>Bảng điều khiển</span>
            </NuxtLink>
          </nav>
        </aside>

        <main class="account-main account-card">
          <div class="account-main-head">
            <div>
              <p class="account-section-label">{{ activeTab === 'info' ? 'Thông tin người dùng' : 'Bảo mật tài khoản' }}</p>
              <h2>{{ activeTab === 'info' ? 'Thông tin cá nhân' : 'Đổi mật khẩu' }}</h2>
              <p>
                {{ activeTab === 'info'
                  ? 'Cập nhật họ tên, ảnh đại diện và các thông tin hiển thị trên hệ thống.'
                  : 'Đặt mật khẩu mạnh hơn để bảo vệ tài khoản học tập của bạn.' }}
              </p>
            </div>
          </div>

          <div class="account-form-wrap">
            <AuthProfileForm v-if="activeTab === 'info'" />
            <AuthChangePasswordForm v-else />
          </div>
        </main>
      </section>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const activeTab = ref<'info' | 'password'>('info')
const tabs = [
  { id: 'info', label: 'Thông tin cá nhân', iconStr: 'person' },
  { id: 'password', label: 'Bảo mật', iconStr: 'lock' },
] as const

const roleLabel = computed(() => {
  const role = auth.user?.role ?? auth.user?.roles?.[0] ?? 'student'
  return { admin: 'Quản trị viên', instructor: 'Giảng viên', student: 'Học viên' }[role] ?? 'Học viên'
})
</script>

<style scoped>
.account-form-wrap :deep(form) { display: grid; gap: 18px; }
.account-form-wrap :deep(.feedback) { margin-top: 0; }
</style>
