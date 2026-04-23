<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AdminSidebar from '~/components/dashboard/AdminSidebar.vue'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'

const user = useAuthUserCookie()

if (!user.value) {
  await navigateTo('/login', { replace: true })
}

if (user.value && normalizeRole(user.value.role) !== 'admin') {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
}

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
