<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AdminSidebar from '~/components/dashboard/AdminSidebar.vue'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AppToast from '~/components/AppToast.vue'
import PointsQuestModal from '~/components/PointsQuestModal.vue'
import { useAuthStore } from '~/stores/auth'
import { getDashboardPath } from '~/composables/useAuthSession'
import { useAdminMenuConfig } from '~/composables/useAdminMenuConfig'

const auth = useAuthStore()

if (!auth.isReady) auth.initFromStorage()
if (auth.token && !auth.user) await auth.fetchMe()
if (!auth.isLoggedIn || !auth.user) await navigateTo('/login', { replace: true })
if (auth.user && !(auth.user.roles || []).includes('admin') && !(auth.user.roles || []).includes('instructor')) {
  await navigateTo(getDashboardPath(auth.user.role), { replace: true })
}

const user = computed(() => auth.user)
const route = useRoute()
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)

// Breadcrumbs from menu config
const { breadcrumbs } = useAdminMenuConfig()

const searchPlaceholder = computed(() =>
  typeof route.meta.adminSearchPlaceholder === 'string'
    ? route.meta.adminSearchPlaceholder
    : 'Tìm user, khóa học, giao dịch...',
)

watch(() => route.fullPath, () => { sidebarOpen.value = false })
</script>

<template>
  <div class="admin-shell" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <!-- Sidebar -->
    <AdminSidebar
      :user-name="user?.name || 'Admin User'"
      :user-role="user?.role === 'admin' ? 'Quản trị viên' : 'Giảng viên'"
      class="admin-sidebar"
      :class="{ 'is-open': sidebarOpen }"
      @collapsed-change="sidebarCollapsed = $event"
    />

    <!-- Mobile overlay -->
    <Transition name="fade">
      <button
        v-if="sidebarOpen"
        type="button"
        class="admin-overlay"
        aria-label="Đóng sidebar"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Main -->
    <div class="admin-main">
      <AdminTopbar
        :search-placeholder="searchPlaceholder"
        :user-name="user?.name || 'Admin User'"
        :user-avatar="user?.avatar"
        :user-role="user?.role === 'admin' ? 'Quản trị viên' : 'Giảng viên'"
        floating
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />

      <!-- Breadcrumb -->
      <nav v-if="breadcrumbs.length > 0" class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol class="admin-breadcrumb-list">
          <li class="admin-breadcrumb-home">
            <NuxtLink to="/admin" class="admin-breadcrumb-link">
              <i class="pi pi-home" style="font-size:0.8125rem" />
            </NuxtLink>
          </li>
          <li v-for="(crumb, idx) in breadcrumbs" :key="idx" class="admin-breadcrumb-item">
            <i class="pi pi-chevron-right admin-breadcrumb-sep" />
            <NuxtLink v-if="crumb.to" :to="crumb.to" class="admin-breadcrumb-link">
              {{ crumb.label }}
            </NuxtLink>
            <span v-else class="admin-breadcrumb-current">{{ crumb.label }}</span>
          </li>
        </ol>
      </nav>

      <div class="admin-content">
        <slot />
      </div>
    </div>

    <AppToast />
    <PointsQuestModal />
  </div>
</template>

<style scoped>
/* ── Shell ── */
.admin-shell {
  display: flex;
  min-height: 100vh;
  background: var(--surface);
}

/* ── Sidebar slot ── */
.admin-sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 40;
  transform: translateX(0);
  transition: transform 220ms cubic-bezier(0.4, 0, 0.2, 1), box-shadow 220ms ease;
}

/* ── Main area ── */
.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  margin-left: calc(var(--sidebar-width) + 16px);
  min-width: 0;
  background: transparent;
  transition: margin-left 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-shell.sidebar-collapsed .admin-main {
  margin-left: calc(var(--sidebar-width-collapsed) + 16px);
}

.admin-content {
  flex: 1;
  padding: 8px 16px 32px;
  min-width: 0;
  overflow-x: auto;
}

/* ── Breadcrumb ── */
.admin-breadcrumb {
  padding: 4px 20px 0;
  flex-shrink: 0;
}

.admin-breadcrumb-list {
  display: flex;
  align-items: center;
  gap: 4px;
  list-style: none;
  margin: 0;
  padding: 0;
  min-height: 28px;
}

.admin-breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.admin-breadcrumb-sep {
  font-size: 0.625rem;
  color: var(--color-text-muted);
  opacity: 0.5;
}

.admin-breadcrumb-link {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  text-decoration: none;
  font-weight: 500;
  transition: color 150ms;
}

.admin-breadcrumb-link:hover {
  color: var(--color-primary);
}

.admin-breadcrumb-current {
  font-size: 0.8125rem;
  color: var(--color-text);
  font-weight: 600;
}

/* ── Mobile overlay ── */
.admin-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 30;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(2px);
  border: none;
  cursor: pointer;
  padding: 0;
}

/* ── Responsive ── */
@media (max-width: 1080px) {
  .admin-sidebar {
    transform: translateX(calc(-100% - 16px));
    box-shadow: none;
  }
  .admin-sidebar.is-open {
    transform: translateX(0);
    box-shadow: var(--shadow-lg);
  }
  .admin-main,
  .admin-shell.sidebar-collapsed .admin-main {
    margin-left: 0;
  }
  .admin-overlay { display: block; }
}
@media (max-width: 640px) {
  .admin-content { padding: 6px 8px 24px; }
  .admin-breadcrumb { padding: 2px 12px 0; }
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 220ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

</style>
