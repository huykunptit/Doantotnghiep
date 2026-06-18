<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'
import AdminSidebar from '~/components/dashboard/AdminSidebar.vue'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AppToast from '~/components/AppToast.vue'
import { useAuthStore } from '~/stores/auth'
import { getDashboardPath } from '~/composables/useAuthSession'

const auth = useAuthStore()

if (!auth.isReady) auth.initFromStorage()
if (auth.token && !auth.user) await auth.fetchMe()
if (!auth.isLoggedIn || !auth.user) await navigateTo('/login', { replace: true })
if (auth.user && !(auth.user.roles || []).includes('admin')) {
  await navigateTo(getDashboardPath(auth.user.role), { replace: true })
}

const user = computed(() => auth.user)
const route = useRoute()
const sidebarOpen = ref(false)

const searchPlaceholder = computed(() => {
  return typeof route.meta.adminSearchPlaceholder === 'string'
    ? route.meta.adminSearchPlaceholder
    : 'Tìm user, khóa học, giao dịch...'
})

watch(() => route.fullPath, () => { sidebarOpen.value = false })
</script>

<template>
  <div class="admin-shell">
    <!-- Sidebar -->
    <AdminSidebar
      :user-name="user?.name || 'Admin User'"
      user-role="Admin"
      class="admin-sidebar"
      :class="{ 'is-open': sidebarOpen }"
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
        user-role="Admin"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />

      <div class="admin-content">
        <slot />
      </div>

      <AdminFooter />
    </div>

    <!-- Global toast (outside main so z-index is not clipped) -->
    <AppToast />
  </div>
</template>

<style scoped>
.admin-shell {
  display: flex;
  min-height: 100vh;
  background: var(--bg);
}

.admin-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 40;
  width: 240px;
  transform: translateX(0);
  transition: transform 220ms cubic-bezier(0.4, 0, 0.2, 1), box-shadow 220ms ease;
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  margin-left: 240px;
  min-width: 0;
  transition: margin-left 220ms cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-content {
  flex: 1;
  padding: 28px;
  min-width: 0;
}

.admin-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 30;
  background: rgba(14, 26, 22, 0.5);
  backdrop-filter: blur(2px);
  border: none;
  cursor: pointer;
  padding: 0;
}

@media (max-width: 1080px) {
  .admin-sidebar { transform: translateX(-100%); box-shadow: none; }
  .admin-sidebar.is-open { transform: translateX(0); box-shadow: 8px 0 40px rgba(14,26,22,0.2); }
  .admin-main { margin-left: 0; }
  .admin-overlay { display: block; }
}

@media (max-width: 640px) {
  .admin-content { padding: 16px; }
}

.fade-enter-active, .fade-leave-active { transition: opacity 220ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
