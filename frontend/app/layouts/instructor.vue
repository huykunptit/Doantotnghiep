<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'
import InstructorSidebar from '~/components/dashboard/InstructorSidebar.vue'
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

const searchPlaceholder = computed(() => {
  if (route.path.includes('/curriculum')) return 'Tìm chương, bài học...'
  if (route.path.includes('/question-bank')) return 'Tìm câu hỏi...'
  return 'Tìm khóa học, học viên...'
})

watch(() => route.fullPath, () => { sidebarOpen.value = false })
</script>

<template>
  <div class="ins-shell">
    <!-- Sidebar -->
    <InstructorSidebar
      :user-name="user?.name || 'Giảng viên'"
      user-role="Giảng viên"
      class="ins-sidebar"
      :class="{ 'is-open': sidebarOpen }"
    />

    <!-- Overlay -->
    <Transition name="fade">
      <div v-if="sidebarOpen" class="ins-overlay" aria-hidden="true" @click="sidebarOpen = false" />
    </Transition>

    <!-- Main -->
    <div class="ins-main">
      <AdminTopbar
        :search-placeholder="searchPlaceholder"
        :user-name="user?.name || 'Giảng viên'"
        :user-avatar="user?.avatar"
        user-role="Giảng viên"
        dashboard-path="/instructor"
        settings-path="/instructor"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />
      <div class="ins-content">
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
.ins-shell {
  display: flex;
  min-height: 100vh;
  background-color: #f6f6f6;
}

/* ── Sidebar ── */
.ins-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
  transform: translateX(0);
  transition: transform 220ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Main ── */
.ins-main {
  margin-left: calc(var(--sidebar-width) + 16px);
  display: flex; flex-direction: column;
  min-height: 100vh; flex: 1; min-width: 0;
  background: #ffffff;
  transition: margin-left 300ms cubic-bezier(0.4, 0, 0.2, 1);
}
.ins-content { flex: 1; padding: 24px; min-width: 0; }

/* ── Overlay ── */
.ins-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 199;
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 1080px) {
  .ins-sidebar { transform: translateX(-100%); }
  .ins-sidebar.is-open { transform: translateX(0); box-shadow: 8px 0 40px rgba(0,0,0,0.18); }
  .ins-main { margin-left: 0; }
  .ins-overlay { display: block; }
}
@media (max-width: 640px) {
  .ins-content { padding: 16px; }
}

/* ── Dark mode (.dark class) ── */
:global([data-theme="dark"]) .ins-main { background: #1a1a1e; }
:global([data-theme="dark"]) .ins-shell { background-color: #111113; }
</style>
