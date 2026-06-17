<script setup lang="ts">
import { computed, ref } from 'vue'
import { User, Lock, ReceiptText, LayoutDashboard } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const activeTab = ref<'info' | 'password'>('info')

const tabs = [
  { id: 'info' as const, label: 'Thông tin cá nhân', icon: User },
  { id: 'password' as const, label: 'Bảo mật', icon: Lock },
]

const roleLabel = computed(() => {
  const role = auth.user?.role ?? auth.user?.roles?.[0] ?? 'student'
  return { admin: 'Quản trị viên', instructor: 'Giảng viên', student: 'Học viên' }[role] ?? 'Học viên'
})

const userInitials = computed(() => {
  const name = auth.user?.name || 'U'
  return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
})
</script>

<template>
  <div class="pf-shell">
    <div class="pf-wrap">
      <!-- Hero -->
      <header class="pf-hero">
        <div class="pf-hero-identity">
          <div class="pf-avatar">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user?.name">
            <span v-else>{{ userInitials }}</span>
          </div>
          <div class="pf-hero-info">
            <h1 class="pf-hero-name">{{ auth.user?.name }}</h1>
            <p class="pf-hero-email">{{ auth.user?.email }}</p>
            <span class="pf-role-chip">{{ roleLabel }}</span>
          </div>
        </div>
        <div class="pf-hero-stats">
          <div class="pf-stat">
            <strong>12</strong>
            <span>Khóa học</span>
          </div>
          <div class="pf-stat-divider" />
          <div class="pf-stat">
            <strong>04</strong>
            <span>Chứng chỉ</span>
          </div>
        </div>
      </header>

      <!-- Layout -->
      <div class="pf-layout">
        <!-- Sidebar -->
        <aside class="pf-sidebar">
          <nav class="pf-nav">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="pf-nav-item"
              :class="{ 'is-active': activeTab === tab.id }"
              @click="activeTab = tab.id"
            >
              <component :is="tab.icon" :size="16" :stroke-width="1.75" />
              <span>{{ tab.label }}</span>
            </button>
            <NuxtLink to="/orders" class="pf-nav-item">
              <ReceiptText :size="16" :stroke-width="1.75" />
              <span>Đơn hàng</span>
            </NuxtLink>
            <NuxtLink to="/student" class="pf-nav-item">
              <LayoutDashboard :size="16" :stroke-width="1.75" />
              <span>Bảng điều khiển</span>
            </NuxtLink>
          </nav>
        </aside>

        <!-- Main -->
        <main class="pf-main">
          <div class="pf-main-head">
            <p class="pf-eyebrow">{{ activeTab === 'info' ? 'Thông tin người dùng' : 'Bảo mật tài khoản' }}</p>
            <h2 class="pf-main-title">{{ activeTab === 'info' ? 'Thông tin cá nhân' : 'Đổi mật khẩu' }}</h2>
            <p class="pf-main-desc">
              {{ activeTab === 'info'
                ? 'Cập nhật họ tên, ảnh đại diện và các thông tin hiển thị trên hệ thống.'
                : 'Đặt mật khẩu mạnh hơn để bảo vệ tài khoản học tập của bạn.' }}
            </p>
          </div>
          <div class="pf-form-area">
            <AuthProfileForm v-if="activeTab === 'info'" />
            <AuthChangePasswordForm v-else />
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pf-shell {
  min-height: 100vh;
  background: var(--bg);
  padding: 32px 16px;
}

.pf-wrap {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ── Hero ── */
.pf-hero {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 20px;
  padding: 28px 32px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
}

.pf-hero-identity { display: flex; align-items: center; gap: 16px; }

.pf-avatar {
  width: 64px; height: 64px; border-radius: 50%;
  background: var(--green-soft); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700;
  overflow: hidden; flex-shrink: 0; border: 3px solid var(--green-soft);
}
.pf-avatar img { width: 100%; height: 100%; object-fit: cover; }

.pf-hero-name {
  margin: 0 0 3px;
  font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700;
  color: var(--text);
}
.pf-hero-email { margin: 0 0 8px; font-size: 0.875rem; color: var(--muted); }

.pf-role-chip {
  display: inline-flex; padding: 3px 10px; border-radius: 999px;
  background: var(--green-soft); color: var(--green-deep);
  font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
}

.pf-hero-stats { display: flex; align-items: center; gap: 20px; }
.pf-stat { text-align: center; }
.pf-stat strong { display: block; font-size: 1.5rem; font-weight: 800; color: var(--text); }
.pf-stat span { font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
.pf-stat-divider { width: 1px; height: 36px; background: var(--line); }

/* ── Layout ── */
.pf-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 20px;
  align-items: start;
}

/* ── Sidebar ── */
.pf-sidebar {
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
  overflow: hidden;
}

.pf-nav { display: flex; flex-direction: column; padding: 8px; gap: 2px; }

.pf-nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px;
  background: transparent; border: none;
  font: inherit; font-size: 0.875rem; font-weight: 500;
  color: var(--muted); text-decoration: none;
  cursor: pointer; transition: background 150ms, color 150ms; text-align: left;
}
.pf-nav-item:hover { background: var(--surface); color: var(--text); }
.pf-nav-item.is-active { background: var(--green-soft); color: var(--green-deep); font-weight: 600; }

/* ── Main ── */
.pf-main {
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
  padding: 28px 32px;
}

.pf-eyebrow {
  margin: 0 0 4px;
  font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.12em; color: var(--green);
}
.pf-main-title {
  margin: 0 0 6px;
  font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text);
}
.pf-main-desc { margin: 0 0 24px; font-size: 0.875rem; line-height: 1.6; color: var(--muted); }

.pf-form-area :deep(form) { display: grid; gap: 16px; }
.pf-form-area :deep(.feedback) { margin-top: 0; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .pf-shell { padding: 16px 12px; }
  .pf-layout { grid-template-columns: 1fr; }
  .pf-sidebar { order: 2; }
  .pf-nav { flex-direction: row; flex-wrap: wrap; }
  .pf-hero { padding: 20px; }
  .pf-main { padding: 20px; }
}

[data-theme="dark"] .pf-hero,
[data-theme="dark"] .pf-sidebar,
[data-theme="dark"] .pf-main {
  background: rgba(255,255,255,0.04);
  border-color: rgba(255,255,255,0.08);
}
</style>
