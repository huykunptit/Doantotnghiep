<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'
import StudentSidebar from '~/components/dashboard/StudentSidebar.vue'
import AppToast from '~/components/AppToast.vue'
import PointsQuestModal from '~/components/PointsQuestModal.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const { siteName, siteLogo } = useSiteSettings()

if (!auth.isReady) auth.initFromStorage()
if (auth.token && !auth.user) await auth.fetchMe()
if (!auth.isLoggedIn || !auth.user) await navigateTo('/login', { replace: true })

const user = computed(() => auth.user)

watch(() => route.fullPath, () => { sidebarOpen.value = false })
</script>

<template>
  <div class="sv-shell">
    <!-- Sidebar -->
    <StudentSidebar
      :user-name="user?.name || 'Học viên'"
      user-role="Học viên"
      class="sv-sidebar"
      :class="{ 'is-open': sidebarOpen }"
    />

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div v-if="sidebarOpen" class="sv-overlay" aria-hidden="true" @click="sidebarOpen = false" />
    </Transition>

    <!-- Main -->
    <div class="sv-main">
      <AdminTopbar
        search-placeholder="Tìm khóa học, tài liệu..."
        :user-name="user?.name || 'Học viên'"
        :user-avatar="user?.avatar"
        user-role="Học viên"
        dashboard-path="/student"
        settings-path="/student"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />
      <div class="sv-content">
        <slot />
      </div>
      <AdminFooter />
    </div>
  </div>
  <AppToast />
  <PointsQuestModal />
</template>

<style scoped>
/* ── Shell ── */
.sv-shell {
  display: flex;
  min-height: 100vh;
  background-color: #f6f6f6;
}

/* ── Sidebar ── */
.sv-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
  transform: translateX(0);
  transition: transform 220ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Main ── */
.sv-main {
  flex: 1;
  margin-left: calc(var(--sidebar-width) + 16px);
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: #ffffff;
  transition: margin-left 300ms cubic-bezier(0.4, 0, 0.2, 1);
}
.sv-content { flex: 1; padding: 24px 28px 40px; }

/* ── Overlay ── */
.sv-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 199;
  background: rgba(0, 0, 0, 0.45);
}

/* ── Responsive ── */
@media (max-width: 1080px) {
  .sv-sidebar { transform: translateX(-100%); }
  .sv-sidebar.is-open { transform: translateX(0); box-shadow: 8px 0 40px rgba(0,0,0,0.18); }
  .sv-overlay { display: block; }
  .sv-main { margin-left: 0; }
  .sv-content { padding: 16px 16px 32px; }
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Dark mode ── */
:global([data-theme="dark"]) .sv-main { background: #1a1a1e; }
:global([data-theme="dark"]) .sv-shell { background-color: #111113; }
</style>
