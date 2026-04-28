<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AdminSidebar from '~/components/dashboard/AdminSidebar.vue'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import { useAuthStore } from '~/stores/auth'
import { getDashboardPath } from '~/composables/useAuthSession'

const auth = useAuthStore()

if (!auth.isReady) {
  auth.initFromStorage()
}

if (auth.token && !auth.user) {
  await auth.fetchMe()
}

if (!auth.isLoggedIn || !auth.user) {
  await navigateTo('/login', { replace: true })
}

if (auth.user && !(auth.user.roles || []).includes('admin')) {
  await navigateTo(getDashboardPath(auth.user.role), { replace: true })
}

const user = computed(() => auth.user)
const route = useRoute()
const sidebarOpen = ref(false)

const searchPlaceholder = computed(() => {
  return typeof route.meta.adminSearchPlaceholder === 'string'
    ? route.meta.adminSearchPlaceholder
    : 'Tim user, khoa hoc, giao dich...'
})

watch(
  () => route.fullPath,
  () => {
    sidebarOpen.value = false
  },
)
</script>

<template>
  <main class="dashboard-shell">
    <div class="dashboard-frame" :class="{ 'sidebar-open': sidebarOpen }">
      <AdminSidebar :user-name="user?.name || 'Admin User'" user-role="Admin" />

      <button
        v-if="sidebarOpen"
        type="button"
        class="dashboard-overlay button-reset"
        aria-label="Dong sidebar"
        @click="sidebarOpen = false"
      />

      <section class="dashboard-main">
        <AdminTopbar
          :search-placeholder="searchPlaceholder"
          :user-name="user?.name || 'Admin User'"
          user-role="Admin"
          @toggle-sidebar="sidebarOpen = !sidebarOpen"
        />

        <slot />
      </section>
    </div>
  </main>
</template>
